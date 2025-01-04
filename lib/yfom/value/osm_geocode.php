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

    public function enterObject(): void
    {
        $fields = array_filter(explode(',', $this->getElement('address')),strlen(...));
        $addressfields = [];
        foreach( $this->getParam('values') as $val) {
            if(in_array($val->getName(),$fields,true)) {
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

        // Legacy support for height and format
        $height = $this->getElement('height');
        $mapclass = $this->getElement('format');
        
        // Only apply legacy attributes if no new attributes are set
        if (empty($mapAttributes)) {
            if ($mapclass) {
                $mapAttributes['class'] = $mapclass;
            } elseif ($height) {
                $height = is_numeric($height) ? $height . 'px' : $height;
                $mapAttributes['style'] = 'height: ' . $height;
            }
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
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|height|class|[mapbox_token]|[no_db]|[map_attributes]';
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
                    'label' => 'Map-H&ouml;he (Legacy)',
                    'notice' => 'Deprecated: Bitte map_attributes verwenden'
                ],
                'format' => [
                    'type' => 'text',
                    'label' => 'CSS-Klasse (Legacy)',
                    'notice' => 'Deprecated: Bitte map_attributes verwenden'
                ],
                'map_attributes' => [
                    'type' => 'text',
                    'label' => 'Map Attribute (JSON)',
                    'notice' => 'JSON-Objekt mit HTML-Attributen für die Karte, z.B.: {"style": "height: 400px", "class": "my-map", "data-max-zoom": "12"}'
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
                ['customfunction' => ['name' => ['height','format','map_attributes'], 'function' => $this->validateLayout(...)]],
            ],
            'description' => '🧩 yform_geo_osm: OpenStreetMap-Karte und Geoocodierung',
            'dbtype' => 'varchar(191)',
            'formbuilder' => false,
            'multi_edit' => false,
        ];
    }

    // ... [andere Methoden bleiben unverändert]

    protected function validateLayout(array $field_names, array $values, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Validate map_attributes JSON if present
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
        }

        // Only validate legacy fields if map_attributes is not set
        if (empty($values['map_attributes'])) {
            if (!empty($values['height']) && !empty($values['format'])) {
                $self->setElement(
                    'message',
                    'Bitte nur die «Map-H&ouml;he» oder die «CSS-Klasse» angeben!'
                );
                return true;
            }

            if (!empty($values['height'])) {
                if (!preg_match('@^(?<height>[1-9]\d*)\s*(?<unit>px|em|rem|vh)?$@', $values['height'])) {
                    $self->setElement(
                        'message',
                        'Ungültige Höhenangabe. Bitte nur eine Integer-Zahl sowie eine der zulässigen Masseinheiten eingeben'
                    );
                    return true;
                }
            }
        }

        return false;
    }
}
