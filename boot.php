<?php

if (rex::isBackend() && rex::getUser()) {

	rex_yform::addTemplatePath(rex_path::plugin('yform','geo_osm','ytemplates'));

	rex_view::addJsFile($this->getAssetsUrl('leaflet/leaflet.js'));
	rex_view::addCssFile($this->getAssetsUrl('leaflet/leaflet.css'));

    rex_view::addJsFile($this->getAssetsUrl('geo_osm.js'));
    rex_view::addCssFile($this->getAssetsUrl('geo_osm.css'));
}