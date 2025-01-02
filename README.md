# YForm Geo OSM

Dieses Addon erweitert YForm um ein Geo-Feld für OpenStreetMap zur Koordinatenauswahl.

![Screenshot](https://github.com/FriendsOfREDAXO/yform_geo_osm/assets/screenshots/yform_geo_osm.jpg)

## Features

* OpenStreetMap Integration für YForm
* Adresssuche via Nominatim
* Browser-Geolocation Support
* Standortsuche mit Live-Vorschau
* Drag & Drop Marker für präzise Positionierung
* Dark Mode Support
* Optional: Mapbox Integration

## Installation

1. Im REDAXO Installer das Addon `yform_geo_osm` herunterladen
2. Addon installieren und aktivieren
3. Optional: Mapbox Token in den Einstellungen hinterlegen

## Verwendung in YForm

### 1. Geo-Feld anlegen

Das Geo-Feld kann im YForm Formbuilder oder in der Table Manager Definition hinzugefügt werden:

```php
osm_geocode|geo_position|Position|pos_lat,pos_lng|strasse,plz,ort
```

Parameter:
1. Feldtyp (`osm_geocode`)
2. Name des Feldes
3. Label
4. Koordinatenfelder (Latitude,Longitude)
5. Adressfelder für die Koordinatenermittlung (optional)
6. Format als JSON (optional)
7. Mapbox Token (optional)

### 2. Koordinatenfelder anlegen

Die benötigten Koordinatenfelder müssen separat angelegt werden:

```php
number|pos_lat|Latitude||
number|pos_lng|Longitude||
```

### 3. Format-Optionen (neu in 2.0.0)

Die Kartenansicht kann über ein JSON-Objekt konfiguriert werden:

```json
{
    "style": "height: 400px",
    "data-init-lat": "52.5200",
    "data-init-lng": "13.4050",
    "data-init-zoom": "12"
}
```

Verfügbare Optionen:
* `style`: CSS-Styles für den Kartencontainer
* `class`: CSS-Klassen für den Kartencontainer
* `data-init-lat`: Initiale Latitude (Standard: 50.1109221)
* `data-init-lng`: Initiale Longitude (Standard: 8.6821267)
* `data-init-zoom`: Initiales Zoom-Level (Standard: 2)

#### Legacy Format-Option

Das Feld unterstützt aus Kompatibilitätsgründen auch noch die einfache Höhenangabe:

```php
osm_geocode|geo_position|Position|pos_lat,pos_lng|strasse,plz,ort|400
```

Diese wird aber nur verwendet, wenn keine JSON-Formatierung angegeben ist.

## Beispiele

### Einfaches Geo-Feld

```php
// Koordinatenfelder
number|pos_lat|Latitude||
number|pos_lng|Longitude||

// Geo-Feld mit Standardeinstellungen
osm_geocode|geo_position|Position|pos_lat,pos_lng
```

### Geo-Feld mit Adressverknüpfung

```php
// Adressfelder
text|strasse|Straße||
text|plz|PLZ||
text|ort|Ort||

// Koordinatenfelder
number|pos_lat|Latitude||
number|pos_lng|Longitude||

// Geo-Feld mit Adressverknüpfung
osm_geocode|geo_position|Position|pos_lat,pos_lng|strasse,plz,ort
```

### Geo-Feld mit angepasster Initialisierung

```php
// Koordinatenfelder
number|pos_lat|Latitude||
number|pos_lng|Longitude||

// Geo-Feld mit JSON-Formatierung
osm_geocode|geo_position|Position|pos_lat,pos_lng||{"style": "height: 400px", "data-init-lat": "52.5200", "data-init-lng": "13.4050", "data-init-zoom": "12"}
```

## Lizenz

MIT Lizenz, siehe [LICENSE.md](LICENSE.md)

## Autor

**Friends Of REDAXO**

* https://www.redaxo.org
* https://github.com/FriendsOfREDAXO
