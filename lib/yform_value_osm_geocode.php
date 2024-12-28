<?php

class rex_yform_value_osm_geocode extends rex_yform_value_abstract
{

    function enterObject()
    {
        $addressfields = explode(',', str_replace(" ", "", $this->getElement('address')));
        $geofields = explode(',', str_replace(" ", "", $this->getElement('latlng')));
        $height = intval($this->getElement('height'));
        $mapbox_token = $this->getElement('mapbox_token');
        
        if ($this->needsOutput()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.osm_geocode.tpl.php', compact('addressfields', 'geofields', 'height', 'mapbox_token'));
        }
     }

    function getDescription(): string
    {
        return 'osm_geocode|osmgeocode|Bezeichnung|pos_lat,pos_lng|strasse,plz,ort|height|';
    }

    function getDefinitions(): array
    {
        return array(
            'type' => 'value',
            'name' => 'osm_geocode',
            'values' => array(
                'name'     => array( 'type' => 'name', 'label' => 'Name' ),
                'label'    => array( 'type' => 'text', 'label' => 'Bezeichnung'),
                'latlng'  => array( 'type' => 'text', 'label' => 'Feldnamen LAT/LNG (Bsp. pos_lat,pos_lng)'),
                'address'  => array( 'type' => 'text', 'label' => 'Feldnamen Positionsfindung (Bsp. strasse,plz,ort)'),
                'height'   => array( 'type' => 'text', 'label' => 'Map-H&ouml;he'),
                'mapbox_token' => array ( 'type' => 'text', 'label' => 'Mapbox Token (optional)')
            ),
            'description' => 'Openstreetmap Positionierung',
            'dbtype' => 'varchar(191)',
            'formbuilder' => false,
            'multi_edit' => false,
        );

    }

}
