<?php

/**
 * @var array<string> $addressfields
 * @var array<string> $geofields
 * @var string $mapbox_token
 * @var int $height
 * @var rex_yform_value_osm_geocode $this
 */

$class_group = trim('form-group yform-element ' . $this->getWarningClass());
$class_label = 'control-label';

$address_selectors = [];
foreach ($addressfields as $afield) {
    /** @var rex_yform_value_abstract $val */
    foreach ($this->params['values'] as $val) {
        if ($val->getName() === $afield) {
            $address_selectors[] = '#' . $val->getFieldId();
        }
    }
}

$geo_selectors = [];
/** @var rex_yform_value_abstract $val */
foreach ($this->params['values'] as $val) {
    if ($val->getName() === $geofields[0]) {
        $geo_selectors['lat'] = '#' . $val->getFieldId();
    }
    if ($val->getName() === $geofields[1]) {
        $geo_selectors['lng'] = '#' . $val->getFieldId();
    }
}

$js = '<script type="text/javascript">
    jQuery(function($){
        var rex_geo_osm_' . $this->getId() . ' = new rex_geo_osm(' . json_encode($address_selectors) . ', ' . json_encode($geo_selectors) . ', ' . $this->getId() . ', "' . $mapbox_token . '");
    });
</script>';

rex_extension::register('OUTPUT_FILTER', '\FriendsOfRedaxo\YFormGeoOsm\Assets::addDynJs', rex_extension::LATE, ['js' => $js]);

/**
 * Kartenbutton mit Redaxo-Mitteln zusammenbauen.
 */
$fragment = new rex_fragment();
$items = [];

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
    'label' => '<i class="fa-solid fa-location-crosshairs"></i> ' . rex_i18n::msg('yform_geo_osm_search_address'),
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

<div class="<?= $class_group ?>"
	id="<?= $this->getHTMLId('osm') ?>">
	<label
		class="<?= $class_label ?>"><?= $this->getElement('label') ?></label>

	<br><?= $buttonHTML ?>

	<div id="map-<?= $this->getId()?>"
		style="height:<?= $height?>px; margin-top:5px;"></div>

	<!-- Search Modal -->
	<div class="rex-geo-search-modal"
		id="rex-geo-search-modal-<?= $this->getId()?>">
		<div class="rex-geo-search-content">
			<span class="rex-geo-search-close">&times;</span>
			<div class="rex-geo-search-wrapper">
				<input type="text"
					id="rex-geo-search-input-<?= $this->getId()?>"
					class="rex-geo-search-input form-control input-lg"
					placeholder="<?= rex_i18n::msg('yform_geo_osm_search_placeholder') ?>"
					autocomplete="off">
			</div>
			<div id="rex-geo-search-results-<?= $this->getId()?>"
				class="search-results"></div>
		</div>
	</div>
</div>
