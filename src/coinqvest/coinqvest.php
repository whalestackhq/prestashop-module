<?php
/**
 * COINQVEST - Cryptocurrency Payment Gateway Module for PrestaShop 1.7
 *
 * This file is the declaration of the module.
 *
 * @author COINQVEST <service@coinqvest.com>
 * @copyright 2023 COINQVEST
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use COINQVEST\Classes\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

include('classes/helpers.class.php');
include('sdk/CQMerchantClient.class.php');

class Coinqvest extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    private $apiKey;
    private $apiSecret;

    public function __construct()
    {
        $this->name                   = 'coinqvest';
        $this->tab                    = 'payments_gateways';
        $this->version                = '1.0.2';
        $this->author                 = 'COINQVEST';
        $this->controllers            = array('validation', 'webhook');
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;
        $this->displayName            = $this->l('COINQVEST Cryptocurrency Payment Processing');
        $this->description            = $this->l('Cryptocurrency payment gateway. Accept Bitcoin and other digital currencies on your PrestaShop and directly settle in your local currency.');
        $this->confirmUninstall       = $this->l('Are you sure you want to uninstall the COINQVEST module?');
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);
        parent::__construct();

        define('COINQVEST_MODULE_VERSION', $this->version);

        $this->apiKey = Configuration::get('COINQVEST_API_KEY');
        $this->apiSecret = Configuration::get('COINQVEST_API_SECRET');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('paymentTop')
            && $this->registerHook('displayAdminOrderTabContent')
            && $this->addConfigFields()
            && $this->addOrderStates()
            && $this->createCoinqvestTable();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('COINQVEST_TITLE')
            && Configuration::deleteByName('COINQVEST_DESC')
            && Configuration::deleteByName('COINQVEST_LOGO_DISPLAY')
            && Configuration::deleteByName('COINQVEST_LOGGING')
            && Configuration::deleteByName('COINQVEST_API_KEY')
            && Configuration::deleteByName('COINQVEST_API_SECRET')
            && Configuration::deleteByName('COINQVEST_SETTLEMENT_ASSET')
            && Configuration::deleteByName('COINQVEST_CHECKOUT_LANGUAGE')
            && Configuration::deleteByName('COINQVEST_HASH');
    }

    public function addConfigFields()
    {
        Configuration::updateValue('COINQVEST_TITLE', 'COINQVEST - Pay with Bitcoin and other digital currencies');
        Configuration::updateValue('COINQVEST_DESC', 'Pay with Bitcoin, Ethereum, Litecoin or other digital currencies.');
        Configuration::updateValue('COINQVEST_SETTLEMENT_ASSET', 'ORIGIN');
        Configuration::updateValue('COINQVEST_LOGO_DISPLAY', 1);
        Configuration::updateValue('COINQVEST_LOGGING', 1);
        if (!Configuration::get('COINQVEST_HASH')) {
            Configuration::updateValue('COINQVEST_HASH', $this->generateRandomString(30));
        }
        return true;
    }

    public function createCoinqvestTable()
    {
        if(!Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'coinqvest_orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT DEFAULT NULL,
                `coinqvest_checkout_id` VARCHAR(20) DEFAULT NULL,
                `coinqvest_refund_id` VARCHAR(20) DEFAULT NULL,
                PRIMARY KEY (`id`)
            );'
        )) {
            $logger = new Coinqvest\Sdk\CQLoggingService();
            $logger::write(print_r('[Install] Failed to create table coinqvest_orders during module installation.', true));
            return false;
        }
        return true;
    }

    public function addOrderStates()
    {
        if (!Configuration::get('COINQVEST_PENDING')) {
            $orderStatePending = new OrderState();
            $orderStatePending->name = array_fill(0, 10, 'Awaiting COINQVEST payment');
            $orderStatePending->module_name = 'COINQVEST';
            $orderStatePending->color = '#6fcff5';
            if ($orderStatePending->add()) {
                Configuration::updateValue('COINQVEST_PENDING', $orderStatePending->id);
            }
        }

        if (!Configuration::get('COINQVEST_UNDERPAID')) {
            $orderStateUnderpaid = new OrderState();
            $orderStateUnderpaid->name = array_fill(0, 10, 'Underpaid COINQVEST payment');
            $orderStateUnderpaid->module_name = 'COINQVEST';
            $orderStateUnderpaid->color = '#5fbf77';
            if ($orderStateUnderpaid->add()) {
                Configuration::updateValue('COINQVEST_UNDERPAID', $orderStateUnderpaid->id);
            }
        }
        return true;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name)) {
            $this->validateConfigForm();
            if (!count($this->_postErrors)) {
                $this->processConfigForm();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->displayCoinqvestInfo();
        $this->_html .= $this->renderConfigForm();
        return $this->_html;
    }

    protected function validateConfigForm()
    {
        if (Tools::isSubmit('submit' . $this->name)) {


            if (!Tools::getValue('COINQVEST_API_KEY')) {
                $this->_postErrors[] = $this->l('Api Key is required.');
                return;
            }

            if (strlen(Tools::getValue('COINQVEST_API_KEY')) != 12) {
                $this->_postErrors[] = $this->l('API Key seems to be wrong. Please double check.');
                return;
            }

            if (!Tools::getValue('COINQVEST_API_SECRET')) {
                $this->_postErrors[] = $this->l('Api Secret is required.');
                return;
            }

            if (strlen(Tools::getValue('COINQVEST_API_SECRET')) != 29) {
                $this->_postErrors[] = $this->l('API Secret seems to be wrong. Please double check.');
                return;
            }

            $client = Helpers::initApi(Tools::getValue('COINQVEST_API_KEY'), Tools::getValue('COINQVEST_API_SECRET'), Tools::getValue('COINQVEST_LOGGING'));
            $response = $client->get('/auth-test');
            if ($response->httpStatusCode != 200) {
                $this->_postErrors[] = $this->l('API Key and Secret don\'t match. Please double check.');
                return;
            }

            if (!Tools::getValue('COINQVEST_TITLE')) {
                $this->_postErrors[] = $this->l('Title is required.');
                return;
            }

            if (!Tools::getValue('COINQVEST_DESC')) {
                $this->_postErrors[] = $this->l('Description is required.');
                return;
            }

            if (!Tools::getValue('COINQVEST_SETTLEMENT_ASSET') || Tools::getValue('COINQVEST_SETTLEMENT_ASSET') == "0") {
                $this->_postErrors[] = $this->l('Please choose a settlement asset.');
                return;
            }
        }
    }

    protected function processConfigForm()
    {
        if (Tools::isSubmit('submit' . $this->name))
        {
            Configuration::updateValue('COINQVEST_API_KEY', Tools::getValue('COINQVEST_API_KEY'));
            Configuration::updateValue('COINQVEST_API_SECRET', Helpers::encrypt(Tools::getValue('COINQVEST_API_SECRET'), Configuration::get('COINQVEST_HASH')));
            Configuration::updateValue('COINQVEST_TITLE', Tools::getValue('COINQVEST_TITLE'));
            Configuration::updateValue('COINQVEST_DESC', Tools::getValue('COINQVEST_DESC'));
            Configuration::updateValue('COINQVEST_SETTLEMENT_ASSET', Tools::getValue('COINQVEST_SETTLEMENT_ASSET'));
            Configuration::updateValue('COINQVEST_CHECKOUT_LANGUAGE', Tools::getValue('COINQVEST_CHECKOUT_LANGUAGE'));
            Configuration::updateValue('COINQVEST_LOGO_DISPLAY', Tools::getValue('COINQVEST_LOGO_DISPLAY'));
            Configuration::updateValue('COINQVEST_LOGGING', Tools::getValue('COINQVEST_LOGGING'));
            $this->_html = $this->displayConfirmation($this->l('Settings updated'));
        }
    }

    protected function displayCoinqvestInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function renderConfigForm()
    {
        $client = Helpers::initApi($this->apiKey, Helpers::decrypt(Configuration::get('COINQVEST_API_SECRET'), Configuration::get('COINQVEST_HASH')), Configuration::get('COINQVEST_LOGGING'));

        $settlementAssets = array(
            array('id_option' => '0', 'name' => 'Select Settlement Asset...'),
            array('id_option' => 'ORIGIN', 'name' => 'ORIGIN - Settle to the cryptocurrency your client pays with')
        );
        $assets = Helpers::getAssets($client);
        $settlementAssets = array_merge($settlementAssets, $assets);

        $checkoutLanguages = array(
            array('id_option' => '0', 'name' => 'Select language...'),
            array('id_option' => 'auto', 'name' => 'auto - Automatic')
        );
        $languages = Helpers::getCheckoutLanguages($client);
        $checkoutLanguages = array_merge($checkoutLanguages, $languages);

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your API Key'),
                        'name' => 'COINQVEST_API_KEY',
                        'desc' => sprintf($this->l('Get your API Key from the COINQVEST Settings page, available %s.'), '<a href="https://www.coinqvest.com/en/api-settings?utm_source=prestashop&utm_medium=' . $_SERVER['SERVER_NAME'] . '" target="_blank">here</a>'),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your API Secret'),
                        'name' => 'COINQVEST_API_SECRET',
                        'desc' => sprintf($this->l('Get your API Secret from the COINQVEST Settings page, available %s.'), '<a href="https://www.coinqvest.com/en/api-settings?utm_source=prestashop&utm_medium=' . $_SERVER['SERVER_NAME'] . '" target="_blank">here</a>'),
                        'size' => 40,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Payment Option Name'),
                        'name' => 'COINQVEST_TITLE',
                        'desc' => $this->l('The name of the payment option which the user sees during checkout.'),
                        'size' => 200,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Description'),
                        'name' => 'COINQVEST_DESC',
                        'desc' => $this->l('This controls the description which the user sees during checkout.'),
                        'size' => 200,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Settlement Asset'),
                        'name' => 'COINQVEST_SETTLEMENT_ASSET',
                        'desc' => $this->l('The currency that the crypto payments get converted to. Choose ORIGIN if you want to get credited in the exact same currency your customer paid in (without any conversion). API credentials must be provided before currency options show up.'),
                        'options' => array(
                            'query' => $settlementAssets,
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                        'required' => true
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Checkout Page Language'),
                        'name' => 'COINQVEST_CHECKOUT_LANGUAGE',
                        'desc' => $this->l('The language that your checkout page will display in. Choose \'auto\' to automatically detect the customer\'s main browser language. Fallback language code is \'en\'.'),
                        'options' => array(
                            'query' => $checkoutLanguages,
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true,
                        'label' => $this->l('Show Logo'),
                        'name' => 'COINQVEST_LOGO_DISPLAY',
                        'desc' => $this->l('Display COINQVEST logo on checkout page'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true,
                        'label' => $this->l('Debug Log'),
                        'name' => 'COINQVEST_LOGGING',
                        'desc' => sprintf($this->l('Logs COINQVEST API responses in file %1$s. Also find %2$s and %3$s directly in your COINQVEST account.'), '<code>' . _PS_ROOT_DIR_. '/var/logs/coinqvest.log</code>', '<a href="https://www.coinqvest.com/en/api-logs" target="_blank">API logs</a>', '<a href="https://www.coinqvest.com/en/webhook-logs" target="_blank">Webhook logs</a>'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ],
                    [
                        'type' => 'free',
                        'label' => $this->l('White Label Setup'),
                        'name' => 'COINQVEST_WHITELABEL',
                        'desc' => sprintf($this->l('Customize the checkout page to your brand\'s look and feel directly in the %s settings of your COINQVEST account.'), '<a href="https://www.coinqvest.com/en/account-settings#brandingConfigs" target="_blank">Brand Connect</a>'),
                    ]

                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = $this->getConfigFormValues();
        return $helper->generateForm([$form]);
    }

    public function getConfigFormValues()
    {
        $fields_value = array();
        $fields_value['COINQVEST_API_KEY'] = Tools::getValue('COINQVEST_API_KEY', Configuration::get('COINQVEST_API_KEY'));
        $fields_value['COINQVEST_API_SECRET'] = Tools::getValue('COINQVEST_API_SECRET', Helpers::decrypt(Configuration::get('COINQVEST_API_SECRET'), Configuration::get('COINQVEST_HASH')));
        $fields_value['COINQVEST_TITLE'] = Tools::getValue('COINQVEST_TITLE', Configuration::get('COINQVEST_TITLE'));
        $fields_value['COINQVEST_DESC'] = Tools::getValue('COINQVEST_DESC', Configuration::get('COINQVEST_DESC'));
        $fields_value['COINQVEST_SETTLEMENT_ASSET'] = Tools::getValue('COINQVEST_SETTLEMENT_ASSET', Configuration::get('COINQVEST_SETTLEMENT_ASSET'));
        $fields_value['COINQVEST_CHECKOUT_LANGUAGE'] = Tools::getValue('COINQVEST_CHECKOUT_LANGUAGE', Configuration::get('COINQVEST_CHECKOUT_LANGUAGE'));
        $fields_value['COINQVEST_LOGO_DISPLAY'] = Tools::getValue('COINQVEST_LOGO_DISPLAY', Configuration::get('COINQVEST_LOGO_DISPLAY'));
        $fields_value['COINQVEST_LOGGING'] = Tools::getValue('COINQVEST_LOGGING', Configuration::get('COINQVEST_LOGGING'));
        $fields_value['COINQVEST_WHITELABEL'] = Tools::getValue('COINQVEST_WHITELABEL', Configuration::get('COINQVEST_WHITELABEL'));
        return $fields_value;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);
        $this->smarty->assign(['action' => $formAction, 'displayLogo' => Configuration::get('COINQVEST_LOGO_DISPLAY')]);
        $paymentForm = $this->fetch('module:coinqvest/views/templates/hook/payment_options.tpl');

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)
            ->setCallToActionText(Configuration::get('COINQVEST_TITLE'))
            ->setAction($formAction)
            ->setForm($paymentForm);

        $payment_options = array(
            $newOption
        );
        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        return $this->fetch('module:coinqvest/views/templates/hook/payment_return.tpl');
    }

    public function hookPaymentTop($params)
    {
        if (!$this->active) {
            return;
        }

        $error = isset($_GET['error']) ? pSQL($_GET['error']) : null;
        $this->context->smarty->assign(array('error' => $error));
        return $this->fetch('module:coinqvest/views/templates/hook/payment_top.tpl');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        if (!$this->active) {
            return;
        }

        $order = new Order($params['id_order']);
        $sql = "SELECT `coinqvest_checkout_id`, `coinqvest_refund_id` FROM " . _DB_PREFIX_ . "coinqvest_orders WHERE order_id = " . (int)$order->id;
        $result = Db::getInstance()->getRow($sql);
        $checkoutId = !is_null ($result['coinqvest_checkout_id']) ? $result['coinqvest_checkout_id'] : null;
        $refundId = !is_null ($result['coinqvest_refund_id']) ? $result['coinqvest_refund_id'] : null;
        $display = null;

        $orderHistory = $order->getHistory($order->id_lang);
        $orderHistoryStates = array();
        foreach ($orderHistory as $item)
        {
            array_push($orderHistoryStates, $item['id_order_state']);
        }

        if (in_array(Configuration::get('COINQVEST_UNDERPAID'), $orderHistoryStates) && !in_array(Configuration::get('PS_OS_PAYMENT'), $orderHistoryStates))
        {
            $display = 'underpaid';
        }

        if (in_array(Configuration::get('PS_OS_PAYMENT'), $orderHistoryStates))
        {
            $display = 'paid';
        }

        if (isset($refundId)) {
            $display = 'refunded';
        }

        $this->context->smarty->assign(array('checkoutId' => $checkoutId, 'refundId' => $refundId, 'display' => $display));
        return $this->fetch('module:coinqvest/views/templates/hook/order_details.tpl');
    }

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = Tools::strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}