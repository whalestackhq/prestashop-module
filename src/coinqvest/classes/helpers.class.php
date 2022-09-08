<?php
/**
 * @author COINQVEST <service@coinqvest.com>
 * @copyright 2022 COINQVEST
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

namespace COINQVEST\Classes;

use Defuse\Crypto\Crypto;
use COINQVEST\Classes\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Helpers
{

    public static function initApi($key, $secret, $log = 1)
    {
        $client = new Api\CQMerchantClient($key, $secret, $log);
        return $client;
    }

    public static function getAssets($client)
    {
        $assets = array();
        $response = $client->get('/assets');
        if ($response->httpStatusCode == 200) {
            $items = json_decode($response->responseBody);
            foreach ($items->assets as $asset) {
                array_push($assets, array(
                    'id_option' => $asset->assetCode,
                    'name' => $asset->assetCode . ' - ' . $asset->name
                ));
            }
        }
        return $assets;
    }

    public static function getCheckoutLanguages($client)
    {
        $languages = array();
        $response = $client->get('/languages');
        if ($response->httpStatusCode == 200) {
            $langs = json_decode($response->responseBody);
            foreach ($langs->languages as $lang) {
                array_push($languages, array(
                    'id_option' => $lang->languageCode,
                    'name' => $lang->languageCode . ' - ' . $lang->name
                ));
            }
        }
        return $languages;
    }

    public static function getModuleVersion()
    {
        return 'Prestashop ' . _PS_VERSION_ . ', CQ ' . COINQVEST_MODULE_VERSION;
    }

    public static function encrypt($string, $salt)
    {

        return Crypto::encryptWithPassword($string, $salt);
    }

    public static function decrypt($string, $salt)
    {
        if (!$string)
        {
            return null;
        }
        return Crypto::decryptWithPassword($string, $salt);
    }
}
