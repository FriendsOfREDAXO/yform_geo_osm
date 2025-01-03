var rex_geo_osm = function(addressfields, geofields, id, mapbox_token) {
    var map, marker;
    var $lat = $(geofields.lat);
    var $lng = $(geofields.lng);
    var $searchInput = $('#rex-geo-search-input-'+id);
    var $searchResults = $('#rex-geo-search-results-'+id);
    var $overlay = $('#rex-geo-overlay-'+id);

    function createMap(lat, lng) {
        var options = {
            gestureHandling: true,
            center: [lat, lng],
            zoom: 16
        };

        var layer = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a>'
        });

        map = L.map('map-'+id, options);
        layer.addTo(map);
        
        marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        marker.on('dragend', function(e) {
            savePosition(e.target.getLatLng());
        });

        $overlay.hide();
    }

    function savePosition(pos) {
        $lat.val(pos.lat.toFixed(6));
        $lng.val(pos.lng.toFixed(6));
    }

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
        }).done(function(data) {
            $searchResults.empty();
            
            data.forEach(function(result) {
                $('<div class="rex-geo-search-result">')
                    .text(result.display_name)
                    .on('click', function() {
                        if (!map) {
                            createMap(result.lat, result.lon);
                        } else {
                            marker.setLatLng([result.lat, result.lon]);
                            map.setView([result.lat, result.lon], 16);
                        }
                        savePosition(marker.getLatLng());
                        $searchResults.removeClass('active');
                    })
                    .appendTo($searchResults);
            });
            
            $searchResults.addClass('active');
        });
    }

    // Initialize if coordinates exist
    if ($lat.val() && $lng.val()) {
        createMap($lat.val(), $lng.val());
    }

    // Search handling
    var searchTimeout;
    $searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(handleSearch, 300);
    });

    // Fill search from address fields
    $('#search-geo-'+id).on('click', function() {
        var address = [];
        addressfields.forEach(function(selector) {
            var value = $(selector).val();
            if (value) address.push(value);
        });
        if (address.length) {
            $searchInput.val(address.join(', ')).trigger('input');
        }
    });

    // Geolocation
    $('#browser-geo-'+id).on('click', function() {
        if (!("geolocation" in navigator)) return;
        
        navigator.geolocation.getCurrentPosition(function(position) {
            if (!map) {
                createMap(position.coords.latitude, position.coords.longitude);
            } else {
                marker.setLatLng([position.coords.latitude, position.coords.longitude]);
                map.setView([position.coords.latitude, position.coords.longitude], 16);
            }
            savePosition(marker.getLatLng());
        });
    });

    // Center map
    $('#center-geo-'+id).on('click', function() {
        if (marker) map.setView(marker.getLatLng(), 16);
    });

    // Close search results on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.rex-geo-search-wrapper').length) {
            $searchResults.removeClass('active');
        }
    });
};
