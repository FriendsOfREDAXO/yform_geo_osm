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
                throw new rex_functional_exception('Konfigurationsfehler im Feld ' . $this->getName() . ': lat/lng');
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
            [$lat, $lng, $rest] = explode(',', $value . ',');
            $this->latField->setValue($lat);
            $this->lngField->setValue($lng);
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
        $addressfields = explode(',', str_replace(' ', '', $this->getElement('address')));
        $geofields = [$this->latField->getName(), $this->lngField->getName()];
        $height = (int) $this->getElement('height');
        $mapbox_token = $this->getElement('mapbox_token');

        if ($this->needsOutput()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.osm_geocode.tpl.php', compact('addressfields', 'geofields', 'height', 'mapbox_token'));
        }

        /**
         * Lat und Lng wieder zusammenfassen und speichern.
         */
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
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|height|[mapbox_token]|[no_db]';
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
                        'label' => 'Name',
                    ],
                'label' => [
                        'type' => 'text',
                        'label' => 'Bezeichnung',
                    ],
                'latlng' => [
                        'type' => 'text',
                        'label' => 'Koordinaten-Felder',
                        'notice' => 'Namen der Felder für Breitengrad/Latitude und Längengrad/Longitude; Bsp.: «pos_lat,pos_lng»',
                    ],
                'address' => [
                        'type' => 'text',
                        'label' => 'Adressen-Felder',
                        'notice' => 'Namen der Felder mit Adressen-Elementen zur Positionsfindung; Bsp.: «strasse,plz,ort»',
                    ],
                'height' => [
                        'type' => 'text',
                        'label' => 'Map-H&ouml;he',
                    ],
                'mapbox_token' => [
                        'type' => 'text',
                        'label' => 'Mapbox-Token',
                        'notice' => '(optional)',
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
            ],
            'description' => 'Openstreetmap Positionierung',
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
        /**
         * Eingabe in ein Array auflösen und formal bereinigen.
         */
        $coord_field_names = array_map(trim(...), explode(',', $value));
        $coord_field_names = array_filter($coord_field_names, strlen(...));
        $coord_field_names = array_unique($coord_field_names);

        /**
         * Fehler 1: mehr oder weniger als zwei Felder angegeben.
         */
        if (2 !== count($coord_field_names)) {
            $self->setElement(
                'message',
                'Bitte genau zwei Felder für Breiten- und Längengrade (lat, lng) angeben.'
            );
            return true;
        }

        /**
         * Liste der Feldnamen in der Tabelle abrufen und ermitteln, welches angegebene Feld
         * nicht im Formular vorkommt.
         */
        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn', 
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        /**
         * Fehler 2: unbekanntes Feld.
         */
        $unknown_fields = array_diff($coord_field_names, $field_list);
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf('Koordinaten-Feld unbekannt: «%s»', implode('», «', $unknown_fields))
            );
            return true;
        }

        /**
         * formal bereinigte Liste in das Feld zurückgeben.
         */
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
        /**
         * Eingabe in ein Array auflösen und formal bereinigen.
         */
        $address_field_names = array_map(trim(...), explode(',', $value));
        $address_field_names = array_filter($address_field_names, strlen(...));
        $address_field_names = array_unique($address_field_names);

        /**
         * Liste der Feldnamen in der Tabelle abrufen und ermitteln, welches angegebene Feld
         * nicht im Formular vorkommt.
         */
        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn',
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        /**
         * Fehler: unbekanntes Feld.
         */
        $unknown_fields = array_diff($address_field_names, $field_list);        
        if (0 < count($unknown_fields)) {
            $self->setElement(
                'message',
                sprintf('Adress-Feld unbekannt: «%s»', implode('», «', $unknown_fields))
            );
            return true;
        }

        /**
         * formal bereinigte Liste in das Feld zurückgeben.
         */
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
        /**
         * Überspringen wenn selbst speicherbar bzw. die LatLng-Überprüfung gescheitert ist.
         */
        if( $value === null || $value === 0 || !isset($this->latLngInput)) {
            return false;
        }

        /**
         * Die Lat/Lng-Felder identifizieren und auf _no_db prüfen
         * Dass es zwei sind muss hier nicht mehr überprüft werden.
         */
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

        /**
         * Fehler: Wenn es mindestens ein no_db-Koordinatenfeld gibt
         */
        if( 0 !== count($result)) {
            $self->setElement(
                'message',
                sprintf(
                    '"%s" kollidiert mit der Feldkonfiguration von «%s»; Beide Koordinatenfelder («%s») müssen speicherbar sein, wenn dieses Feld nicht speicherbar ist',
                    rex_i18n::msg('yform_donotsaveindb'),
                    implode('» bzw. «', $result),
                    implode('», «', $fields),
                )
            );
            return true;
        }
        return false;
    }
}
