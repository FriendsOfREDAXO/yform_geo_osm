var rex_geo_osm = function(addressfields, geofields, id, mapbox_token) {
    var current_lat = $(geofields.lat).val();
    var current_lng = $(geofields.lng).val();
    var hasCoordinates = current_lat && current_lng;
    
    // Map initialization
    var mapOptions = {
        gestureHandling: true,
        duration: 500
    };

    if (hasCoordinates) {
        mapOptions.center = [current_lat, current_lng];
        mapOptions.zoom = 16;
    } else {
        mapOptions.center = [30, 0];
        mapOptions.zoom = 2;
    }

    var map;
    if (mapbox_token) {
        var mapboxAttribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, Imagery © <a href="http://mapbox.com">Mapbox</a>';
        var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets', attribution: mapboxAttribution}),
            streets_satellite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

        mapOptions.layers = [streets, streets_satellite];
        map = L.map('map-'+id, mapOptions);

        L.control.layers({
            "Map": streets,
            "Satellite": streets_satellite
        }).addTo(map);
    } else {
        var streets = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        });
        mapOptions.layers = [streets];
        map = L.map('map-'+id, mapOptions);
    }

    // Marker handling
    var marker;
    if (hasCoordinates) {
        marker = L.marker([current_lat, current_lng], {
            draggable: true
        }).addTo(map);
        
        marker.on('dragend', function(ev) {
            var pos = ev.target.getLatLng();
            updateCoordinates(pos.lat, pos.lng);
        });
        
        $('#rex-geo-overlay-'+id).hide();
    }

    function updateCoordinates(lat, lng) {
        $(geofields.lat).val(lat);
        $(geofields.lng).val(lng);
        
        if (!marker) {
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            
            marker.on('dragend', function(ev) {
                var pos = ev.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
            
            $('#rex-geo-overlay-'+id).hide();
        } else {
            marker.setLatLng([lat, lng]);
        }
    }

    // Search functionality
    var searchInput = $('#rex-geo-search-input-'+id);
    var searchResults = $('#rex-geo-search-results-'+id);
    var searchTimeout;

    // Pre-fill search if address fields exist
    if (addressfields.length > 0) {
        var address = [];
        addressfields.forEach(function(selector) {
            var value = $(selector).val();
            if (value) address.push(value);
        });
        if (address.length > 0) {
            searchInput.val(address.join(', '));
        }
    }

    searchInput.on('input focus', function() {
        clearTimeout(searchTimeout);
        var value = $(this).val();
        
        if (value.length < 3) {
            searchResults.removeClass('active').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.get('https://nominatim.openstreetmap.org/search', {
                q: value,
                format: 'json',
                limit: 5
            })
            .done(function(data) {
                searchResults.empty();
                
                if (data.length === 0) {
                    searchResults.append(
                        $('<div class="rex-geo-search-result">').text('No results found')
                    );
                } else {
                    data.forEach(function(result) {
                        $('<div class="rex-geo-search-result">')
                            .text(result.display_name)
                            .on('click', function() {
                                selectLocation(result);
                                searchResults.removeClass('active');
                            })
                            .appendTo(searchResults);
                    });
                }
                searchResults.addClass('active');
            });
        }, 300);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.rex-geo-search-wrapper').length) {
            searchResults.removeClass('active');
        }
    });

    function selectLocation(location) {
        var lat = parseFloat(location.lat);
        var lng = parseFloat(location.lon);
        
        updateCoordinates(lat, lng);
        map.setView([lat, lng], 16);
        searchInput.val(location.display_name);
    }

    // Browser geolocation
    $('#browser-geo-'+id).on('click', function() {
        if (!("geolocation" in navigator)) {
            alert("Your browser doesn't support geolocation.");
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                
                updateCoordinates(lat, lng);
                map.setView([lat, lng], 16);
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
