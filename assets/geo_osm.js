function rex_geo_osm_get_address(addressfields) {
    var out = [];
    for(var i=0; i<addressfields.length; i++) {
        if($(addressfields[i]).val()!='')
            out.push($(addressfields[i]).val());
    }
    return out.join(",");
}

var rex_geo_osm = function(addressfields, geofields, id, mapbox_token, options = {}) {
    
    // Standardwerte für Frankfurt und Welt-Zoom, können durch options überschrieben werden
    let initialLat = options.initialLat || 50.1109221;
    let initialLng = options.initialLng || 8.6821267;
    let initialZoom = options.initialZoom || 2;

    // Aktuelle Werte aus den Feldern holen
    var current_lat = $(geofields.lat).val();
    var current_lng = $(geofields.lng).val();
    let defaultZoom = current_lat && current_lng ? 14 : initialZoom;
   
    L.Map.addInitHook(function () {
        this.getContainer()._leaflet_map = this;
    });

    // Basis Map-Optionen
    let defaultMapOptions = {
        center: current_lat && current_lng ? [current_lat, current_lng] : [initialLat, initialLng],
        zoom: defaultZoom,
        gestureHandling: true,
        duration: 500
    };

    // Map-Optionen mit benutzerdefinierten Optionen zusammenführen
    let mapOptions = {
        ...defaultMapOptions,
        ...(options.mapOptions || {})
    };
    
    // Map initialization
    var map;
    if(mapbox_token=='') {
        let streets = L.tileLayer('//{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
            attribution: 'Map data © <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        });
        map = L.map('map-'+id, mapOptions).addLayer(streets);
    } else {
        var mapboxAttribution = 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery © <a href="http://mapbox.com">Mapbox</a>';

        var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets', attribution: mapboxAttribution}),
            streets_sattelite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

        map = L.map('map-'+id, mapOptions).addLayer(streets);

        var baseMaps = {
            "Map": streets,
            "Satellite": streets_sattelite
        };

        L.control.layers(baseMaps).addTo(map);
    }

    var marker = null;
    var defaultMarkerOptions = {
        draggable: true
    };
    
    // Marker-Optionen mit benutzerdefinierten Optionen zusammenführen
    var markerOptions = {
        ...defaultMarkerOptions,
        ...(options.markerOptions || {})
    };

    // Marker nur erstellen wenn Koordinaten vorhanden sind
    if(current_lat && current_lng) {
        createMarker(current_lat, current_lng);
    }

    function createMarker(lat, lng) {
        if(marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], markerOptions)
                .on('dragend', function(ev) {
                    var newPos = ev.target.getLatLng();
                    $(geofields.lat).val(newPos.lat);
                    $(geofields.lng).val(newPos.lng);
                }).addTo(map);
        }
    }

    $(geofields.lat+','+geofields.lng).on('keyup', function() {
        var lat = $(geofields.lat).val();
        var lng = $(geofields.lng).val();
        if(lat && lng) {
            createMarker(lat, lng);
            map.setView([lat, lng], defaultZoom);
        }
    });

    // Show/hide search modal
    $('#search-geo-'+id).on('click', function() {
        $('#rex-geo-search-modal-'+id).show();
        $('#rex-geo-search-input-'+id).focus();
    });

    $('.rex-geo-search-close').on('click', function() {
        $(this).closest('.rex-geo-search-modal').hide();
    });

    // Live search
    $('#rex-geo-search-input-'+id).on('input', function(e) {
        e.preventDefault();
        performSearch($(this).val());
    });

    let searchTimeout;

    function performSearch(searchText) {
        if(searchText.trim() === '') {
            $('#rex-geo-search-results-'+id).empty();
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            var xhr = new XMLHttpRequest();
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var json = JSON.parse(xhr.response);
                    displaySearchResults(json);
                } else {
                    console.log('An error occurred.');
                }
            };
            xhr.open('GET', 'https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(searchText)+'&format=json&polygon=0&addressdetails=1&limit=5');
            xhr.send();
        }, 300);
    }

    function displaySearchResults(results) {
        const resultsContainer = $('#rex-geo-search-results-'+id);
        resultsContainer.empty();

        if(results.length === 0) {
            resultsContainer.append('<div class="search-result">No results found</div>');
            return;
        }

        results.forEach(result => {
            const resultDiv = $('<div class="search-result"></div>');
            resultDiv.text(result.display_name);
            resultDiv.on('click', () => selectLocation(result));
            resultsContainer.append(resultDiv);
        });
    }

    function selectLocation(location) {
        $(geofields.lat).val(location.lat);
        $(geofields.lng).val(location.lon);
        createMarker(location.lat, location.lon);
        map.setView([location.lat, location.lon], 16);
        $('#rex-geo-search-modal-'+id).hide();
        $('#rex-geo-search-input-'+id).val('');
        $('#rex-geo-search-results-'+id).empty();
    }

    // Browser geolocation
    $('#browser-geo-'+id).on('click', function() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                $(geofields.lat).val(lat);
                $(geofields.lng).val(lng);
                createMarker(lat, lng);
                map.setView([lat, lng], 16);
            }, function(error) {
                alert("Geolocation failed: " + error.message);
            });
        } else {
            alert("Your browser doesn't support geolocation.");
        }
    });

    // Recenter the map to the curent marker-position
    $('#center-geo-'+id).on('click', function(e) {
        e.preventDefault();
        if(marker) {
            map.setView(marker.getLatLng(), 16);
        }
    });        

    // Original address geocoding
    $('#set-geo-'+id).on('click', function(e) {
        e.preventDefault();

        var street = $(addressfields[0]).val();
        var city = $(addressfields[2]).val();
        var postalcode = $(addressfields[1]).val();

        if(street=='' || city=='' || postalcode == '') {
            alert('Please fill in the complete address first.');
            return true;
        }

        var xhr = new XMLHttpRequest();
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                var json = JSON.parse(xhr.response);
                if(json.length==0) {
                    alert('Address not found')
                    return false;
                }
                $(geofields.lat).val(json[0].lat);
                $(geofields.lng).val(json[0].lon);
                createMarker(json[0].lat, json[0].lon);
                map.setView([json[0].lat, json[0].lon], 16);
            } else {
                console.log('An error occurred.');
            }
        };
        xhr.open('GET', 'https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(street+' '+city+' '+postalcode)+'&format=json&polygon=0&addressdetails=0&limit=1');
        xhr.send();
    });

    // Public methods/properties
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
}
