<?php

namespace FriendsOfRedaxo\YFormGeoOsm;

use rex_url;

class Assets
{

    public static function addAssets($ep)
    {

        $assets_header = '
			<link rel="stylesheet" type="text/css" media="all" href="'.rex_url::addonAssets('yform_geo_osm', 'leaflet/leaflet.css').'" />
   			 <link rel="stylesheet" type="text/css" media="all" href="'.rex_url::addonAssets('yform_geo_osm', 'geo_osm.css').'" />
    		</head>
		';

        $assets_footer = '
   			 <script type="text/javascript" src="'.rex_url::addonAssets('yform_geo_osm', 'leaflet/leaflet.js').'" ></script>
    		<script type="text/javascript" src="'.rex_url::addonAssets('yform_geo_osm', 'geo_osm.js').'" ></script>
    		</body>
		';

        return str_replace(['</head>', '</body>'], [$assets_header, $assets_footer], $ep->getSubject());

    }

    public static function addDynJs($ep)
    {
        $js = $ep->getParam('js');
        return str_replace('</body>', $js.'</body>', $ep->getSubject());
    }

}
