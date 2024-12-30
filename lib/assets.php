<?php

namespace FriendsOfRedaxo\YFormGeoOsm;

use rex_extension_point;
use rex_url;

class Assets
{
    /**
     * @api
     * @param rex_extension_point<string> $ep
     */
    public static function addAssets(rex_extension_point $ep): string
    {
        $assets_header = '
			<link rel="stylesheet" type="text/css" media="all" href="' . rex_url::addonAssets('yform_geo_osm', 'leaflet/leaflet.css') . '" />
   			 <link rel="stylesheet" type="text/css" media="all" href="' . rex_url::addonAssets('yform_geo_osm', 'geo_osm.css') . '" />
    		</head>
		';

        $assets_footer = '
   			 <script type="text/javascript" src="' . rex_url::addonAssets('yform_geo_osm', 'leaflet/leaflet.js') . '" ></script>
    		<script type="text/javascript" src="' . rex_url::addonAssets('yform_geo_osm', 'geo_osm.js') . '" ></script>
    		</body>
		';

        return str_replace(['</head>', '</body>'], [$assets_header, $assets_footer], $ep->getSubject());
    }

    /**
     * @api
     * @param rex_extension_point<string> $ep
     */
    public static function addDynJs(rex_extension_point $ep): string
    {
        $js = $ep->getParam('js');
        return str_replace('</body>', $js . '</body>', $ep->getSubject());
    }
}
