# YForm Erweiterung: Geo (OSM)

![Screenshot](https://github.com/FriendsOfREDAXO/yform_geo_osm/blob/assets/screen.png?raw=true)

* YForm-Erweiterung, um eine Geocoding-Funktion einzubinden. Basierend auf OpenStreetMap
* Anpassung der Geodaten über Map-Marker möglich
* Live-Suche für Adressen mit Vorschlägen
* Browser-Standortbestimmung
* OpenStreetMap (Straßenkarte), optional Mapbox (Straßenkarte + Satellitenbilder)
* PHP-Klasse `Search` für:
  * Einzelne Adressabfragen
  * Umkreissuche basierend auf Postleitzahlen
  * Batch-Geocodierung von Adressen auch außerhalb von YForm

## Installation

* Paket herunterladen oder über den Installer installieren
* Optional: Mapbox-Token für zusätzliche Kartenebenen (Layer)
* Optional: Geoapify API-Key für erweiterte Geocoding-Funktionen

> Hinweis: `yform_geo_osm` kann an den entsprechenden Tabellen 2 Felder für die Koordinaten `lat` und `lng` verwenden. Die optimalen Felder sind ein YForm-Number-Feld `DECIMAL(10,8)` für Latitude und `DECIMAL(11,8)` für Longitude. Oder verwende <https://github.com/alexplusde/yform_field> für zwei vorgefertigte Felder `number_lat` und `number_lng`.

## Features

### YForm-Feld `osm_geocode`

* Interaktive Kartenansicht mit Marker
* Live-Adresssuche mit Vorschlägen
* Direkte Übernahme des Browser-Standorts
* Automatische Koordinaten-Ermittlung aus Adressfeldern
* Dark-Mode unterstützt
* Responsive-Design

### Geocodierungs-Funktionen

Die `Search`-Klasse bietet verschiedene Möglichkeiten der Geocodierung:

> Hinweis: wenn man anstelle des API-Keys `config` schreibt, wird der API-Key der `config` übernommen.

#### 1. Abfrage einer einzelnen Adresse

```php

namespace FriendsOfREDAXO\YFormGeoOSM;

// Geocoder initialisieren
$geocoder = Search::forGeocoding('optional-api-key');

// Variante 1: Mit einzelnen Feldern
$coords = $geocoder->geocodeAddress('Musterstr. 1', 'Berlin', '10115');

// Variante 2: Mit kompletter Adresse
$coords = $geocoder->geocode('Musterstr. 1, 10115 Berlin');

if ($coords) {
    echo "Latitude: {$coords['lat']}, Longitude: {$coords['lng']}";
}
```

#### 2. Batch-Geokodierung

> Hinweis: dies setzt voraus, dass es zwei getrennte Felder für Längen- und Breitengrade gibt. Mit dem ebenfalls möglichen kombinierten Feld ist diese Funktion derzeit nicht möglich.

```php
use FriendsOfRedaxo\YFormGeoOsm\Search;
// Geocoder für Massenverarbeitung initialisieren
$geocoder = Search::forBulkGeocoding(
    'rex_my_addresses',           // Tabellenname
    'street,zip,city',            // Adressfelder: String oder Array, z.B.: ['street', 'zip', 'city']
    'latitude',                   // Feld für Breitengrad
    'longitude',                  // Feld für Längengrad
    'your-geoapify-api-key',      // Optional: API Key
    200                           // Optional: Batch-Größe
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

> Hinweis: dies setzt voraus, dass es zwei getrennte Felder für Längen- und Breitengrade gibt. Mit dem ebenfalls möglichen kombinierten Feld ist diese Funktion derzeit nicht möglich.

```php
use FriendsOfRedaxo\YFormGeoOsm\Search;
// Geocoder für PLZ-Suche initialisieren
$geo = new Search(
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

### YForm-Integration

#### Beispielmodul für YForm Frontend

```php
rex_extension::register('OUTPUT_FILTER', FriendsOfRedaxo\YFormGeoOSM\Assets::addAssets(...));

$yform = new rex_yform();
$yform->setObjectparams('form_name', 'table-rex_geotest');
$yform->setObjectparams('form_action',rex_getUrl('REX_ARTICLE_ID'));
$yform->setObjectparams('form_ytemplate', 'bootstrap');
$yform->setObjectparams('form_showformafterupdate', 0);
$yform->setObjectparams('real_field_names', true);

$yform->setValueField('text', ['street','Straße','','0']);
$yform->setValueField('text', ['postalcode','PLZ','','0']);
$yform->setValueField('text', ['city','Ort','','0']);
$yform->setValueField('number', ['lat','LAT','10','8','','0','input:text']);
$yform->setValueField('number', ['lng','LNG','11','8','','0','input:text']);
$yform->setValueField('osm_geocode', ['osm','OSM','lat,lng','street,postalcode,city','500','','','0']);

echo $yform->getForm();
```

Die Koordinaten können entweder in den Einzelfeldern (`lat`, `lng`) oder im `osm_geocode`-Feld (`osm`) gespeichert
werden. Dazu wird für den jeweils nicht benötigen Teil "Nicht in Datenbank speichern" festgelegt. Da im `osm_geocode`-Feld per Default keine Daten abgelegt werden, sondern in den Einzelfeldern, sollte wie im Beispiel 1 gezeigt das Feld gar nicht erst in der Datenbank angelegt werden.

*Beispiel 1: Koordinaten als Einzelwerte `lat` bzw. `lng` speichern*

```php
$yform->setValueField('number', ['lat','LAT','10','8','','0','input:text']);
$yform->setValueField('number', ['lng','LNG','11','8','','0','input:text']);
$yform->setValueField('osm_geocode', ['osm','OSM','lat,lng','street,postalcode,city','500','','','1']);
```

*Beispiel 2: Koordinaten als Kombiwert (`lat,lng`) in `osm` speichern*

```php
$yform->setValueField('number', ['lat','LAT','10','8','','1','input:text']);
$yform->setValueField('number', ['lng','LNG','11','8','','1','input:text']);
$yform->setValueField('osm_geocode', ['osm','OSM','lat,lng','street,postalcode,city','500','','','0']);
```

### Batch-Geokodierung in YForm

Die Batch-Geokodierung wird im YForm Reiter "Geo OSM" eingestellt:

1. Tabelle mit Geocode-Feld auswählen
2. Optional: Geoapify-API-Key eintragen
3. Geocodierung starten
4. Verarbeitung erfolgt in 200er-Schritten

## API-Nutzung

### Dienst: Nominatim

*-* Standardmäßig wird Nominatim verwendet

* Kostenlos, aber mit Nutzungsbeschränkungen
* Rate Limit beachten

### Dienst: Geoapify

* Optional für erweiterte Funktionen
* API-Key erforderlich
* Höhere Limits möglich
* Bessere Genauigkeit

## Geopicker für Module / Add-ons Beispiel

In diesem Fall werden die Koordinaten in einem Feld gespeichert. Das Feld muss die CSS-Klasse `rex-coords` besitzen.

```html
<div class="form-group">
    <label>Location 1</label>
    <input type="text" 
           name="REX_INPUT_VALUE[1]" 
           value="REX_VALUE[1]" 
           class="form-control rex-coords"
           readonly>
</div>

<div class="form-group">
    <label>Location 2</label>
    <input type="text" 
           name="REX_INPUT_VALUE[2]" 
           value="REX_VALUE[2]" 
           class="form-control rex-coords"
           readonly>
</div>
```

## Gestaltung der Karte

Die Karte kann mittels JSON-Attriebute gestaltet und ein wenig konfiguriert werden. 

> Hinweis: die ID sollte besser nicht verändert werden, da diese durch die Skripte benötigt wird. Selbstverständlich könnte man sie so fixieren. Schreibweise `map-xx`


```json
{
    "style": "height: 400px",
    "class": "my_class",
    "data-max-zoom": "12",
    "data-min-zoom": "2"
}
```

## Lizenz

MIT-Lizenz, siehe [LICENSE](LICENSE)

## Autoren

**Friends Of REDAXO**

* <http://www.redaxo.org>
* <https://github.com/FriendsOfREDAXO>

**Project Lead**

[Alexander Walther](https://github.com/alxndr-w)

**Weitere Autoren (2.x)** 

* [Thomas Skerbis](https://github.com/skerbis)
* [Christoph Böcker](https://github.com/christophboecker)

**Weitere Credits (1.x)**

* Polarpixel – Peter Bickel (Testing / Ideen)
* Wolfgang Bund – Massencodierung
* Leaflet
* OpenStreetMap
* Mapbox
* Geoapify
