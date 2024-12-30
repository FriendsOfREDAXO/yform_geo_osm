<?php

use FriendsOfRedaxo\YFormGeoOsm\rex_yform_value_osm_geocode;

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
?>

<div class="<?= $class_group ?>"
	id="<?= $this->getHTMLId('osm') ?>">
	<label
		class="<?= $class_label ?>"><?= $this->getElement('label') ?></label>

	<br>
	<div class="btn-group">
		<button class="btn btn-primary set"
			id="set-geo-<?= $this->getId()?>" type="button">
			<i class="fa-solid fa-map-marker-alt"></i>
			<?= rex_i18n::msg('yform_geo_osm_get_coords') ?>
		</button>
		<button class="btn btn-primary search"
			id="search-geo-<?= $this->getId()?>" type="button">
			<i class="fa-solid fa-magnifying-glass"></i>
			<?= rex_i18n::msg('yform_geo_osm_search_address') ?>
		</button>
		<button class="btn btn-primary browser-location"
			id="browser-geo-<?= $this->getId()?>" type="button">
			<i class="fa-solid fa-location-crosshairs"></i>
			<?= rex_i18n::msg('yform_geo_osm_get_location') ?>
		</button>
	</div>

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
