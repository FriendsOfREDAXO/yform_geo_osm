<?php

$addon = rex_addon::get('yform_geo_osm');

rex_yform::addTemplatePath(rex_path::addon('yform_geo_osm', 'ytemplates'));

if (rex::isBackend() && rex::getUser() !== null) {
    // Assets nur laden wenn geolocation addon nicht verfügbar.
    if (!rex_addon::get('geolocation')->isAvailable()) {
        rex_view::addJsFile($addon->getAssetsUrl('leaflet/leaflet.js'));
        rex_view::addJsFile($addon->getAssetsUrl('CoordPicker.js'));
        rex_view::addCssFile($addon->getAssetsUrl('leaflet/leaflet.css'));
    }
    rex_view::addJsFile($addon->getAssetsUrl('geo_osm.js'));
    rex_view::addCssFile($addon->getAssetsUrl('geo_osm.css'));
}
