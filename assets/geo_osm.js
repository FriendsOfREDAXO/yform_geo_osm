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

    L.Map.addInitHook(function () {
        this.getContainer()._leaflet_map = this;
    });

    // Map initialization
    var map;
    if(mapbox_token=='') {
        map = L.map('map-'+id).setView([current_lat, current_lng], 16);
        L.tileLayer('//{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    } else {
        var mapboxAttribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery © <a href="http://mapbox.com">Mapbox</a>';

        var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets', attribution: mapboxAttribution}),
            streets_sattelite = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, 
            {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

        map = L.map('map-'+id, {
            center: [current_lat, current_lng],
            zoom: 16,
            layers: [streets, streets_sattelite]
        });

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
        map.setView([lat, lng], 16);
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

    // Search functionality
    $('#rex-geo-search-button-'+id).on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        performSearch($('#rex-geo-search-input-'+id).val());
        return false;
    });

    $('#rex-geo-search-input-'+id).on('keypress', function(e) {
        if(e.which == 13) {
            e.preventDefault();
            e.stopPropagation();
            performSearch($(this).val());
            return false;
        }
    });

    function performSearch(searchText) {
        if(searchText.trim() === '') return;

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
                map.setView([json[0].lat, json[0].lon], 16);
                marker.setLatLng([json[0].lat, json[0].lon]);
                $('#rex-geo-search-modal-'+id).hide();
            } else {
                console.log('An error occurred.');
            }
        };
        xhr.open('GET', 'https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(searchText)+'&format=json&polygon=0&addressdetails=0&limit=1');
        xhr.send();
    }

    // Browser geolocation
    $('#browser-geo-'+id).on('click', function() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                $(geofields.lat).val(lat);
                $(geofields.lng).val(lng);
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
            }, function(error) {
                alert("Geolocation failed: " + error.message);
            });
        } else {
            alert("Your browser doesn't support geolocation.");
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
                map.setView([json[0].lat, json[0].lon], 16);
                marker.setLatLng([json[0].lat, json[0].lon]);
            } else {
                console.log('An error occurred.');
            }
        };
        xhr.open('GET', 'https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(street+' '+city+' '+postalcode)+'&format=json&polygon=0&addressdetails=0&limit=1');
        xhr.send();
    });
}
