<?php

class geo_seach {

    /**
     *   Geo-Search: PHP Klasse für die Umkreissuche
     *   $settings_postalcode: Tabelle mit PLZ Daten (Quelle z.B. OpenGeoDB)
     *   $settings_retults: Tabelle mit Ergebnisdaten (Datensätze der Umkreissuche)
     */

    private $postalcode_table;
    private $postalcode_lat_field;
    private $postalcode_lng_field;
    private $postalcode_postalcode_field;

    private $result_table;
    private $result_lat_field;
    private $result_lng_field;

    public function __construct($settings_postalcode, $settings_result)
    {

        $this->postalcode_table = $settings_postalcode['table'];
        $this->postalcode_lat_field = $settings_postalcode['lat_field'];
        $this->postalcode_lng_field = $settings_postalcode['lng_field'];
        $this->postalcode_postalcode_field = $settings_postalcode['postalcode_field'];

        $this->result_table = $settings_result['table'];
        $this->result_lat_field = $settings_result['lat_field'];
        $this->result_lng_field = $settings_result['lng_field'];

    }

    private function getIdByPostalcode($postalcode) {

        $sql = rex_sql::factory()->getArray('
            SELECT
                id,'.$this->postalcode_postalcode_field.'
            FROM
                '.$this->postalcode_table.'
            WHERE
                '.$this->postalcode_postalcode_field.' = :postalcode
            LIMIT 0,1
        ', [':postalcode' => $postalcode]);

        if($sql)
            return $sql[0]['id'];
        return -1;

    }

    public function searchByPostalcode($postalcode, $radius) {

        $id = $this->getIdByPostalcode($postalcode);

        $sql = rex_sql::factory()->getArray('
            SELECT 
                dest.*, 
                ACOS(
                     SIN(RADIANS(src.'.$this->postalcode_lat_field.')) * SIN(RADIANS(dest.'.$this->result_lat_field.')) 
                     + COS(RADIANS(src.'.$this->postalcode_lat_field.')) * COS(RADIANS(dest.'.$this->result_lat_field.'))
                     * COS(RADIANS(src.'.$this->postalcode_lng_field.') - RADIANS(dest.'.$this->result_lng_field.'))
                ) * 6380 AS distance
            FROM '.$this->result_table.' dest
            CROSS JOIN '.$this->postalcode_table.' src
            WHERE src.id = :id
            HAVING distance < :distance
            ORDER BY distance', [
            ':id' => $id,
            ':distance' => intval($radius)
        ] );

        return $sql;

    }

    public function searchByLatLng($lat,$lng,$radius) {
        // folgt...
    }

}