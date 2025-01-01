<?php

$addon = rex_addon::get('yform_geo_osm');

rex_yform::addTemplatePath(rex_path::addon('yform_geo_osm', 'ytemplates'));

if (rex::isBackend() && rex::getUser() !== null) {
    // Assets nur laden wenn geolocation addon nicht verfügbar.
    dump(rex_addon::get('geolocation')->isAvailable() );
    if (!rex_addon::get('geolocation')->isAvailable()) {
        rex_view::addJsFile($addon->getAssetsUrl('leaflet/leaflet.js'));
        rex_view::addJsFile($addon->getAssetsUrl('Leaflet.GestureHandling/leaflet-gesture-handling.min.js'));
        rex_view::addJsFile($addon->getAssetsUrl('CoordPicker.js'));
        rex_view::addCssFile($addon->getAssetsUrl('leaflet/leaflet.css'));
        rex_view::addCssFile($addon->getAssetsUrl('Leaflet.GestureHandling/leaflet-gesture-handling.min.css'));
    }
    rex_view::addJsFile($addon->getAssetsUrl('geo_osm.js'));
    rex_view::addCssFile($addon->getAssetsUrl('geo_osm.css'));
}
