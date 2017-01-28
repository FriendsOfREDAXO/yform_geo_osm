<?php

	$class_group   = trim('form-group yform-element ' . $this->getWarningClass());
	$class_label = 'control-label';

	$address_selectors = [];

	foreach($this->params["values"] as $val) {
	    if (in_array($val->getName(), $addressfields)) {
	        $address_selectors[] = "#".$val->getFieldId();
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

?>
<div class="<?php echo $class_group ?>" id="<?php echo $this->getHTMLId('osm') ?>">
    <label class="<?php echo $class_label ?>"><?php echo $this->getElement('label') ?></label>
    
    <br><button class="btn" id="set-geo-<?=$this->getId()?>" class="set"><?=rex_i18n::msg('yform_geo_osm_set_data');?></button>
    <div id="map-<?=$this->getId()?>" style="height:<?=$height;?>px; margin-top:5px;"></div>
</div>


<script type="text/javascript">
	
    jQuery(function($){
        rex_geo_osm_<?=$this->getId()?> = new rex_geo_osm(<?=json_encode($address_selectors);?>, <?=json_encode($geo_selectors);?>, <?=$this->getId()?>, '<?=$mapbox_token;?>');
    });

</script>
