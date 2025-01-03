var rex_geo_osm = function(addressfields, geofields, id, mapbox_token) {
    var current_lat = $(geofields.lat).val();
    var current_lng = $(geofields.lng).val();
    var map, marker;

    initMap();

    function initMap() {
        if (mapbox_token) {
            var mapboxAttribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, Imagery © <a href="http://mapbox.com">Mapbox</a>';
            var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
                {id: 'mapbox.streets', attribution: mapboxAttribution}),
                streets_satellite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
                {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

            map = L.map('map-'+id, {
                gestureHandling: true,
                layers: [streets, streets_satellite]
            });

            L.control.layers({
                "Map": streets,
                "Satellite": streets_satellite
            }).addTo(map);
        } else {
            var streets = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
            });
            map = L.map('map-'+id, {
                gestureHandling: true,
                layers: [streets]
            });
        }

        if (current_lat && current_lng) {
            setMarker(current_lat, current_lng);
            $('#rex-geo-overlay-'+id).hide();
        }
    }

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            
            marker.on('dragend', function(ev) {
                var pos = ev.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
        }
        map.setView([lat, lng], 16);
    }

    function updateCoordinates(lat, lng) {
        $(geofields.lat).val(lat.toFixed(6));
        $(geofields.lng).val(lng.toFixed(6));
        setMarker(lat, lng);
        $('#rex-geo-overlay-'+id).hide();
    }

    // Search functionality
    var searchTimeout;
    $('#rex-geo-search-input-'+id).on('input focus', function() {
        clearTimeout(searchTimeout);
        var value = $(this).val();
        var $results = $('#rex-geo-search-results-'+id);
        
        if (value.length < 3) {
            $results.removeClass('active').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.get('https://nominatim.openstreetmap.org/search', {
                q: value,
                format: 'json',
                limit: 5
            })
            .done(function(data) {
                $results.empty();
                
                if (data.length === 0) {
                    $results.append(
                        $('<div class="rex-geo-search-result">').text('No results found')
                    );
                } else {
                    data.forEach(function(result) {
                        $('<div class="rex-geo-search-result">')
                            .text(result.display_name)
                            .on('click', function() {
                                updateCoordinates(result.lat, result.lon);
                                $(this).closest('.rex-geo-search-results').removeClass('active');
                            })
                            .appendTo($results);
                    });
                }
                $results.addClass('active');
            });
        }, 300);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.rex-geo-search-wrapper').length) {
            $('.rex-geo-search-results').removeClass('active');
        }
    });

    // Browser geolocation
    $('#browser-geo-'+id).on('click', function() {
        if (!("geolocation" in navigator)) {
            alert("Your browser doesn't support geolocation.");
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                updateCoordinates(
                    position.coords.latitude,
                    position.coords.longitude
                );
            },
            function(error) {
                alert("Geolocation failed: " + error.message);
            }
        );
    });

    // Map center button
    $('#center-geo-'+id).on('click', function(e) {
        e.preventDefault();
        if (marker) {
            map.setView(marker.getLatLng(), 16);
        }
    });
};
