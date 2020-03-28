<?php
//Change these values below.

define('FEDEX_ACCOUNT_NUMBER', '510087860');
define('FEDEX_METER_NUMBER', '100174621');
define('FEDEX_KEY', 'gTlL7PfNGF9gfqnY');
define('FEDEX_PASSWORD', 'UXS9yXMzq3N03hVCYJNGskIQb');


if (!defined('FEDEX_ACCOUNT_NUMBER') || !defined('FEDEX_METER_NUMBER') || !defined('FEDEX_KEY') || !defined('FEDEX_PASSWORD')) {
    die("The constants 'FEDEX_ACCOUNT_NUMBER', 'FEDEX_METER_NUMBER', 'FEDEX_KEY', and 'FEDEX_PASSWORD' need to be defined in: " . realpath(__FILE__));
}
