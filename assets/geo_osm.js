function rex_geo_osm_get_address(addressfields) {
    var out = [];
    for(var i=0;i<addressfields.length;i++) {
        if($(addressfields[i]).val()!='')
            out.push($(addressfields[i]).val());
    }
    return out.join(",");
}

var rex_geo_osm = function(addressfields, geofields, id, mapbox_token) {
    /*
     * adressfields (arr - strasse/plz/ort)
     * geofield (obj - lat/lng)
     */


    var current_lat = $(geofields.lat).val();
    var current_lng = $(geofields.lng).val();

    // Karte laden


    if(mapbox_token=='') {
        var map = L.map('map-'+id).setView([current_lat, current_lng], 16);
        L.tileLayer('//{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    }
    else {

        var mapboxAttribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery © <a href="http://mapbox.com">Mapbox</a>';

        var streets = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, {id: 'mapbox.streets', attribution: mapboxAttribution}),
            streets_sattelite   = L.tileLayer('//api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token='+mapbox_token, {id: 'mapbox.streets-satellite', attribution: mapboxAttribution});

        var map = L.map('map-'+id, {
            center: [current_lat, current_lng],
            zoom: 16,
            layers: [streets, streets_sattelite]
        });

        var baseMaps = {
            "Karte": streets,
            "Satellit": streets_sattelite
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
        map.setView([lat, lng, 16]);
        marker.setLatLng([lat, lng]);
    });

    $('#set-geo-'+id).on('click', function(e) {
        e.preventDefault();

        var street = $(addressfields[0]).val();
        var city = $(addressfields[2]).val();
        var postalcode = $(addressfields[1]).val();

        if(street=='' || city=='' || postalcode == '') {
            alert('Bitte vorerst die Adresse vollständig ausfüllen.');
            return true;
        }


        var xhr = new XMLHttpRequest();
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                var json = JSON.parse(xhr.response);
                if(json.length==0) {
                    alert('Adresse nicht gefunden.')
                    return false;
                }
                $(geofields.lat).val(json[0].lat);
                $(geofields.lng).val(json[0].lon);
                setTimeout(function(){
                    $(geofields.lat).keyup();
                }, 200);
            } else {
                console.log('Es trat ein Fehler auf.');
            }
        };
        xhr.open('GET', 'https://nominatim.openstreetmap.org/search?street='+street+'&city='+city+'&postalcode='+postalcode+'&format=json&polygon=0&addressdetails=0&limit=1');
        xhr.send();

    })
}
