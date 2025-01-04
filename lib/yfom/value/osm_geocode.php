<?php

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
     * If they are pure helper fields (no_db), they are pre-filled with data
     * from this field on first call.
     *
     * @throws rex_functional_exception
     * @return void
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
     * Handles both legacy attributes and new map_attributes configuration.
     *
     * @return void
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

    /**
     * Returns the description string for this field type.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|height|[mapbox_token]|[no_db]|[map_attributes]';
    }

    /**
     * Returns the field definitions including validation rules.
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        $definitions = [
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
            'description' => '🧩 yform_geo_osm: OpenStreetMap-Karte und Geoocodierung',
            'dbtype' => 'varchar(191)',
            'formbuilder' => false,
            'multi_edit' => false,
        ];

        $this->params['validates'][] = [
            'type' => 'empty',
            'name' => 'latlng',
            'message' => 'Bitte Koordinaten-Felder angeben.'
        ];

        $this->params['validates'][] = [
            'type' => 'preg_match',
            'name' => 'latlng',
            'message' => 'Bitte genau zwei Felder durch Komma getrennt angeben.',
            'pattern' => '/^[a-zA-Z0-9_]+,[a-zA-Z0-9_]+$/',
            'not_required' => false
        ];

        $this->params['validates'][] = [
            'type' => 'custom',
            'name' => ['map_attributes', 'height'],
            'message' => 'Ungültiges JSON-Format oder ungültige Höhenangabe.',
            'validate' => function($value, $element) {
                // Prüfen der map_attributes, wenn vorhanden
                if ($element['name'] === 'map_attributes' && !empty($value)) {
                    try {
                        $attributes = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($attributes)) {
                            return false;
                        }
                    } catch (\JsonException $e) {
                        return false;
                    }
                }

                // Prüfen der height, wenn map_attributes nicht gesetzt ist
                $mapAttributes = $this->getElement('map_attributes');
                if (empty($mapAttributes) && $element['name'] === 'height' && !empty($value)) {
                    if (!preg_match('@^(?<height>[1-9]\d*)\s*(?<unit>px|em|rem|vh)?$@', $value)) {
                        return false;
                    }
                }

                return true;
            }
        ];

        $this->params['validates'][] = [
            'type' => 'custom',
            'name' => 'latlng',
            'message' => 'Die angegebenen Koordinaten-Felder existieren nicht in der Tabelle.',
            'validate' => function($value) {
                $coord_field_names = array_map('trim', explode(',', $value));
                $coord_field_names = array_filter($coord_field_names);
                
                $sql = rex_sql::factory();
                $field_list = $sql->getArray(
                    'SELECT name FROM ' . rex::getTable('yform_field') . ' 
                     WHERE type_id = :ti AND table_name = :tn',
                    [
                        ':ti' => 'value',
                        ':tn' => $this->params['main_table']
                    ]
                );
                $field_list = array_column($field_list, 'name');

                foreach ($coord_field_names as $field) {
                    if (!in_array($field, $field_list)) {
                        return false;
                    }
                }
                return true;
            }
        ];

        return $definitions;
    }
}
