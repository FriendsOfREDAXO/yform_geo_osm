<?php

namespace FriendsOfRedaxo\YFormGeoOsm;

use rex_sql;
use rex_addon;
use Exception;
use rex_sql_exception;

class Search
{

    private string $postalcode_table;
    private string $postalcode_lat_field;
    private string $postalcode_lng_field;
    private string $postalcode_postalcode_field;

    private string $result_table;
    private string $result_lat_field;
    private string $result_lng_field;

    private string $apiKey;
    private array $addressFields;
    private $batchSize;

    public function __construct(array $settings_postalcode, array $settings_result)
    {
        $this->postalcode_table = $settings_postalcode['table'];
        $this->postalcode_lat_field = $settings_postalcode['lat_field'];
        $this->postalcode_lng_field = $settings_postalcode['lng_field'];
        $this->postalcode_postalcode_field = $settings_postalcode['postalcode_field'];

        $this->result_table = $settings_result['table'];
        $this->result_lat_field = $settings_result['lat_field'];
        $this->result_lng_field = $settings_result['lng_field'];
    }

    /**
     * @api
     * Create instance for bulk geocoding
     * @param string $table Table name
     * @param string|array $addressFields Either comma-separated string or array of field names
     * @param string $latField Latitude field name
     * @param string $lngField Longitude field name
     * @param string $apiKey API key (optional)
     * @param int $batchSize Batch size (optional)
     * @return self
     */
    public static function forBulkGeocoding(string $table, string|array $addressFields, string $latField, string $lngField, string $apiKey = '', $batchSize = 200): self
    {
        $instance = new self(
            ['table' => '', 'lat_field' => '', 'lng_field' => '', 'postalcode_field' => ''],
            ['table' => $table, 'lat_field' => $latField, 'lng_field' => $lngField]
        );

        // Konvertiere Array zu String wenn nötig
        if (is_array($addressFields)) {
            $instance->addressFields = $addressFields;
        } else {
            // Wandle String in Array um und entferne eventuelle Leerzeichen
            $instance->addressFields = array_map('trim', explode(',', $addressFields));
        }

        $instance->apiKey = $instance->getApiKey($apiKey);
        $instance->batchSize = $batchSize;
        return $instance;
    }
    /**
     * @api
     * Create instance for single address geocoding
     */
    public static function forGeocoding(string $apiKey = ''): self
    {
        $instance = new self(
            ['table' => '', 'lat_field' => '', 'lng_field' => '', 'postalcode_field' => ''],
            ['table' => '', 'lat_field' => '', 'lng_field' => '']
        );
        $instance->apiKey = $instance->getApiKey($apiKey);
        return $instance;
    }

    /**
     * @api
     * Get API key from config if 'config' is passed, otherwise return the provided key
     */
    private function getApiKey(string $key): string
    {
        if ($key === 'config') {
            return rex_addon::get('yform_geo_osm')->getConfig('geoapifykey');
        }
        return $key;
    }

    /**
     * Geocode a single address
     *@api
     * @param string $street
     * @param string $city
     * @param string $postalcode
     * @return array|null ['lat' => float, 'lng' => float]
     */
    public function geocodeAddress(string $street, string $city, string $postalcode = ''): ?array
    {
        $address = implode(' ', array_filter([$street, $city, $postalcode]));
        return $this->getCoordinates($address);
    }

    /**
     * Geocode any address string
     *
     * @api
     * @param string $address Complete address string
     * @return array|null ['lat' => float, 'lng' => float]
     */
    public function geocode(string $address): ?array
    {
        return $this->getCoordinates($address);
    }

    private function getIdByPostalcode(string $postalcode): int
    {
        $sql = rex_sql::factory()->getArray('
            SELECT
                id,' . $this->postalcode_postalcode_field . '
            FROM
                ' . $this->postalcode_table . '
            WHERE
                ' . $this->postalcode_postalcode_field . ' = :postalcode
            LIMIT 0,1
        ', [':postalcode' => $postalcode]);

        if ($sql) {
            return $sql[0]['id'];
        }
        return -1;
    }

    /**
     * Search for results within a radius of a postal code
     *
     * @api
     * @param string $postalcode
     * @param int $radius
     * @return array
     */
    public function searchByPostalcode(string $postalcode, int $radius): array
    {
        $id = $this->getIdByPostalcode($postalcode);

        $sql = rex_sql::factory()->getArray('
            SELECT 
                dest.*, 
                ACOS(
                     SIN(RADIANS(src.' . $this->postalcode_lat_field . ')) * SIN(RADIANS(dest.' . $this->result_lat_field . ')) 
                     + COS(RADIANS(src.' . $this->postalcode_lat_field . ')) * COS(RADIANS(dest.' . $this->result_lat_field . '))
                     * COS(RADIANS(src.' . $this->postalcode_lng_field . ') - RADIANS(dest.' . $this->result_lng_field . '))
                ) * 6380 AS distance
            FROM ' . $this->result_table . ' dest
            CROSS JOIN ' . $this->postalcode_table . ' src
            WHERE src.id = :id
            HAVING distance < :distance
            ORDER BY distance', [
            ':id' => $id,
            ':distance' => $radius
        ]);

        return $sql;
    }

    /**
     * Search for results within a radius of a postal code
     *
     * @api
     * @param float $lat
     * @param float $lng
     * @param int $radius
     * @return array
     */
    public function searchByLatLng($lat, $lng, $radius)
    {
        // folgt...
    }

    /**
     * Get all postal codes
     *
     * @api
     * @return array
     */
    public function getUncodedRecords(): array
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

    /**
     * Process a single record
     *
     * @api
     * @param array $record
     * @return bool
     */
    public function processRecord(array $record): bool
    {
        if (!isset($this->addressFields)) {
            throw new Exception('Not configured for bulk geocoding. Use forBulkGeocoding() instead.');
        }

        $address = implode(' ', array_map(function ($field) use ($record) {
            return $record[$field] ?? '';
        }, $this->addressFields));

        if (empty(trim($address)) === true) {
            return false;
        }

        $coordinates = $this->getCoordinates($address);
        if (!$coordinates) {
            return false;
        }

        return $this->updateRecord($record['id'], $coordinates);
    }

    /**
     * Process all records
     *
     * @api
     * @return array
     * @throws Exception
     * @throws rex_sql_exception
     */

    public function processBatch(): array
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

    /**
     * Get coordinates from address
     *
     * @api
     * @param string $address
     * @return array|null ['lat' => float, 'lng' => float]
     */

    private function getCoordinates(string $address): ?array
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

    /**
     * Update record with coordinates
     * @api
     * @param int $id
     * @param array $coordinates
     * @return bool
     */
    private function updateRecord(int $id, array $coordinates): bool
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
