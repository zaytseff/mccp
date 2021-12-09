<?php

if (!defined('ABSPATH'))
  exit; // Exit if accessed directly

// URL API of payment gateway
// This is Apirone Bitcoin Forwarding RESTful API query
// You can read more details at https://apirone.com/docs/bitcoin-forwarding-api

define('MCCP_API', 'https://apirone.com/api'); // Apirone API url

define('MCCP_ADDRESS_URL', 'https://apirone.com/api/v1/receive');

define('MCCP_CURRENCY_ICON', 'https://apirone.com/static/img2/%s.svg');

define('MCCP_MAX_CONFIRMATIONS', '30'); // if 0 - max confirmations count is unlimited, -1 - function is disabled

define('MCCP_DUST_RATE', 1000); // Set currency dusr rate if not set

define('MCCP_ZERO_TRIM', false); // Default setting to trim tail zeros for cryptocurrency

define('MCCP_SHOP_URL', site_url()); // take Site URL for callbacks
