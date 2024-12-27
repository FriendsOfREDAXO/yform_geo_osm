# YForm Erweiterung: Geo (OSM)

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/yform_geo_osm/master/assets/preview.png)

* YForm Erweiterung für die Einbindung einer Geocoding-Funktion basierend auf Openstreetmaps
* Anpassung der Geo-Daten über Map-Marker möglich
* Live-Suche für Adressen mit Vorschlägen
* Browser-Standortbestimmung möglich
* Openstreetmaps (Karte), optional Mapbox (Karte + Satellit)
* PHP Klasse "geo_search" für:
  * Einzelne Adressabfragen
  * Postleitzahlbasierte Umkreissuche
  * Massengeokodierung von Adressen auch außerhalb von YForm

## Installation

* Paket herunterladen oder über den Installer installieren
* Optional: Mapbox Token für zusätzliche Kartenlayer
* Optional: Geoapify API Key für erweiterte Geocoding-Funktionen

## Features

### YForm Geo Field

* Interaktive Kartenansicht mit Marker
* Live-Adresssuche mit Vorschlägen
* Direkte Übernahme des Browser-Standorts
* Automatische Koordinaten-Ermittlung aus Adressfeldern
* Dark Mode Support
* Responsive Design

### Geocoding-Funktionen

Die geo_search Klasse bietet verschiedene Möglichkeiten der Geocodierung:

#### 1. Einzelne Adressabfrage
```php
// Geocoder initialisieren
$geocoder = geo_search::forGeocoding('optional-api-key');

// Variante 1: Mit einzelnen Feldern
$coords = $geocoder->geocodeAddress('Musterstr. 1', 'Berlin', '10115');

// Variante 2: Mit kompletter Adresse
$coords = $geocoder->geocode('Musterstr. 1, 10115 Berlin');

if ($coords) {
    echo "Latitude: {$coords['lat']}, Longitude: {$coords['lng']}";
}
```

#### 2. Massengeokodierung
```php
// Geocoder für Massenverarbeitung initialisieren
$geocoder = geo_search::forBulkGeocoding(
    'rex_my_addresses',           // Tabellenname
    ['street', 'zip', 'city'],    // Adressfelder
    'latitude',                   // Feld für Breitengrad
    'longitude',                  // Feld für Längengrad
    'your-geoapify-api-key',      // Optional: API Key
    200                          // Optional: Batch-Größe
);

// Batch verarbeiten
$result = $geocoder->processBatch();
printf(
    "Verarbeitet: %d, Erfolgreich: %d, Fehlgeschlagen: %d",
    $result['total'],
    $result['success'],
    $result['failed']
);
```

#### 3. PLZ-Umkreissuche
```php
// Geocoder für PLZ-Suche initialisieren
$geo = new geo_search(
    [
        'table' => 'rex_plz_data',
        'lat_field' => 'lat',
        'lng_field' => 'lng',
        'postalcode_field' => 'plz'
    ],
    [
        'table' => 'rex_my_addresses',
        'lat_field' => 'latitude',
        'lng_field' => 'longitude'
    ]
);

// Suche nach Adressen im Umkreis von 50km um PLZ 12345
$results = $geo->searchByPostalcode('12345', 50);
```

### YForm Integration

#### Beispielmodul für YForm Frontend
```php
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

echo $yform->getForm();
```

### Massengeokodierung in YForm

Die Massengeokodierung wird im YForm Reiter "Geo OSM" eingestellt:

1. Tabelle mit Geocode-Feld auswählen
2. Optional: Geoapify API Key eintragen 
3. Geocodierung starten
4. Verarbeitung erfolgt in 200er Batches

## API-Nutzung

### Nominatim
- Standardmäßig wird Nominatim verwendet
- Kostenlos, aber mit Nutzungsbeschränkungen
- Rate Limiting beachten

### Geoapify
- Optional für erweiterte Funktionen
- API Key erforderlich
- Höhere Limits möglich
- Bessere Treffergenauigkeit

## Changelog

### Version 1.4.0 // 27.12.2024

* Neue Funktionen für einzelne Adressabfragen
* Verbesserte geo_search Klasse mit drei Betriebsmodi:
  * Einzelabfrage
  * Massengeokodierung
  * PLZ-Umkreissuche
* Erweiterte Dokumentation und Beispiele

### Version 1.3.0 // 27.12.2024

* Live-Suche mit Adressvorschlägen
* Browser-Standortbestimmung
* Verbesserte Such-UI mit Modal-Dialog
* Font Awesome 6 Icons
* Dark Mode Support
* Sprachdateien aktualisiert (DE/EN)

### Version 1.2.3 // 17.08.2020 

* Massencodierung über Geoapify hinzugefügt (dtpop)

[...vorherige Versionen...]

## Credits

* Polarpixel - Peter Bickel (Testing / Ideen)
* Wolfgang Bund - Massencodierung
* Leaflet
* Openstreetmaps
* Mapbox
* Geoapify

## Support & Lizenz

* MIT Lizenz
* Support über GitHub Issues
* Beiträge willkommen
