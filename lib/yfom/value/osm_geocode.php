<?php

use rex_functional_exception;
use rex_i18n;
use rex_yform_value_abstract;

use function sprintf;

class rex_yform_value_osm_geocode extends rex_yform_value_abstract
{
    protected ?rex_yform_value_abstract $latField = null;
    protected ?rex_yform_value_abstract $lngField = null;
    protected bool $combinedValue = false;
    protected rex_yform_value_abstract $latLngInput;

    /**
     * Die Hilfsfelder im Formular für Lat/Lng identifizieren.
     * Falls es reine Hilfsfelder sind (no_db) werden die Felder
     * beim ersten Aufruf vorbefüllt mit den Daten aus diesem Feld.
     *
     * @api
     */
    public function preValidateAction(): void
    {
        /**
         * Brauchen wir so oder so: die Referenz auf die Lat/Lng-Felder.
         */
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
                throw new rex_functional_exception(rex_i18n::msg('yform_geo_osm_error_coordinates'));
            }
        }

        /**
         * Wenn die Felder nicht selbst in der DB gespeichert werden (no_db), erhalten Sie den Wert
         * aus diesem Feld vorbelegt.
         */
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
     * HTML für das Feld generieren
     * Daten ggf. speichern
     * 
     * @api
     */
    public function enterObject(): void
    {
        $fields = array_filter(explode(',', $this->getElement('address')),strlen(...));
        $addressfields = [];
        /** @var rex_yform_value_abstract $val */
        foreach( $this->getParam('values') as $val) {
            if(in_array($val->getName(),$fields,true)) {
                $addressfields[$val->getName()] = $val;
            }
        }
        $geofields = [$this->latField, $this->lngField];

        $format = $this->getElement('format');
        $formatData = [];
        $height = $this->getElement('height');
        
        if (!empty($format)) {
            try {
                $formatData = json_decode($format, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($formatData)) {
                    throw new \Exception('Invalid JSON format');
                }
                // Height wird ignoriert wenn formatData gesetzt ist
                $height = '';
            } catch (\Exception $e) {
                throw new rex_functional_exception(rex_i18n::msg('yform_geo_osm_error_format'));
            }
        } else {
            // Fallback: Wenn keine formatData, dann height verwenden
            if ($height !== '' && is_numeric($height)) {
                $height = $height . 'px';
            }
        }

        // ID kann nicht überschrieben werden
        if (isset($formatData['id'])) {
            unset($formatData['id']);
        }

        $mapbox_token = $this->getElement('mapbox_token');
        $This = $this;

        if ($this->needsOutput()) {
            $this->params['form_output'][$this->getId()] = $this->parse(
                'value.osm_geocode.tpl.php', 
                compact('addressfields', 'geofields', 'formatData', 'mapbox_token', 'height')
            );
        }

        $this->setValue(sprintf('%s,%s', $this->latField->getValue(), $this->lngField->getValue()));

        $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
        if ($this->saveInDB()) {
            $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
        }
    }

    /**
     * @api
     */
    public function getDescription(): string
    {
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|{"class":"wide","data-init-lat":"48.8566"}|[height]|[mapbox_token]|[no_db]';
    }

    /**
     * @api
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'osm_geocode',
            'values' => [
                'name' => [
                        'type' => 'name',
                        'label' => rex_i18n::msg('yform_geo_osm_label_name'),
                    ],
                'label' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_label'),
                    ],
                'latlng' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_coord_fields'),
                        'notice' => rex_i18n::msg('yform_geo_osm_notice_coord_fields'),
                    ],
                'address' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_address_fields'),
                        'notice' => rex_i18n::msg('yform_geo_osm_notice_address_fields'),
                    ],
                'format' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_format'),
                        'notice' => rex_i18n::msg('yform_geo_osm_notice_format'),
                    ],
                'height' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_height'),
                        'notice' => rex_i18n::msg('yform_geo_osm_notice_height'),
                    ],
                'mapbox_token' => [
                        'type' => 'text',
                        'label' => rex_i18n::msg('yform_geo_osm_label_mapbox_token'),
                        'notice' => rex_i18n::msg('yform_geo_osm_notice_mapbox_token'),
                    ],
                'no_db' => [
                        'type' => 'no_db',
                        'default' => 0,
                    ],
            ],
            'validates' => [
                ['customfunction' => ['name' => 'latlng', 'function' => $this->validateLatLng(...)]],
                ['customfunction' => ['name' => 'address', 'function' => $this->validateAddress(...)]],
                ['customfunction' => ['name' => 'no_db', 'function' => $this->validateNoDb(...)]],
                ['customfunction' => ['name' => 'format', 'function' => $this->validateFormat(...)]],
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
     * Wenn ja: schon mal versehentlich eingegebene Leerzeichen entfernen.
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateLatLng(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $coord_field_names = array_map(trim(...), explode(',', $value));
        $coord_field_names = array_filter($coord_field_names, strlen(...));
        $coord_field_names = array_unique($coord_field_names);

        if (2 !== count($coord_field_names)) {
            $self->setElement('message', rex_i18n::msg('yform_geo_osm_error_coordinates'));
            return true;
        }

        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn', 
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        $unknown_fields = array_diff($coord_field_names, $field_list);
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf(rex_i18n::msg('yform_geo_osm_error_unknown_field'), implode('», «', $unknown_fields))
            );
            return true;
        }

        $fields[$field_name]->setValue(implode(',', $coord_field_names));
        $this->latLngInput = $fields[$field_name];
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die angegebenen Felder für Adress-Teile existieren.
     * Wenn nein: Fehlermeldung.
     * Wenn ja: schon mal versehentlich eingegebene Leerzeichen entfernen.
     * 
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateAddress(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $address_field_names = array_map(trim(...), explode(',', $value));
        $address_field_names = array_filter($address_field_names, strlen(...));
        $address_field_names = array_unique($address_field_names);

        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn',
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        $unknown_fields = array_diff($address_field_names, $field_list);        
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf(rex_i18n::msg('yform_geo_osm_error_address_field'), implode('», «', $unknown_fields))
            );
            return true;
        }

        $fields[$field_name]->setValue(implode(',', $address_field_names));
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob die Angaben zu no_db hier und in den latlng-Felder korrelieren.
     * Wenn nein: Fehlermeldung.
     * 
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateNoDb(string $field_name, ?int $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        if ($value === null || $value === 0 || !isset($this->latLngInput)) {
            return false;
        }

        $fields = explode(',',$this->latLngInput->getValue());
        $sql = rex_sql::factory();
        $result = $sql->getArray(
            'SELECT id,name FROM rex_yform_field WHERE table_name = :tn AND type_id = :ti AND (name = :lat OR name = :lng) AND no_db = :no',
            [
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
                ':ti' => 'value',
                ':lat' => $fields[0],
                ':lng' => $fields[1],
                ':no' => 1,
            ],
            PDO::FETCH_KEY_PAIR,
        );

        if (0 !== count($result)) {
            $self->setElement(
                'message',
                sprintf(
                    rex_i18n::msg('yform_geo_osm_error_no_db_conflict'),
                    rex_i18n::msg('yform_donotsaveindb'),
                    implode('» bzw. «', $result),
                    implode('», «', $fields),
                )
            );
            return true;
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration
     * 
     * Überprüft, ob das Format-Feld ein gültiges JSON enthält
     * 
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateFormat(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        if (empty(trim($value))) {
            return false;
        }

        try {
            $formatData = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($formatData)) {
                throw new \Exception('Invalid JSON format');
            }
        } catch (\Exception $e) {
            $self->setElement('message', rex_i18n::msg('yform_geo_osm_error_format'));
            return true;
        }

        return false;
    }
}
