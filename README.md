YForm Erweiterung: Geo (OSM)
=============

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/yform_geo_osm/master/assets/preview.png)

* YForm Erweiterung für die Einbindung einer Geocoding-Funktion basierend auf Openstreetmaps.
* Anpassung der Geo-Daten über Map-Marker möglich
* Openstreetmaps (Karte), optional Mapbox (Karte + Satellit)
* PHP Klasse "geo_search" für eine postleitzahlbasierte Umkreissuche

Installation
-------

* Paket herunterladen oder über den Installer installieren


Beispielmodul (Ausgabe) YForm Frontend
-------

```php

<?php

	rex_extension::register('OUTPUT_FILTER', 'yform_geo_osm::addAssets');

	$yform = new rex_yform();

	$yform->setObjectparams('form_name', 'table-rex_geotest');
	$yform->setObjectparams('form_action',rex_getUrl('REX_ARTICLE_ID'));
	$yform->setObjectparams('form_ytemplate', 'bootstrap');
	$yform->setObjectparams('form_showformafterupdate', 0);
	$yform->setObjectparams('real_field_names', true);

	$yform->setValueField('text', array('street','Straße','','0'));
	$yform->setValueField('text', array('postalcode','PLZ','','0'));
	$yform->setValueField('text', array('city','Ort','','0'));
	$yform->setValueField('number', array('lat','LAT','10','8','','0'));
	$yform->setValueField('number', array('lng','LNG','11','8','','0'));
	$yform->setValueField('osm_geocode', array('osm','OSM','lat,lng','street,postalcode,city','500'));

	$yform->setActionField('tpl2email', array('emailtemplate', 'emaillabel', 'email@domain.de'));
	echo $yform->getForm();

?>

```

Massencodierung
-------

Die Massencodierung wird im yform Reiter Geo OSM eingestellt und gestartet. Auf der Einstellungsseite erscheinen Tabellen zur Auswahl, die ein Geocode Feld aus yform_geo_osm haben. Für die Codierung ist ein Key von Geoapify notwendig. Der Key wird in das entsprechende Feld eingetragen, dann kann es los gehen. Bitte die Lizenzbestimmungen zu Geoapify beachten. Die Massencodierung berücksichtigt die Einstellungen des yform_geo_osm-Feldes. Es werden jeweils Adresshäppchen à 200 Adressen codiert. Es werden nur solche Adressen codiert, die noch keinen Geocode Eintrag haben.



Changelog
-------

### Version 1.2.3 // 17.08.2020 

* Massencodierung über Geoapify hinzugefügt (dtpop)

### Version 1.2.2 // 24.04.2020

### Version 1.2.1 // 10.03.2020 

* Anpassung Einbindung Assets + JS Code im Frontend (OUTPUT_FILTER)

### Version 1.2.0 // 09.03.2020 

* Bugfix #6
* Anpassungen für Verwendung im Frontend
* Beispielcode "YForm Frontend"

### Version 1.1.2 // 14.02.2019

* Versionsabhängikeit für YForm 3 korrigiert @skerbis

### Version 1.1.1 // 15.07.2018

* Deutschen Tile-Server eingebunden @skerbis

### Version 1.1 // 11.03.2017

* Umstellung auf eigenständiges AddOn
* Bugfix "uninstall"

### Version 1.0 // 28.01.2017

* Initial release

Credits
-------

* Polarpixel - Peter Bickel (Testing / Ideen)
* Wolfgang Bund - Massencodierung
* Leaflet
* Openstreetmaps
* Mapbox
