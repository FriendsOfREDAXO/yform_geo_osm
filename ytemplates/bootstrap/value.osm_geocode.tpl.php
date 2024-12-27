<?php
$class_group = trim('form-group yform-element ' . $this->getWarningClass());
$class_label = 'control-label';

$address_selectors = [];
foreach($addressfields as $afield) {
    foreach($this->params["values"] as $val) {
        if ($val->getName() == $afield) {
            $address_selectors[] = "#".$val->getFieldId();
        }
    }
}

$geo_selectors = [];
foreach($this->params["values"] as $val) {
    if ($val->getName() == $geofields[0]) {
        $geo_selectors['lat'] = "#".$val->getFieldId();
    }
    if ($val->getName() == $geofields[1]) {
        $geo_selectors['lng'] = "#".$val->getFieldId();
    }
}

$js = '<script type="text/javascript">
    jQuery(function($){
        var rex_geo_osm_'.$this->getId().' = new rex_geo_osm('.json_encode($address_selectors).', '.json_encode($geo_selectors).', '.$this->getId().', "'.$mapbox_token.'");
    });
</script>';

rex_extension::register('OUTPUT_FILTER', 'yform_geo_osm::addDynJs', rex_extension::LATE, ['js' => $js]);
?>

<div class="<?= $class_group ?>" id="<?= $this->getHTMLId('osm') ?>">
    <label class="<?= $class_label ?>"><?= $this->getElement('label') ?></label>

    <br>
    <div class="btn-group">
        <button class="btn btn-primary set" id="set-geo-<?=$this->getId()?>" type="button">
            <i class="fa-solid fa-map-marker-alt"></i> 
            <?= rex_i18n::msg('yform_geo_osm_get_coords') ?>
        </button>
        <button class="btn btn-primary search" id="search-geo-<?=$this->getId()?>" type="button">
            <i class="fa-solid fa-magnifying-glass"></i> 
            <?= rex_i18n::msg('yform_geo_osm_search_address') ?>
        </button>
        <button class="btn btn-primary browser-location" id="browser-geo-<?=$this->getId()?>" type="button">
            <i class="fa-solid fa-location-crosshairs"></i> 
            <?= rex_i18n::msg('yform_geo_osm_get_location') ?>
        </button>
    </div>

    <div id="map-<?=$this->getId()?>" style="height:<?=$height?>px; margin-top:5px;"></div>

    <!-- Search Modal -->
    <div class="rex-geo-search-modal" id="rex-geo-search-modal-<?=$this->getId()?>">
        <div class="rex-geo-search-content">
            <span class="rex-geo-search-close">&times;</span>
            <input type="text" id="rex-geo-search-input-<?=$this->getId()?>" 
                   placeholder="<?= rex_i18n::msg('yform_geo_osm_search_placeholder') ?>"
                   autocomplete="off">
            <div id="rex-geo-search-results-<?=$this->getId()?>" class="search-results"></div>
        </div>
    </div>
</div>
