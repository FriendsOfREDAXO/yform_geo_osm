<?php
/**
 * @var array<string, rex_yform_value_abstract> $addressfields
 * @var array<rex_yform_value_abstract> $geofields
 * @var array<string, string> $mapAttributes
 * @var string|null $mapbox_token
 */

$fieldId = $this->getId();

// Prepare address fields for JavaScript
$addressSelectors = [];
foreach ($addressfields as $name => $field) {
    $addressSelectors[] = '#' . $field->getFieldId();
}

// Prepare geo fields for JavaScript
$lat = $geofields[0]->getFieldId();
$lng = $geofields[1]->getFieldId();

// Build HTML attributes string for map div
$htmlAttributes = '';
if (!empty($mapAttributes) && is_array($mapAttributes)) {
    foreach ($mapAttributes as $attr => $value) {
        $htmlAttributes .= ' ' . rex_escape($attr) . '="' . rex_escape($value) . '"';
    }
}
?>

<div class="yform-geocoding-wrapper">
    <div class="rex-geo-search-wrapper">
        <input type="text" 
               class="rex-geo-search-input" 
               id="rex-geo-search-input-<?= $fieldId ?>" 
               placeholder="<?= rex_i18n::msg('yform_geo_osm_search_placeholder') ?>" 
               autocomplete="off">
        <div id="rex-geo-search-results-<?= $fieldId ?>" class="rex-geo-search-results"></div>
    </div>

    <div class="btn-group">
        <button type="button" 
                class="btn btn-default" 
                id="search-geo-<?= $fieldId ?>">
            <?= rex_i18n::msg('yform_geo_osm_search_address') ?>
        </button>
        <button type="button" 
                class="btn btn-default" 
                id="browser-geo-<?= $fieldId ?>">
            <?= rex_i18n::msg('yform_geo_osm_get_location') ?>
        </button>
        <button type="button" 
                class="btn btn-default" 
                id="center-geo-<?= $fieldId ?>">
            <?= rex_i18n::msg('yform_geo_osm_center_map') ?>
        </button>
    </div>

    <div class="map-wrapper">
        <div id="map-<?= $fieldId ?>"<?= $htmlAttributes ?>></div>
        <div id="rex-geo-overlay-<?= $fieldId ?>" class="rex-geo-overlay">
            <div class="rex-geo-overlay-content">
                <?= rex_i18n::msg('yform_geo_osm_no_data') ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    rex_geo_osm(
        <?= json_encode($addressSelectors) ?>,
        {
            lat: '#<?= $lat ?>',
            lng: '#<?= $lng ?>'
        },
        '<?= $fieldId ?>',
        <?= $mapbox_token ? json_encode($mapbox_token) : 'null' ?>,
        <?= !empty($mapAttributes) ? json_encode($mapAttributes) : 'null' ?>
    );
});
</script>
