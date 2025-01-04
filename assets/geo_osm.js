var rex_geo_osm = function (addressfields, geofields, id, mapbox_token, mapAttributes) {
    var map, marker;
    var $lat = $(geofields.lat);
    var $lng = $(geofields.lng);
    var $searchInput = $('#rex-geo-search-input-' + id);
    var $searchResults = $('#rex-geo-search-results-' + id);
    var $overlay = $('#rex-geo-overlay-' + id);
    var hasCoordinates = $lat.val() && $lng.val();
    var mapElement = document.getElementById('map-' + id);

    // Apply map attributes if provided
    if (mapAttributes && typeof mapAttributes === 'object') {
        Object.keys(mapAttributes).forEach(function(attr) {
            mapElement.setAttribute(attr, mapAttributes[attr]);
        });
    }

    // Initialize overlay
    if (!hasCoordinates) {
        $overlay.show();
    } else {
        $overlay.hide();
        createMap(parseFloat($lat.val()), parseFloat($lng.val()));
    }

    function createMap(lat, lng) {
        // Default map options
        var options = {
            gestureHandling: true,
            center: [lat, lng],
            zoom: 16
        };

        // Get zoom limits from data attributes if available
        if (mapElement) {
            var maxZoom = mapElement.getAttribute('data-max-zoom');
            var minZoom = mapElement.getAttribute('data-min-zoom');
            
            if (maxZoom) {
                options.maxZoom = parseInt(maxZoom, 10);
            }
            if (minZoom) {
                options.minZoom = parseInt(minZoom, 10);
            }
        }

        var layer = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a>'
        });

        if (!map) {
            map = L.map('map-' + id, options);
            layer.addTo(map);

            // Ensure zoom is within bounds after initialization
            if (options.maxZoom && map.getZoom() > options.maxZoom) {
                map.setZoom(options.maxZoom);
            }
            if (options.minZoom && map.getZoom() < options.minZoom) {
                map.setZoom(options.minZoom);
            }
        } else {
            map.setView([lat, lng], Math.min(16, map.getMaxZoom()));
        }

        if (!marker) {
            marker = L.marker([lat, lng], { 
                draggable: true 
            }).addTo(map);
            
            marker.on('dragend', function (e) {
                savePosition(e.target.getLatLng());
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        // Add touch gesture handling
        if (L.Browser.touch) {
            map.dragging.enable();
            map.touchZoom.enable();
            map.doubleClickZoom.enable();
            map.scrollWheelZoom.disable();
            map.boxZoom.disable();
            map.keyboard.disable();

            if (map.tap) map.tap.enable();
        }

        $overlay.hide();
    }

    function savePosition(pos) {
        $lat.val(pos.lat.toFixed(6));
        $lng.val(pos.lng.toFixed(6));
    }

    var searchTimeout;
    function handleSearch() {
        var value = $searchInput.val();
        if (value.length < 3) {
            $searchResults.removeClass('active').empty();
            return;
        }

        $.get('https://nominatim.openstreetmap.org/search', {
            q: value,
            format: 'json',
            limit: 5
        }).done(function (data) {
            $searchResults.empty();

            if (data.length === 0) {
                $searchResults.append(
                    $('<div class="rex-geo-search-result">').text('No results found')
                );
                return;
            }

            data.forEach(function (result) {
                $('<div class="rex-geo-search-result">')
                    .text(result.display_name)
                    .on('click', function () {
                        var lat = parseFloat(result.lat);
                        var lng = parseFloat(result.lon);
                        createMap(lat, lng);
                        savePosition({ lat: lat, lng: lng });
                        $searchInput.val(result.display_name);
                        $searchResults.removeClass('active').empty();
                    })
                    .appendTo($searchResults);
            });

            $searchResults.addClass('active');
        });
    }

    $searchInput.on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(handleSearch, 300);
    });

    $('#search-geo-' + id).on('click', function () {
        var address = [];
        addressfields.forEach(function (selector) {
            var value = $(selector).val();
            if (value) address.push(value);
        });
        if (address.length) {
            $searchInput.val(address.join(', ')).trigger('input');
        }
    });

    $('#browser-geo-' + id).on('click', function () {
        if (!("geolocation" in navigator)) {
            alert("Geolocation is not supported by this browser.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                createMap(position.coords.latitude, position.coords.longitude);
                savePosition({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                });
            },
            function (error) {
                alert("Error getting location: " + error.message);
            }
        );
    });

    $('#center-geo-' + id).on('click', function () {
        if (map && marker) {
            var zoom = Math.min(16, map.getMaxZoom());
            map.setView(marker.getLatLng(), zoom);
        }
    });

    // Improved touch handling for search results
    var touchStartY;
    $searchResults.on('touchstart', function(e) {
        touchStartY = e.originalEvent.touches[0].clientY;
    });

    $searchResults.on('touchmove', function(e) {
        var touchY = e.originalEvent.touches[0].clientY;
        var scrollTop = $searchResults[0].scrollTop;
        var scrollHeight = $searchResults[0].scrollHeight;
        var offsetHeight = $searchResults[0].offsetHeight;

        // Allow scrolling only when content is scrollable
        if (scrollHeight > offsetHeight) {
            if (scrollTop === 0 && touchY > touchStartY) {
                e.preventDefault(); // Prevent pull-to-refresh at top
            }
            if (scrollTop + offsetHeight >= scrollHeight && touchY < touchStartY) {
                e.preventDefault(); // Prevent overscroll at bottom
            }
        } else {
            e.preventDefault(); // Prevent scrolling when content fits
        }
    });

    // Close search results when clicking outside
    $(document).on('click touchend', function (e) {
        if (!$(e.target).closest('.rex-geo-search-wrapper').length) {
            $searchResults.removeClass('active');
        }
    });
};
