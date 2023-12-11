<?php
/**
 * Whalestack - Cryptocurrency Payment Gateway Module for PrestaShop 8
 *
 * This file is the declaration of the module.
 *
 * @author Whalestack <service@whalestack.com>
 * @copyright 2023 Whalestack
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Whalestack\Classes\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

include('classes/helpers.class.php');
include('sdk/WsMerchantClient.class.php');

class Whalestack extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    private $apiKey;
    private $apiSecret;

    public function __construct()
    {
        $this->name                   = 'whalestack';
        $this->tab                    = 'payments_gateways';
        $this->version                = '2.0.0';
        $this->author                 = 'Whalestack';
        $this->controllers            = array('validation', 'webhook');
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;
        $this->displayName            = $this->l('Whalestack Cryptocurrency Payment Processing');
        $this->description            = $this->l('Cryptocurrency payment gateway. Accept Bitcoin, stablecoins (EURC, USDC), and other cryptocurrencies and directly settle in your preferred currency.');
        $this->confirmUninstall       = $this->l('Are you sure you want to uninstall the Whalestack module?');
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);
        parent::__construct();

        define('Whalestack_MODULE_VERSION', $this->version);

        $this->apiKey = Configuration::get('Whalestack_API_KEY');
        $this->apiSecret = Configuration::get('Whalestack_API_SECRET');
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
            && $this->createWhalestackTable();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('Whalestack_TITLE')
            && Configuration::deleteByName('Whalestack_DESC')
            && Configuration::deleteByName('Whalestack_LOGO_DISPLAY')
            && Configuration::deleteByName('Whalestack_LOGGING')
            && Configuration::deleteByName('Whalestack_API_KEY')
            && Configuration::deleteByName('Whalestack_API_SECRET')
            && Configuration::deleteByName('Whalestack_SETTLEMENT_ASSET')
            && Configuration::deleteByName('Whalestack_CHECKOUT_LANGUAGE')
            && Configuration::deleteByName('Whalestack_HASH');
    }

    public function addConfigFields()
    {
        Configuration::updateValue('Whalestack_TITLE', 'Whalestack - Pay with Bitcoin and stablecoins');
        Configuration::updateValue('Whalestack_DESC', 'Pay with Bitcoin, other crypto and stablecoins like EURC and USDC.');
        Configuration::updateValue('Whalestack_SETTLEMENT_ASSET', 'ORIGIN');
        Configuration::updateValue('Whalestack_LOGO_DISPLAY', 1);
        Configuration::updateValue('Whalestack_LOGGING', 1);
        if (!Configuration::get('Whalestack_HASH')) {
            Configuration::updateValue('Whalestack_HASH', $this->generateRandomString(30));
        }
        return true;
    }

    public function createWhalestackTable()
    {
        if(!Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'whalestack_orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT DEFAULT NULL,
                `whalestack_checkout_id` VARCHAR(20) DEFAULT NULL,
                `whalestack_refund_id` VARCHAR(20) DEFAULT NULL,
                PRIMARY KEY (`id`)
            );'
        )) {
            $logger = new Whalestack\Sdk\WsLoggingService();
            $logger::write(print_r('[Install] Failed to create table whalestack_orders during module installation.', true));
            return false;
        }
        return true;
    }

    public function addOrderStates()
    {
        if (!Configuration::get('Whalestack_PENDING')) {
            $orderStatePending = new OrderState();
            $orderStatePending->name = array_fill(0, 10, 'Awaiting Whalestack payment');
            $orderStatePending->module_name = 'Whalestack';
            $orderStatePending->color = '#6fcff5';
            if ($orderStatePending->add()) {
                Configuration::updateValue('Whalestack_PENDING', $orderStatePending->id);
            }
        }

        if (!Configuration::get('Whalestack_UNDERPAID')) {
            $orderStateUnderpaid = new OrderState();
            $orderStateUnderpaid->name = array_fill(0, 10, 'Underpaid Whalestack payment');
            $orderStateUnderpaid->module_name = 'Whalestack';
            $orderStateUnderpaid->color = '#5fbf77';
            if ($orderStateUnderpaid->add()) {
                Configuration::updateValue('Whalestack_UNDERPAID', $orderStateUnderpaid->id);
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

        $this->_html .= $this->displayWhalestackInfo();
        $this->_html .= $this->renderConfigForm();
        return $this->_html;
    }

    protected function validateConfigForm()
    {
        if (Tools::isSubmit('submit' . $this->name)) {


            if (!Tools::getValue('Whalestack_API_KEY')) {
                $this->_postErrors[] = $this->l('Api Key is required.');
                return;
            }

            if (strlen(Tools::getValue('Whalestack_API_KEY')) != 12) {
                $this->_postErrors[] = $this->l('API Key seems to be wrong. Please double check.');
                return;
            }

            if (!Tools::getValue('Whalestack_API_SECRET')) {
                $this->_postErrors[] = $this->l('Api Secret is required.');
                return;
            }

            if (strlen(Tools::getValue('Whalestack_API_SECRET')) != 29) {
                $this->_postErrors[] = $this->l('API Secret seems to be wrong. Please double check.');
                return;
            }

            $client = Helpers::initApi(Tools::getValue('Whalestack_API_KEY'), Tools::getValue('Whalestack_API_SECRET'), Tools::getValue('Whalestack_LOGGING'));
            $response = $client->get('/auth-test');
            if ($response->httpStatusCode != 200) {
                $this->_postErrors[] = $this->l('API Key and Secret don\'t match. Please double check.');
                return;
            }

            if (!Tools::getValue('Whalestack_TITLE')) {
                $this->_postErrors[] = $this->l('Title is required.');
                return;
            }

            if (!Tools::getValue('Whalestack_DESC')) {
                $this->_postErrors[] = $this->l('Description is required.');
                return;
            }

            if (!Tools::getValue('Whalestack_SETTLEMENT_ASSET') || Tools::getValue('Whalestack_SETTLEMENT_ASSET') == "0") {
                $this->_postErrors[] = $this->l('Please choose a settlement asset.');
                return;
            }
        }
    }

    protected function processConfigForm()
    {
        if (Tools::isSubmit('submit' . $this->name))
        {
            Configuration::updateValue('Whalestack_API_KEY', Tools::getValue('Whalestack_API_KEY'));
            Configuration::updateValue('Whalestack_API_SECRET', Helpers::encrypt(Tools::getValue('Whalestack_API_SECRET'), Configuration::get('Whalestack_HASH')));
            Configuration::updateValue('Whalestack_TITLE', Tools::getValue('Whalestack_TITLE'));
            Configuration::updateValue('Whalestack_DESC', Tools::getValue('Whalestack_DESC'));
            Configuration::updateValue('Whalestack_SETTLEMENT_ASSET', Tools::getValue('Whalestack_SETTLEMENT_ASSET'));
            Configuration::updateValue('Whalestack_CHECKOUT_LANGUAGE', Tools::getValue('Whalestack_CHECKOUT_LANGUAGE'));
            Configuration::updateValue('Whalestack_LOGO_DISPLAY', Tools::getValue('Whalestack_LOGO_DISPLAY'));
            Configuration::updateValue('Whalestack_LOGGING', Tools::getValue('Whalestack_LOGGING'));
            $this->_html = $this->displayConfirmation($this->l('Settings updated'));
        }
    }

    protected function displayWhalestackInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function renderConfigForm()
    {
        $client = Helpers::initApi($this->apiKey, Helpers::decrypt(Configuration::get('Whalestack_API_SECRET'), Configuration::get('Whalestack_HASH')), Configuration::get('Whalestack_LOGGING'));

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
                        'name' => 'Whalestack_API_KEY',
                        'desc' => sprintf($this->l('Get your API Key from the Whalestack Settings page, available %s.'), '<a href="https://www.whalestack.com/en/api-settings?utm_source=prestashop&utm_medium=' . $_SERVER['SERVER_NAME'] . '" target="_blank">here</a>'),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your API Secret'),
                        'name' => 'Whalestack_API_SECRET',
                        'desc' => sprintf($this->l('Get your API Secret from the Whalestack Settings page, available %s.'), '<a href="https://www.whalestack.com/en/api-settings?utm_source=prestashop&utm_medium=' . $_SERVER['SERVER_NAME'] . '" target="_blank">here</a>'),
                        'size' => 40,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Payment Option Name'),
                        'name' => 'Whalestack_TITLE',
                        'desc' => $this->l('The name of the payment option which the user sees during checkout.'),
                        'size' => 200,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Description'),
                        'name' => 'Whalestack_DESC',
                        'desc' => $this->l('This controls the description which the user sees during checkout.'),
                        'size' => 200,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Settlement Asset'),
                        'name' => 'Whalestack_SETTLEMENT_ASSET',
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
                        'name' => 'Whalestack_CHECKOUT_LANGUAGE',
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
                        'name' => 'Whalestack_LOGO_DISPLAY',
                        'desc' => $this->l('Display Whalestack logo on checkout page'),
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
                        'name' => 'Whalestack_LOGGING',
                        'desc' => sprintf($this->l('Logs Whalestack API responses in file %1$s. Also find %2$s and %3$s directly in your Whalestack account.'), '<code>' . _PS_ROOT_DIR_. '/var/logs/whalestack.log</code>', '<a href="https://www.whalestack.com/en/api-logs" target="_blank">API logs</a>', '<a href="https://www.whalestack.com/en/webhook-logs" target="_blank">Webhook logs</a>'),
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
                        'name' => 'Whalestack_WHITELABEL',
                        'desc' => sprintf($this->l('Customize the checkout page to your brand\'s look and feel directly in the %s settings of your Whalestack account.'), '<a href="https://www.whalestack.com/en/account-settings#brandingConfigs" target="_blank">Brand Connect</a>'),
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
        $fields_value['Whalestack_API_KEY'] = Tools::getValue('Whalestack_API_KEY', Configuration::get('Whalestack_API_KEY'));
        $fields_value['Whalestack_API_SECRET'] = Tools::getValue('Whalestack_API_SECRET', Helpers::decrypt(Configuration::get('Whalestack_API_SECRET'), Configuration::get('Whalestack_HASH')));
        $fields_value['Whalestack_TITLE'] = Tools::getValue('Whalestack_TITLE', Configuration::get('Whalestack_TITLE'));
        $fields_value['Whalestack_DESC'] = Tools::getValue('Whalestack_DESC', Configuration::get('Whalestack_DESC'));
        $fields_value['Whalestack_SETTLEMENT_ASSET'] = Tools::getValue('Whalestack_SETTLEMENT_ASSET', Configuration::get('Whalestack_SETTLEMENT_ASSET'));
        $fields_value['Whalestack_CHECKOUT_LANGUAGE'] = Tools::getValue('Whalestack_CHECKOUT_LANGUAGE', Configuration::get('Whalestack_CHECKOUT_LANGUAGE'));
        $fields_value['Whalestack_LOGO_DISPLAY'] = Tools::getValue('Whalestack_LOGO_DISPLAY', Configuration::get('Whalestack_LOGO_DISPLAY'));
        $fields_value['Whalestack_LOGGING'] = Tools::getValue('Whalestack_LOGGING', Configuration::get('Whalestack_LOGGING'));
        $fields_value['Whalestack_WHITELABEL'] = Tools::getValue('Whalestack_WHITELABEL', Configuration::get('Whalestack_WHITELABEL'));
        return $fields_value;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);
        $this->smarty->assign(['action' => $formAction, 'displayLogo' => Configuration::get('Whalestack_LOGO_DISPLAY')]);
        $paymentForm = $this->fetch('module:whalestack/views/templates/hook/payment_options.tpl');

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)
            ->setCallToActionText(Configuration::get('Whalestack_TITLE'))
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

        return $this->fetch('module:whalestack/views/templates/hook/payment_return.tpl');
    }

    public function hookPaymentTop($params)
    {
        if (!$this->active) {
            return;
        }

        $error = Tools::getIsset(Tools::getValue('error')) ? pSQL(Tools::getValue('error')) : null;
//        $error = !empty(Tools::getValue('error')) ? pSQL(Tools::getValue('error')) : null;
        $this->context->smarty->assign(array('error' => $error));
        return $this->fetch('module:whalestack/views/templates/hook/payment_top.tpl');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        if (!$this->active) {
            return;
        }

        $order = new Order($params['id_order']);
        $sql = "SELECT `whalestack_checkout_id`, `whalestack_refund_id` FROM " . _DB_PREFIX_ . "whalestack_orders WHERE order_id = " . (int)$order->id;
        $result = Db::getInstance()->getRow($sql);
        $checkoutId = !is_null ($result['whalestack_checkout_id']) ? $result['whalestack_checkout_id'] : null;
        $refundId = !is_null ($result['whalestack_refund_id']) ? $result['whalestack_refund_id'] : null;
        $display = null;

        $orderHistory = $order->getHistory($order->id_lang);
        $orderHistoryStates = array();
        foreach ($orderHistory as $item)
        {
            array_push($orderHistoryStates, $item['id_order_state']);
        }

        if (in_array(Configuration::get('Whalestack_UNDERPAID'), $orderHistoryStates) && !in_array(Configuration::get('PS_OS_PAYMENT'), $orderHistoryStates))
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
        return $this->fetch('module:whalestack/views/templates/hook/order_details.tpl');
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