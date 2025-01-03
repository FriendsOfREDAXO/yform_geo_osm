var rex_geo_osm = function (addressfields, geofields, id, mapbox_token) {
    var map, marker;
    var $lat = $(geofields.lat);
    var $lng = $(geofields.lng);
    var $searchInput = $('#rex-geo-search-input-' + id);
    var $searchResults = $('#rex-geo-search-results-' + id);
    var $overlay = $('#rex-geo-overlay-' + id);
    var hasCoordinates = $lat.val() && $lng.val();

    // Initialize overlay
    if (!hasCoordinates) {
        $overlay.show();
    } else {
        $overlay.hide();
        createMap(parseFloat($lat.val()), parseFloat($lng.val()));
    }

    function createMap(lat, lng) {
        var options = {
            gestureHandling: true,
            center: [lat, lng],
            zoom: 16
        };

        var layer = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a>'
        });

        if (!map) {
            map = L.map('map-' + id, options);
            layer.addTo(map);
        } else {
            map.setView([lat, lng], 16);
        }

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                savePosition(e.target.getLatLng());
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

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
        }).done(function (data) {
            $searchResults.empty();

            if (data.length === 0) {
                $searchResults.append(
                    $('<div class="rex-geo-search-result">').text('No results found')
                );
                return;
            }

            // Im YForm Geocoder:
            data.forEach(function (result) {
                $('<div class="rex-geo-search-result">')
                    .text(result.display_name)
                    .on('click', function () {
                        var lat = parseFloat(result.lat);
                        var lng = parseFloat(result.lon);
                        createMap(lat, lng);
                        savePosition({ lat: lat, lng: lng });
                        $searchInput.val(result.display_name);
                        $searchResults.removeClass('active').empty(); // Leeren und ausblenden
                    })
                    .appendTo($searchResults);
            });

            $searchResults.addClass('active');
        });
    }

    var searchTimeout;
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
            map.setView(marker.getLatLng(), 16);
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.rex-geo-search-wrapper').length) {
            $searchResults.removeClass('active');
        }
    });
};
