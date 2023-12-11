<?php
/**
 * @author Whalestack <service@whalestack.com>
 * @copyright 2022 Whalestack
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Whalestack\Classes;

use Defuse\Crypto\Crypto;
use Whalestack\Sdk;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Helpers
{

    public static function initApi($key, $secret, $log = 1)
    {
        $client = new Sdk\WsMerchantClient($key, $secret, $log);
        return $client;
    }

    public static function getAssets($client)
    {
        $assets = array();
        $response = $client->get('/assets');
        if ($response->httpStatusCode == 200) {
            $items = json_decode($response->responseBody);
            foreach ($items->assets as $asset) {
                if ($asset->settlement === true) {
                    array_push($assets, array(
                        'id_option' => $asset->id,
                        'name' => $asset->assetCode . ' - ' . $asset->name
                    ));
                }
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
        return 'Prestashop ' . _PS_VERSION_ . ', Ws ' . Whalestack_MODULE_VERSION;
    }

    public static function encrypt($string, $salt)
    {
        return Crypto::encryptWithPassword($string, $salt);
    }

    public static function decrypt($string, $salt)
    {
        if (!$string) {
            return null;
        }
        return Crypto::decryptWithPassword($string, $salt);
    }
}
