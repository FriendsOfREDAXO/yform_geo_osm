/**
 * Helper function to get concatenated address from address fields
 * @param {Array} addressfields - Array of jQuery selectors for address fields
 * @returns {string} Concatenated address
 */
function rex_geo_osm_get_address(addressfields) {
    return addressfields
        .map(selector => $(selector).val())
        .filter(value => value && value.trim() !== '')
        .join(', ');
}

/**
 * Main Geo OSM class
 * @param {Array} addressfields - Array of address field selectors
 * @param {Object} geofields - Object containing lat/lng field selectors
 * @param {string} id - Unique identifier
 * @param {string} mapbox_token - Optional Mapbox API token
 * @param {Object} options - Additional options for initialization
 */
var rex_geo_osm = function(addressfields, geofields, id, mapbox_token, options = {}) {
    // Private variables
    let map;
    let marker = null;
    const GEOCODING_DELAY = 500; // ms delay between geocoding requests
    let geocodingTimeout;
    
    // Constants
    const DEFAULT_LAT = options.initialLat || 50.1109221;
    const DEFAULT_LNG = options.initialLng || 8.6821267;
    const DEFAULT_ZOOM = options.initialZoom || 2;
    const DETAIL_ZOOM = 14;
    
    // Get current values from fields
    const current_lat = $(geofields.lat).val();
    const current_lng = $(geofields.lng).val();
    
    /**
     * Initialize the map
     */
    function initializeMap() {
        const startLat = current_lat || DEFAULT_LAT;
        const startLng = current_lng || DEFAULT_LNG;
        const startZoom = (current_lat && current_lng) ? DETAIL_ZOOM : DEFAULT_ZOOM;

        if (mapbox_token) {
            initMapbox(startLat, startLng, startZoom);
        } else {
            initOpenStreetMap(startLat, startLng, startZoom);
        }

        // Create marker if coordinates exist
        if (current_lat && current_lng) {
            createMarker(current_lat, current_lng);
        }
    }

    /**
     * Initialize Mapbox map
     */
    function initMapbox(lat, lng, zoom) {
        const mapboxAttribution = 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery © <a href="http://mapbox.com">Mapbox</a>';

        const streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=' + mapbox_token,
            { id: 'mapbox.streets', attribution: mapboxAttribution });
            
        const streets_satellite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=' + mapbox_token,
            { id: 'mapbox.streets-satellite', attribution: mapboxAttribution });

        map = L.map('map-' + id, {
            center: [lat, lng],
            zoom: zoom,
            layers: [streets]
        });

        L.control.layers({
            "Map": streets,
            "Satellite": streets_satellite
        }).addTo(map);
    }

    /**
     * Initialize OpenStreetMap
     */
    function initOpenStreetMap(lat, lng, zoom) {
        const streets = L.tileLayer('//{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
            attribution: 'Map data © <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        });

        map = L.map('map-' + id, {
            center: [lat, lng],
            zoom: zoom,
            layers: [streets]
        });
    }

    /**
     * Create or update marker
     */
    function createMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], {
                draggable: true
            }).on('dragend', function(ev) {
                const newPos = ev.target.getLatLng();
                updateGeoFields(newPos.lat, newPos.lng);
            }).addTo(map);
        }
    }

    /**
     * Update geo fields with new coordinates
     */
    function updateGeoFields(lat, lng) {
        $(geofields.lat).val(lat);
        $(geofields.lng).val(lng);
    }

    /**
     * Perform geocoding search
     */
    async function performSearch(searchText) {
        if (!searchText.trim()) {
            $('#rex-geo-search-results-' + id).empty();
            return;
        }

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?q=' + 
                encodeURIComponent(searchText) + 
                '&format=json&polygon=0&addressdetails=1&limit=5'
            );
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            displaySearchResults(data);
        } catch (error) {
            console.error('Search error:', error);
            $('#rex-geo-search-results-' + id).html(
                '<div class="search-result error">Search failed. Please try again later.</div>'
            );
        }
    }

    /**
     * Display search results
     */
    function displaySearchResults(results) {
        const resultsContainer = $('#rex-geo-search-results-' + id);
        resultsContainer.empty();

        if (results.length === 0) {
            resultsContainer.append('<div class="search-result">No results found</div>');
            return;
        }

        results.forEach(result => {
            const resultDiv = $('<div class="search-result"></div>')
                .text(result.display_name)
                .on('click', () => selectLocation(result));
            resultsContainer.append(resultDiv);
        });
    }

    /**
     * Select location from search results
     */
    function selectLocation(location) {
        updateGeoFields(location.lat, location.lon);
        createMarker(location.lat, location.lon);
        map.setView([location.lat, location.lon], 16);
        closeSearchModal();
    }

    /**
     * Close search modal and reset
     */
    function closeSearchModal() {
        $('#rex-geo-search-modal-' + id).hide();
        $('#rex-geo-search-input-' + id).val('');
        $('#rex-geo-search-results-' + id).empty();
    }

    /**
     * Get coordinates from address fields
     */
    async function geocodeAddress() {
        const address = rex_geo_osm_get_address(addressfields);
        
        if (!address) {
            alert('Please fill in at least one address field.');
            return;
        }

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?q=' + 
                encodeURIComponent(address) + 
                '&format=json&polygon=0&addressdetails=0&limit=1'
            );

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            
            if (data.length === 0) {
                alert('Address not found');
                return;
            }

            updateGeoFields(data[0].lat, data[0].lon);
            createMarker(data[0].lat, data[0].lon);
            map.setView([data[0].lat, data[0].lon], 16);
        } catch (error) {
            console.error('Geocoding error:', error);
            alert('Geocoding failed. Please try again later.');
        }
    }

    /**
     * Initialize event listeners
     */
    function initEventListeners() {
        // Handle coordinate field updates
        $(geofields.lat + ',' + geofields.lng).on('keyup', function() {
            const lat = $(geofields.lat).val();
            const lng = $(geofields.lng).val();
            if (lat && lng) {
                createMarker(lat, lng);
                map.setView([lat, lng], DETAIL_ZOOM);
            }
        });

        // Search modal
        $('#search-geo-' + id).on('click', function() {
            $('#rex-geo-search-modal-' + id).show();
            $('#rex-geo-search-input-' + id).focus();
        });

        $('.rex-geo-search-close').on('click', closeSearchModal);

        // Search input with debounce
        $('#rex-geo-search-input-' + id).on('input', function(e) {
            clearTimeout(geocodingTimeout);
            geocodingTimeout = setTimeout(() => performSearch(e.target.value), GEOCODING_DELAY);
        });

        // Browser geolocation
        $('#browser-geo-' + id).on('click', function() {
            if (!("geolocation" in navigator)) {
                alert("Your browser doesn't support geolocation.");
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    updateGeoFields(lat, lng);
                    createMarker(lat, lng);
                    map.setView([lat, lng], 16);
                },
                function(error) {
                    alert("Geolocation failed: " + error.message);
                }
            );
        });

        // Center map on marker
        $('#center-geo-' + id).on('click', function(e) {
            e.preventDefault();
            if (marker) {
                map.setView(marker.getLatLng(), 16);
            }
        });

        // Geocode address
        $('#set-geo-' + id).on('click', function(e) {
            e.preventDefault();
            geocodeAddress();
        });
    }

    // Initialize map and events
    initializeMap();
    initEventListeners();

    // Public API
    return {
        map: map,
        marker: marker,
        setView: function(lat, lng, zoom) {
            createMarker(lat, lng);
            map.setView([lat, lng], zoom);
        },
        getMap: function() {
            return map;
        },
        getMarker: function() {
            return marker;
        },
        createMarker: createMarker
    };
};
