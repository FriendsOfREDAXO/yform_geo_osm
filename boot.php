<?php

if (rex::isBackend() && rex::getUser()) {
    rex_yform::addTemplatePath(rex_path::addon('yform_geo_osm', 'ytemplates'));
	// Assets nur laden wenn geolocation addon nicht verfügbar. 
	if (!rex_addon::get('geolocation')->isAvailable()) {
           rex_view::addJsFile($this->getAssetsUrl('leaflet/leaflet.js'));
           rex_view::addCssFile($this->getAssetsUrl('leaflet/leaflet.css'));
    }
    rex_view::addJsFile($this->getAssetsUrl('geo_osm.js'));
    rex_view::addCssFile($this->getAssetsUrl('geo_osm.css'));
}
