<?php
/**
 * Aus Codeschnippseln verschiedener Addons
 */


namespace yform\geoosm;

echo \rex_view::title('YForm GeoOSM');

$table_name = rex_request('osm-geo-table', 'string','');

$geosql = \rex_sql::factory();
$table = null;
$addon = \rex_addon::get('yform_geo_osm');

if (rex_request('config-submit','int',0) == 1) {
    $addon->setConfig('geoapifykey',rex_request('geoapifykey','string'));
}

$tables = \rex_yform_manager_table::getAll();
$geo_tables = [];
foreach ($tables as $i_table) {
    $fields = $i_table->getValueFields(['type_name' => 'osm_geocode']);
    if (count($fields) > 0) {
        $geo_tables[$i_table->getTableName()] = $i_table;
        if ($table_name == $i_table->getTableName()) {
            $table = $i_table;
            break;
        }
    }
}


$sel_table = new \rex_select();
$sel_table->setId('osm-geo-table');
$sel_table->setName('osm-geo-table');
$sel_table->setSize(1);
$sel_table->setAttribute('class', 'form-control');
$sel_table->setSelected(rex_request('osm-geo-table','string',''));

foreach ($geo_tables as $gt=>$gtable) {
    $sel_table->addOption($gt, $gt);
}

$content = '<p>Für die Geocodierung ist ein API Key von <a href="https://www.geoapify.com/" target="_blank">Geo Apify</a> notwendig. Stand 08/2020 ist der freie Account ausreichend, wenn ein Link ins Impressum gesetzt wird (siehe Bedingungen des Anbieters).</p>'
        . '<p>Wähle eine Tabelle zur Geocodierung aus.</p>';

$content .= '<fieldset>';


$formElements = [];
$n = [];
$n['field'] = '<input type="text" value="'.$addon->getConfig('geoapifykey').'" name="geoapifykey" class="form-control">';
$n['label'] = '<label for="geoapifykey">Geo Apify key</label>';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');



$formElements = [];
$n = [];
$n['label'] = '<label for="osm-geo-table">Tabelle</label>';
$n['field'] = $sel_table->get();
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');



$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1" ' . \rex::getAccesskey($this->i18n('save'), 'save') . '>Geocodierung starten</button>';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', 'OSM Geocodierung');
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '
    <form action="' . \rex_url::currentBackendPage() . '" method="post">
        ' . $content . '
    </form>';


if ($table) {
    $content = '';
    $func = rex_request('geo_func', 'string');
    $field = rex_request('geo_field', 'string');
    
    $geo_sql = \rex_sql::factory();
//    $geo_sql->setDebug();
    
    if ($func == 'get_data') {
        $data = [];
        ob_end_clean();
        if (array_key_exists($field, $fields)) {
            $address_fields = explode(',', $fields[$field]['address']);
            $fs = [];
            foreach ($address_fields as $f) {
                $fs[] = $geo_sql->escapeIdentifier(trim($f));
            }
            $concat = 'CONCAT(' . implode(' , ",", ', $fs) . ') as address';

            $pos_fields = explode(',', $fields[$field]['latlng']); // das Element position gibt es nicht
            $pos_field = $fields[$field]['name'];
            if (count($pos_fields) == 2) {
                $pos_lat = $pos_fields[0];
                $pos_lng = $pos_fields[1];
                $geo_sql->setQuery('select id, ' . $concat . ' from ' . $table['table_name'] . ' where ' . $pos_lng . '="" or ' . $pos_lng . ' IS NULL or ' . $pos_lat . '="" or ' . $pos_lat . ' IS NULL LIMIT 200');
                $data = ($geo_sql->getArray());
            } elseif ($pos_field) {
                $geo_sql->setQuery('select id, ' . $concat . ' from ' . $table['table_name'] . ' where ' . $pos_field . '="" or ' . $pos_field . ' IS NULL LIMIT 200');
                $data = ($geo_sql->getArray());
            }
        }
        echo json_encode($data);
        
        exit;
    }
    
    if ($func == 'save_data') {
        ob_end_clean();
        $data = '0';
        if (array_key_exists($field, $fields)) {
            $data_lng = rex_request('geo_lng', 'string');
            $data_lat = rex_request('geo_lat', 'string');
            $data_id = rex_request('geo_id', 'int', 0);
            $pos_fields = explode(',', $fields[$field]['latlng']);
            $pos_field = $fields[$field]['name'];
            if (count($pos_fields) == 2) {
                $pos_lat = $pos_fields[0];
                $pos_lng = $pos_fields[1];
                $gd = \rex_sql::factory();
                $gd->setQuery('select id, ' . $gd->escapeIdentifier($pos_lat) . ', ' . $gd->escapeIdentifier($pos_lng) . ' from ' . $table['table_name'] . ' where id = ' . $gd->escape($data_id) . '');
                if ($gd->getRows() == 1 && $data_lng != '' && $data_lat != '') {
                    $sd = \rex_sql::factory();
                    $sd->setTable($table['table_name']);
                    $sd->setWhere('id=' . $data_id);
                    $sd->setValue($pos_lat, $data_lat);
                    $sd->setValue($pos_lng, $data_lng);
                    $sd->update();
                    $data = '1';
                }
            } elseif ($pos_field) {
                $gd = \rex_sql::factory();
                $gd->setQuery('select id from ' . $table['table_name'] . ' where id = ' . $gd->escape($data_id));
                if ($gd->getRows() == 1 && $data_lng != '' && $data_lat != '') {
                    $geopos = $data_lat.','.$data_lng;
                    $sd = \rex_sql::factory();
                    $sd->setTable($table['table_name']);
                    $sd->setWhere('id=' . $data_id);
                    $sd->setValue($pos_field, $geopos);
                    $sd->update();
                    $data = '1';
                }                
            } 
        }
        echo $data;
        exit;
    }    
    
    foreach ($fields as $k => $v) {
        $content .= '<p><a class="btn btn-setup" href="javascript:osm_geo_updates(\'' . $table['table_name'] . '\',\'' . $k . '\')">Google Geotagging starten</a> &nbsp;Hiermit werden alle Datensätze anhand des Felder "' . $k . '" nach fehlenden Geopositionen durchsucht und neu gesetzt.</p>'
                . '<p>[<span id="osm_geo_count_' . $k . '"></span>]</p>';
    }
    
    $content .= '</fieldset>';
    
    echo $content;
   
}

?>

<script type="text/javascript">
    
    var data_counter = 0;
    var data_running = 0;
    var data_next = -1;
    var data = "";
    var table = "";
    var field = "";
    var data_id = 0;
    var latitude = 0;
    var longitude = 0;
    var locked = 0;
    
    function osm_geo_updates(tablename,fieldname) {
        if(data_running == 1) return false;
        data_running = 1;
        field = fieldname;
        table = tablename;
        var currentTime = new Date();
        link = "index.php?page=yform/yform_geo_osm/&osm-geo-table="+tablename+"&geo_func=get_data&geo_field="+fieldname+"&nocache="+currentTime.getTime();
        jQuery.ajax({
            url: link,
            dataType: "json",
            success: function(datam){
                data = datam;
//                console.log(datam);
                data_counter = 0;
                osm_geo_update();
            },
            error: function() {
                alert("error loading "+link);
            }
        });
    }
    
    function osm_geo_update() {
        if(data.length == data_counter) {
            data_running = 0;
            return false;
        }
        
//        var address = encodeURIComponent(data[data_counter]["address"]);
        var address = data[data_counter]["address"];
        data_id = data[data_counter]["id"];
        
//        get_geocode_link = 'https://nominatim.openstreetmap.org/search?q='+address+"&format=json&polygon=0&addressdetails=0";
//        get_geocode_link = 'https://api.nettoolkit.com/v1/geo/geocodes?address='+address+'&key=test_RXNtAxEJzQHkLiorg1wEmVyzirqlsxQYS4gDncPl';

        get_geocode_link = 'https://api.geoapify.com/v1/geocode/search?text='+address+'&limit=1&apiKey=<?= $addon->getConfig('geoapifykey') ?>';
        locked = 1;

        jQuery.ajax({
            dataType: "json",
            url: get_geocode_link,
            success: function(gdata) {
                latitude = gdata.features[0].properties.lat;
                longitude = gdata.features[0].properties.lon;
                save_data();
            }
        });

        data_counter = data_counter + 1;

        if(data_next == "0") {
            jQuery("#osm_geo_count_"+field).html(jQuery("#osm_geo_count_"+field).html()+"<a href=\"index.php?page=yform/manager/data_edit&table_name="+table+"&data_id="+data_id+"&func=edit&start=\">Geocoding not possible, try manually [id=\""+data_id+"\"]</a>");
            // return false;
        }
        
        
//        setTimeout("osm_geo_update()",1000);
        waitForReady();

    }
    
    
    function waitForReady() {
        if(locked == 0) {
            osm_geo_update();
        } else {
            setTimeout('waitForReady()', 50);
        }
    }    
    
    
    function save_data () {
        
        lat = latitude;
        lon = longitude;
        
        data_link = "index.php?page=yform/yform_geo_osm/&osm-geo-table="+table+"&geo_func=save_data&geo_field="+field+"&geo_lng="+lon+"&geo_lat="+lat+"&geo_id="+data_id;
        
        jQuery.ajax({
            url: data_link,
            success: function(data_status){
                if(data_status == "1") {
                    jQuery("#osm_geo_count_"+field).html(jQuery("#osm_geo_count_"+field).html()+". ");
                    data_next = "1";
                    locked = false;
                }else {
                    // alert("data status" + data_status);
                }
            }
        });
    }
    
    
</script>
