<?php
if (!defined('ABSPATH')) exit;

if (!defined('COAI_GOOGLE_DRIVE_KEY_PATH')) {
  define('COAI_GOOGLE_DRIVE_KEY_PATH', WP_CONTENT_DIR . '/private/coai-google-drive-service.json');
}

function coai_google_export_folder_map() {
  return [
    'South West Region'    => '1SNvH-SLk8WvxBVlR852j95fginqdZrCm',
    'South East Region'    => '1JldexD3ZxEQJhhw6OVeolYfYRjUqDAXS',
    'South Central Region' => '1R7FU62IT4WEqW9_9ozRSNp9eSb9YK2cG',
    'North East Region'    => '1xF0x82zCHJI_hgUnWtVfoktLWzdWRIh8',
    'North Central Region' => '14D480pMGLu6poRMpBKmkRfOo-7AlEs3Y',
    'Mid West Region'      => '1fsmo1jSZvw0xpy5cI1-seqcgLFciiewL',
    'Mid East Region'      => '1HV4DxB43CUCncw7RcojyombpDT0iMADA',
    'Latin Region'         => '1cfP_MmGH9jwDtm-vZwE1i78RVOiwssJ5',
    'International Region' => '10CslTW-a-_zsWKlFU3BiRmZUjPX-9olX',
    'Canada Region'        => '1EXV62GWoJMSLxH5Hfp9eSaXNNKAzgi13',
  ];
}

function coai_google_folder_id_for_region($region) {
  $map = coai_google_export_folder_map();
  return $map[$region] ?? '';
}