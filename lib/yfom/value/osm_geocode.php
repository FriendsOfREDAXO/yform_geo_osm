<?php

use rex_functional_exception;
use rex_i18n;
use rex_yform_value_abstract;

use function sprintf;

/**
 * YForm value field for OpenStreetMap integration.
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
                throw new rex_functional_exception(rex_i18n::msg('osm_geocode_config_error', $this->getName()));
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
        return rex_i18n::msg('osm_geocode_description');
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'osm_geocode',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('osm_geocode_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('osm_geocode_label')],
                'latlng' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('osm_geocode_latlng_label'),
                    'notice' => rex_i18n::msg('osm_geocode_latlng_notice')
                ],
                'address' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('osm_geocode_address_label'),
                    'notice' => rex_i18n::msg('osm_geocode_address_notice')
                ],
                'height' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('osm_geocode_height_label'),
                    'notice' => rex_i18n::msg('osm_geocode_height_notice')
                ],
                'map_attributes' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('osm_geocode_map_attributes_label'),
                    'notice' => rex_i18n::msg('osm_geocode_map_attributes_notice')
                ],
                'mapbox_token' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('osm_geocode_mapbox_token_label'),
                    'notice' => rex_i18n::msg('osm_geocode_mapbox_token_notice')
                ],
                'no_db' => ['type' => 'no_db', 'default' => 0]
            ],
            'validates' => [
                ['customfunction' => ['name' => 'latlng', 'function' => $this->validateLatLng(...)]],
                ['customfunction' => ['name' => 'address', 'function' => $this->validateAddress(...)]],
                ['customfunction' => ['name' => 'no_db', 'function' => $this->validateNoDb(...)]],
                ['customfunction' => ['name' => ['height', 'map_attributes'], 'function' => $this->validateLayout(...)]]
            ],
            'description' => rex_i18n::msg('osm_geocode_description_full'),
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
                rex_i18n::msg('osm_geocode_validate_latlng_count')
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
                sprintf(rex_i18n::msg('osm_geocode_validate_latlng_unknown'), implode('», «', $unknown_fields))
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
               sprintf(rex_i18n::msg('osm_geocode_validate_address_unknown'), implode('», «', $unknown_fields))
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
                     rex_i18n::msg(
                         'osm_geocode_validate_nodb_conflict',
                         rex_i18n::msg('yform_donotsaveindb')
                     ),
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
                         rex_i18n::msg('osm_geocode_validate_map_attributes_invalid')
                    );
                    return true;
                }
            } catch (\JsonException $e) {
                $self->setElement(
                    'message',
                    sprintf(rex_i18n::msg('osm_geocode_validate_map_attributes_json_error'), $e->getMessage())
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
                 sprintf(rex_i18n::msg('osm_geocode_validate_height_invalid'), $height)
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
