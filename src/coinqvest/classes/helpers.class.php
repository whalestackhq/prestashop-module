<?php

namespace COINQVEST\Classes;
use COINQVEST\Classes\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Helpers {

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
                array_push($assets, array('id_option' => $asset->assetCode, 'name' => $asset->assetCode . ' - ' . $asset->name));
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
                array_push($languages, array('id_option' => $lang->languageCode, 'name' => $lang->languageCode . ' - ' . $lang->name));

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
        $sSalt = substr(hash('sha256', $salt, true), 0, 32);
        $method = 'aes-256-cbc';
        $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        $encrypted = base64_encode(openssl_encrypt($string, $method, $sSalt, OPENSSL_RAW_DATA, $iv));
        return $encrypted;
    }

    public static function decrypt($string, $salt)
    {
        $sSalt = substr(hash('sha256', $salt, true), 0, 32);
        $method = 'aes-256-cbc';
        $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        $decrypted = openssl_decrypt(base64_decode($string), $method, $sSalt, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}