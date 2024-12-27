<?php

class geo_search {

    private $postalcode_table;
    private $postalcode_lat_field;
    private $postalcode_lng_field;
    private $postalcode_postalcode_field;

    private $result_table;
    private $result_lat_field;
    private $result_lng_field;

    private $apiKey;
    private $addressFields;
    private $batchSize;

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

    public static function forBulkGeocoding($table, $addressFields, $latField, $lngField, $apiKey = '', $batchSize = 200)
    {
        $instance = new self(
            ['table' => '', 'lat_field' => '', 'lng_field' => '', 'postalcode_field' => ''],
            ['table' => $table, 'lat_field' => $latField, 'lng_field' => $lngField]
        );
        $instance->addressFields = $addressFields;
        $instance->apiKey = $apiKey;
        $instance->batchSize = $batchSize;
        return $instance;
    }

    /**
     * Create instance for single address geocoding
     */
    public static function forGeocoding($apiKey = '')
    {
        $instance = new self(
            ['table' => '', 'lat_field' => '', 'lng_field' => '', 'postalcode_field' => ''],
            ['table' => '', 'lat_field' => '', 'lng_field' => '']
        );
        $instance->apiKey = $apiKey;
        return $instance;
    }

    /**
     * Geocode a single address
     * 
     * @param string $street
     * @param string $city
     * @param string $postalcode
     * @return array|null ['lat' => float, 'lng' => float]
     */
    public function geocodeAddress($street, $city, $postalcode)
    {
        $address = implode(' ', array_filter([$street, $city, $postalcode]));
        return $this->getCoordinates($address);
    }

    /**
     * Geocode any address string
     * 
     * @param string $address Complete address string
     * @return array|null ['lat' => float, 'lng' => float]
     */
    public function geocode($address)
    {
        return $this->getCoordinates($address);
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

    public function searchByLatLng($lat, $lng, $radius) {
        // folgt...
    }

    public function getUncodedRecords()
    {
        if (!isset($this->addressFields)) {
            throw new Exception('Not configured for bulk geocoding. Use forBulkGeocoding() instead.');
        }

        $sql = rex_sql::factory();
        $fields = implode(', ', array_merge(['id'], $this->addressFields));
        
        return $sql->getArray(
            "SELECT {$fields} FROM {$this->result_table} 
             WHERE {$this->result_lat_field} = '' OR {$this->result_lat_field} IS NULL 
             OR {$this->result_lng_field} = '' OR {$this->result_lng_field} IS NULL 
             LIMIT ?",
            [$this->batchSize ?? 200]
        );
    }

    public function processRecord($record)
    {
        if (!isset($this->addressFields)) {
            throw new Exception('Not configured for bulk geocoding. Use forBulkGeocoding() instead.');
        }

        $address = implode(' ', array_map(function($field) use ($record) {
            return $record[$field] ?? '';
        }, $this->addressFields));

        if (empty(trim($address))) {
            return false;
        }

        $coordinates = $this->getCoordinates($address);
        if (!$coordinates) {
            return false;
        }

        return $this->updateRecord($record['id'], $coordinates);
    }

    public function processBatch()
    {
        if (!isset($this->addressFields)) {
            throw new Exception('Not configured for bulk geocoding. Use forBulkGeocoding() instead.');
        }

        $stats = [
            'total' => 0,
            'success' => 0,
            'failed' => 0
        ];

        $records = $this->getUncodedRecords();
        $stats['total'] = count($records);

        foreach ($records as $record) {
            if ($this->processRecord($record)) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
            
            usleep(500000); // 0.5 second delay
        }

        return $stats;
    }

    private function getCoordinates($address)
    {
        if ($this->apiKey) {
            $url = 'https://api.geoapify.com/v1/geocode/search'
                . '?text=' . urlencode($address)
                . '&limit=1'
                . '&apiKey=' . $this->apiKey;
        } else {
            $url = 'https://nominatim.openstreetmap.org/search'
                . '?q=' . urlencode($address)
                . '&format=json'
                . '&limit=1';
        }

        $response = @file_get_contents($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        
        if ($this->apiKey) {
            if (!empty($data['features'][0]['properties'])) {
                return [
                    'lat' => $data['features'][0]['properties']['lat'],
                    'lng' => $data['features'][0]['properties']['lon']
                ];
            }
        } else {
            if (!empty($data[0])) {
                return [
                    'lat' => $data[0]['lat'],
                    'lng' => $data[0]['lon']
                ];
            }
        }

        return null;
    }

    private function updateRecord($id, $coordinates)
    {
        $sql = rex_sql::factory();
        try {
            $sql->setTable($this->result_table);
            $sql->setWhere('id = :id', ['id' => $id]);
            $sql->setValue($this->result_lat_field, $coordinates['lat']);
            $sql->setValue($this->result_lng_field, $coordinates['lng']);
            $sql->update();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
