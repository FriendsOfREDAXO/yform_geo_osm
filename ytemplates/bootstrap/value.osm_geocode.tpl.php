<?php

/**
 * @var array<string, rex_yform_value_abstract> $addressfields
 * @var array<string, rex_yform_value_abstract> $geofields
 * @var array<string, mixed> $formatData
 * @var string $mapbox_token
 * @var string $height
 * @var rex_yform_value_osm_geocode $this
 */

$class_group = trim('form-group yform-element ' . $this->getWarningClass());
$class_label = 'control-label';

$address_selectors = [];
foreach ($addressfields as $afield) {
    $address_selectors[] = '#' . $afield->getFieldId();
}

$geo_selectors = [
    'lat' => '#' . $geofields[0]->getFieldId(),
    'lng' => '#' . $geofields[1]->getFieldId(),
];

// JavaScript-Initialisierung vorbereiten
$initScript = '<script type="text/javascript">';
$initScript .= 'jQuery(function($){';

// Map-Initialisierung mit formatData
if (!empty($formatData)) {
    // Karteneinstellungen aus formatData extrahieren
    $initLat = $formatData['data-init-lat'] ?? '50.1109221';  // Frankfurt als Default
    $initLng = $formatData['data-init-lng'] ?? '8.6821267';
    $initZoom = $formatData['data-init-zoom'] ?? '2';
    
    unset($formatData['data-init-lat'], $formatData['data-init-lng'], $formatData['data-init-zoom']);
    
    $initScript .= sprintf(
        'var rex_geo_osm_%d = new rex_geo_osm(%s, %s, %d, "%s", {initialLat: %s, initialLng: %s, initialZoom: %d});',
        $this->getId(),
        json_encode($address_selectors),
        json_encode($geo_selectors),
        $this->getId(),
        $mapbox_token,
        $initLat,
        $initLng,
        $initZoom
    );
} else {
    // Legacy-Initialisierung ohne zusätzliche Optionen
    $initScript .= sprintf(
        'var rex_geo_osm_%d = new rex_geo_osm(%s, %s, %d, "%s");',
        $this->getId(),
        json_encode($address_selectors),
        json_encode($geo_selectors),
        $this->getId(),
        $mapbox_token
    );
}

$initScript .= '});';
$initScript .= '</script>';

// Map-Container Attribute vorbereiten
$mapAttributes = [
    'id' => 'map-' . $this->getId(),
];

if (!empty($formatData)) {
    // CSS-Klassen separat behandeln
    if (isset($formatData['class'])) {
        $mapAttributes['class'] = $formatData['class'];
        unset($formatData['class']);
    }
    
    // Verbleibende Attribute hinzufügen
    foreach ($formatData as $key => $value) {
        $mapAttributes[$key] = $value;
    }
} elseif ($height !== '') {
    // Legacy height Fallback
    $mapAttributes['style'] = sprintf('height: %s; margin-top:5px;', $height);
}

// HTML für die Buttons generieren
$fragment = new rex_fragment();
$items = [];

$items[] = [
    'label' => '<i class="fa-solid fa-arrows-to-circle"></i>&nbsp;',
    'attributes' => [
        'class' => ['btn-default', 'map-center'],
        'type' => 'button',
        'id' => 'center-geo-' . $this->getId(),
    ],
];

if (0 < count($address_selectors)) {
    $items[] = [
        'label' => '<i class="fa-solid fa-map-marker-alt"></i> ' . rex_i18n::msg('yform_geo_osm_get_coords'),
        'attributes' => [
            'class' => ['btn-primary', 'set'],
            'type' => 'button',
            'id' => 'set-geo-' . $this->getId(),
        ],
    ];
}

$items[] = [
    'label' => '<i class="fa-solid fa-magnifying-glass"></i> ' . rex_i18n::msg('yform_geo_osm_search_address'),
    'attributes' => [
        'class' => ['btn-primary', 'search'],
        'type' => 'button',
        'id' => 'search-geo-' . $this->getId(),
    ],
];

$items[] = [
    'label' => '<i class="fa-solid fa-location-crosshairs"></i> ' . rex_i18n::msg('yform_geo_osm_get_location'),
    'attributes' => [
        'class' => ['btn-primary', 'browser-location'],
        'type' => 'button',
        'id' => 'browser-geo-' . $this->getId(),
    ],
];

$fragment->setVar('buttons', $items, false);
$buttonHTML = $fragment->parse('core/buttons/button_group.php');

?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId('osm') ?>">
    <label class="<?= $class_label ?>"><?= $this->getElement('label') ?></label>

    <br><?= $buttonHTML ?>

    <div <?= rex_string::buildAttributes($mapAttributes) ?>></div>

    <!-- Search Modal -->
    <div class="rex-geo-search-modal" id="rex-geo-search-modal-<?= $this->getId()?>">
        <div class="rex-geo-search-content">
            <span class="rex-geo-search-close">&times;</span>
            <div class="rex-geo-search-wrapper">
                <input type="text"
                    id="rex-geo-search-input-<?= $this->getId()?>"
                    class="rex-geo-search-input form-control input-lg"
                    placeholder="<?= rex_i18n::msg('yform_geo_osm_search_placeholder') ?>"
                    autocomplete="off">
            </div>
            <div id="rex-geo-search-results-<?= $this->getId()?>" class="search-results"></div>
        </div>
    </div>
</div>
<?= $initScript ?>
