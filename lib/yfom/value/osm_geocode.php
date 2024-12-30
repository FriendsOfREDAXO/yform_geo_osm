<?php

namespace FriendsOfRedaxo\YFormGeoOsm;

use rex_functional_exception;
use rex_i18n;
use rex_yform_value_abstract;

use function sprintf;

class rex_yform_value_osm_geocode extends rex_yform_value_abstract
{
    protected ?rex_yform_value_abstract $latField = null;
    protected ?rex_yform_value_abstract $lngField = null;
    protected bool $combinedValue = false;

    /**
     * Die Hilfsfelder im Formular für Lat/Lng identifizieren.
     * Falls es reine Hilfsfelder sind (nicht in der DB speichern)
     * werden ggf. sie aus diesem Feld initialisiert.
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
         * Wenn die Felder nicht selbst in der DB gespeichert werden, erhalten Sie den Wert
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
                'name' => ['type' => 'name', 'label' => 'Name'],
                'label' => ['type' => 'text', 'label' => 'Bezeichnung'],
                'latlng' => ['type' => 'text', 'label' => 'Feldnamen LAT/LNG (Bsp. pos_lat,pos_lng)'],
                'address' => ['type' => 'text', 'label' => 'Feldnamen Positionsfindung (Bsp. strasse,plz,ort)'],
                'height' => ['type' => 'text', 'label' => 'Map-H&ouml;he'],
                'mapbox_token' => ['type' => 'text', 'label' => 'Mapbox Token (optional)'],
                'no_db' => ['type' => 'no_db',   'label' => rex_i18n::msg('yform_values_defaults_table'),  'default' => 0],
            ],
            'description' => 'Openstreetmap Positionierung',
            'dbtype' => 'varchar(191)',
            'formbuilder' => false,
            'multi_edit' => false,
        ];
    }
}
