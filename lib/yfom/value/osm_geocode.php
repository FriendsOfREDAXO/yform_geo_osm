<?php

namespace FriendsOfRedaxo\YFormGeoOsm;

use rex_functional_exception;
use rex_i18n;
use rex_yform_value_abstract;

use function sprintf;

/**
 * YForm value field for OpenStreetMap integration.
 * 
 * This class provides a map field for YForm with geocoding capabilities.
 * It allows to select coordinates via map interface and can handle both
 * separate lat/lng fields and combined coordinate storage.
 * 
 * @package redaxo\yform\geo-osm
 * @author Friends Of REDAXO
 */
class rex_yform_value_osm_geocode extends rex_yform_value_abstract
{
    /**
     * Reference to the latitude field
     * @var rex_yform_value_abstract|null
     */
    protected ?rex_yform_value_abstract $latField = null;

    /**
     * Reference to the longitude field
     * @var rex_yform_value_abstract|null
     */
    protected ?rex_yform_value_abstract $lngField = null;

    /**
     * Whether to store coordinates as combined value
     * @var bool
     */
    protected bool $combinedValue = false;

    /**
     * Reference to the input field for validation
     * @var rex_yform_value_abstract|null
     */
    protected ?rex_yform_value_abstract $latLngInput = null;

    /**
     * Identifies and initializes the helper fields for Lat/Lng in the form.
     */
    public function preValidateAction(): void
    {
        if (null === $this->latField || null === $this->lngField) {
            $geofields = explode(',', str_replace(' ', '', $this->getElement('latlng')));
            foreach ($this->params['values'] as $val) {
                if ($val->getName() === $geofields[0]) {
                    $this->latField = $val;
                    $this->combinedValue = $this->combinedValue || !$val->saveInDB();
                    continue;
                }
                if ($val->getName() === $geofields[1]) {
                    $this->lngField = $val;
                    $this->combinedValue = $this->combinedValue || !$val->saveInDB();
                }
            }
            if (null === $this->latField || null === $this->lngField) {
                throw new rex_functional_exception('Konfigurationsfehler im Feld ' . $this->getName() . ': lat/lng');
            }
        }

        if (1 !== $this->params['send'] && $this->combinedValue) {
            $value = $this->getValue();
            if (null === $value) {
                $value = ',';
            }
            $latLng = array_merge(explode(',', $value), ['','']);
            $this->latField->setValue($latLng[0]);
            $this->lngField->setValue($latLng[1]);
        }
    }

    /**
     * Generates HTML for the field and saves data if necessary.
     */
    public function enterObject(): void
    {
        $fields = array_filter(explode(',', $this->getElement('address')), strlen(...));
        $addressfields = [];
        foreach ($this->params['values'] as $val) {
            if (in_array($val->getName(), $fields, true)) {
                $addressfields[$val->getName()] = $val;
            }
        }
        $geofields = [$this->latField, $this->lngField];

        // Parse map attributes
        $mapAttributes = [];
        $mapAttributesJson = $this->getElement('map_attributes');
        
        if ($mapAttributesJson) {
            try {
                $mapAttributes = json_decode($mapAttributesJson, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($mapAttributes)) {
                    $mapAttributes = [];
                }
            } catch (\JsonException $e) {
                // Invalid JSON, fallback to empty array
                $mapAttributes = [];
            }
        }

        // Legacy support for height
        $height = $this->getElement('height');
        if (empty($mapAttributes) && $height) {
            $height = is_numeric($height) ? $height . 'px' : $height;
            $mapAttributes['style'] = 'height: ' . $height;
        }

        $mapbox_token = $this->getElement('mapbox_token');

        if ($this->needsOutput()) {
            $this->params['form_output'][$this->getId()] = $this->parse(
                'value.osm_geocode.tpl.php',
                compact('addressfields', 'geofields', 'mapAttributes', 'mapbox_token')
            );
        }

        $this->setValue(sprintf('%s,%s', $this->latField->getValue(), $this->lngField->getValue()));

        $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
        if ($this->saveInDB()) {
            $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
        }
    }
    public function getDescription(): string
    {
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|height|[mapbox_token]|[no_db]|[map_attributes]';
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'osm_geocode',
            'values' => [
                'name' => ['type' => 'name', 'label' => 'Name'],
                'label' => ['type' => 'text', 'label' => 'Bezeichnung'],
                'latlng' => [
                    'type' => 'text',
                    'label' => 'Koordinaten-Felder',
                    'notice' => 'Namen der Felder für Breitengrad/Latitude und Längengrad/Longitude; Bsp.: «pos_lat,pos_lng»'
                ],
                'address' => [
                    'type' => 'text',
                    'label' => 'Adressen-Felder',
                    'notice' => 'Namen der Felder mit Adressen-Elementen zur Positionsfindung; Bsp.: «strasse,plz,ort»'
                ],
                'height' => [
                    'type' => 'text',
                    'label' => 'Map-H&ouml;he',
                    'notice' => 'Angabe als Integer-Zahl ggf, mit Masseinheit px(defaut) | em | rem | vh. Wird ignoriert wenn map_attributes gesetzt sind.'
                ],
                'map_attributes' => [
                    'type' => 'text',
                    'label' => 'Map Attribute (JSON)',
                    'notice' => 'JSON-Objekt mit HTML-Attributen für die Karte, z.B.: {"style": "height: 400px", "data-max-zoom": "12"}'
                ],
                'mapbox_token' => [
                    'type' => 'text',
                    'label' => 'Mapbox-Token',
                    'notice' => '(optional)'
                ],
                'no_db' => ['type' => 'no_db', 'default' => 0]
            ],
            'validates' => [
                ['customfunction' => ['name' => 'latlng', 'function' => $this->validateLatLng(...)]],
                ['customfunction' => ['name' => 'address', 'function' => $this->validateAddress(...)]],
                ['customfunction' => ['name' => 'no_db', 'function' => $this->validateNoDb(...)]],
                ['customfunction' => ['name' => ['height', 'map_attributes'], 'function' => $this->validateLayout(...)]]
            ],
            'description' => '🧩 yform_geo_osm: OpenStreetMap-Karte und Geoocodierung',
            'dbtype' => 'varchar(191)',
            'formbuilder' => false,
            'multi_edit' => false,
        ];
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die angegebenen Felder für LAT/LNG existieren.
     * Wenn nein: Fehlermeldung.
     * Wenn ungleich 2 Felder: Fehlermeldung
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateLatLng(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Eingabe in ein Array auflösen und formal bereinigen
        $coord_field_names = array_map(trim(...), explode(',', $value));
        $coord_field_names = array_filter($coord_field_names, strlen(...));
        $coord_field_names = array_unique($coord_field_names);

        // Fehler 1: mehr oder weniger als zwei Felder angegeben
        if (2 !== count($coord_field_names)) {
            $self->setElement(
                'message',
                'Bitte genau zwei Felder für Breiten- und Längengrade (lat, lng) angeben.'
            );
            return true;
        }

        // Liste der Feldnamen in der Tabelle abrufen
        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn', 
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        // Fehler 2: unbekanntes Feld
        $unknown_fields = array_diff($coord_field_names, $field_list);
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf('Koordinaten-Feld unbekannt: «%s»', implode('», «', $unknown_fields))
            );
            return true;
        }

        // formal bereinigte Liste in das Feld zurückgeben
        $fields[$field_name]->setValue(implode(',', $coord_field_names));
        $this->latLngInput = $fields[$field_name];
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die angegebenen Felder für Adress-Teile existieren.
     * 
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateAddress(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        if(empty(trim($value))) {
            return false;
        }

        // Eingabe in ein Array auflösen und formal bereinigen
        $address_field_names = array_map(trim(...), explode(',', $value));
        $address_field_names = array_filter($address_field_names, strlen(...));
        $address_field_names = array_unique($address_field_names);

        // Liste der Feldnamen in der Tabelle abrufen
        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn',
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        // Fehler: unbekanntes Feld
        $unknown_fields = array_diff($address_field_names, $field_list);        
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf('Adress-Feld unbekannt: «%s»', implode('», «', $unknown_fields))
            );
            return true;
        }

        // formal bereinigte Liste in das Feld zurückgeben
        $fields[$field_name]->setValue(implode(',', $address_field_names));
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die Angaben zu no_db hier und in den latlng-Felder korrelieren.
     * 
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateNoDb(string $field_name, ?int $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Überspringen wenn selbst speicherbar
        if ($value === null || $value === 0 || !isset($this->latLngInput)) {
            return false;
        }

        // Die Lat/Lng-Felder identifizieren und auf _no_db prüfen
        $coord_fields = array_map(trim(...), explode(',', $this->latLngInput->getValue()));
        $sql = rex_sql::factory();
        $result = $sql->getArray(
            'SELECT id,name FROM rex_yform_field WHERE table_name = :tn AND type_id = :ti AND (name = :lat OR name = :lng) AND no_db = :no',
            [
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
                ':ti' => 'value',
                ':lat' => $coord_fields[0],
                ':lng' => $coord_fields[1],
                ':no' => 1,
            ],
            PDO::FETCH_KEY_PAIR,
        );

        // Fehler: Wenn es mindestens ein no_db-Koordinatenfeld gibt
        if (0 !== count($result)) {
            $self->setElement(
                'message',
                sprintf(
                    '"%s" kollidiert mit der Feldkonfiguration von «%s»; Beide Koordinatenfelder («%s») müssen speicherbar sein, wenn dieses Feld nicht speicherbar ist',
                    rex_i18n::msg('yform_donotsaveindb'),
                    implode('» bzw. «', $result),
                    implode('», «', $coord_fields)
                )
            );
            return true;
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die Angaben map_attributes valid sind und die height korrekt ist.
     * - map_attributes muss valides JSON sein
     * - die Höhe (height) muss eine Integer-Zahl sein, ggf. mit einer Massangabe
     * - die Massangabe darf sein px, em, rem oder vh. px ist default
     * - wenn kein Feld gefüllt ist, wird 400px als Höhe eingetragen (default)
     *  
     * @param string[] $field_names
     * @param array<string, string> $values
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateLayout(array $field_names, array $values, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Prüfen der map_attributes, wenn vorhanden
        if (!empty($values['map_attributes'])) {
            try {
                $attributes = json_decode($values['map_attributes'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($attributes)) {
                    $self->setElement(
                        'message',
                        'Map Attribute müssen ein valides JSON-Objekt sein.'
                    );
                    return true;
                }
            } catch (\JsonException $e) {
                $self->setElement(
                    'message',
                    'Ungültiges JSON Format für Map Attribute: ' . $e->getMessage()
                );
                return true;
            }
            return false;
        }

        // Prüfen der height, wenn map_attributes nicht gesetzt ist
        $height = $values['height'] ?? '';
        if (empty($height)) {
            $fields['height']->setValue('400px');
            return false;
        }

        // Die Höhe muss ein Integer-Wert sein oder ein Int-Wert mit Masseinheit
        $ok = preg_match('@^(?<height>[1-9]\d*)\s*(?<unit>px|em|rem|vh)?$@', $height, $match);
        if (0 === $ok) {
            $self->setElement(
                'message',
                sprintf(
                    'Ungültige Höhenangabe «%s»; bitte nur eine Integer-Zahl sowie eine der zulässigen Masseinheiten eingeben',
                    $height
                )
            );
            return true;
        }

        $match = array_merge(
            ['height' => '', 'unit' => 'px'],
            $match
        );
        $fields['height']->setValue(sprintf('%d%s', $match['height'], $match['unit']));
        return false;
    }
}
