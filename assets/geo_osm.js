function rex_geo_osm_get_address(addressfields) {
    var out = [];
    for(var i=0; i<addressfields.length; i++) {
        if($(addressfields[i]).val()!='')
            out.push($(addressfields[i]).val());
    }
    return out.join(",");
}

var rex_geo_osm = function(addressfields, geofields, id, mapbox_token) {
    var current_lat = $(geofields.lat).val() || 51.1657;
    var current_lng = $(geofields.lng).val() || 10.4515;
    let defaultZoom = 16;

    L.Map.addInitHook(function () {
        this.getContainer()._leaflet_map = this;
    });

    // Map initialization
    var map;
    let mapOptions = {
        center: [current_lat, current_lng],
        zoom: defaultZoom,
        gestureHandling: true,
        duration: 500,
    }
    if(mapbox_token=='') {
        let streets = L.tileLayer('//{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        });
        mapOptions.layers = [streets];
        map = L.map('map-'+id,mapOptions);
    } else {
        var mapboxAttribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery © <a href="http://mapbox.com">Mapbox</a>';

        var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets', attribution: mapboxAttribution}),
            streets_sattelite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

        mapOptions.layers = [streets, streets_sattelite];
        map = L.map('map-'+id, mapOptions);

        var baseMaps = {
            "Map": streets,
            "Satellite": streets_sattelite
        };

        L.control.layers(baseMaps).addTo(map);
    }

    var marker = L.marker([current_lat, current_lng], {
        draggable: true
    }).on('dragend', function(ev) {
        var newPos = ev.target.getLatLng();
        $(geofields.lat).val(newPos.lat);
        $(geofields.lng).val(newPos.lng);
    }).addTo(map);

    $(geofields.lat+','+geofields.lng).on('keyup', function() {
        var lat = $(geofields.lat).val();
        var lng = $(geofields.lng).val();
        map.setView([lat, lng], defaultZoom);
        marker.setLatLng([lat, lng]);
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
        map.setView([location.lat, location.lon], defaultZoom);
        marker.setLatLng([location.lat, location.lon]);
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
                map.setView([lat, lng], defaultZoom);
                marker.setLatLng([lat, lng]);
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
        map.setView(marker.getLatLng(), defaultZoom);

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
                map.setView([json[0].lat, json[0].lon], defaultZoom);
                marker.setLatLng([json[0].lat, json[0].lon]);
            } else {
                console.log('An error occurred.');
            }
        };
        xhr.open('GET', 'https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(street+' '+city+' '+postalcode)+'&format=json&polygon=0&addressdetails=0&limit=1');
        xhr.send();
    });
}
