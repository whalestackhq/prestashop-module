<?php
use Coinqvest\Classes\Helpers;

class CoinqvestValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        $authorized = false;

        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'coinqvest') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $this->module->validateOrder(
            (int) $this->context->cart->id,
            Configuration::get('COINQVEST_PENDING'),
            (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
            'COINQVEST',
            null,
            null,
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order($this->module->currentOrder);
        $cart = new Cart($order->id_cart);
        $currency = new Currency($order->id_currency);
        $address = new Address($order->id_address_invoice);
        $country = new Country($address->id_country);
        $carrier = new Carrier($order->id_carrier);
        $orderDetails = new OrderDetail();
        $products = $orderDetails->getList($order->id);
        $link = new Link();
        $logger = new Coinqvest\Sdk\CQLoggingService();

        $client = Helpers::initApi(Configuration::get('COINQVEST_API_KEY'), Helpers::decrypt(Configuration::get('COINQVEST_API_SECRET'), Configuration::get('COINQVEST_HASH')), Configuration::get('COINQVEST_LOGGING'));

        $cqCustomer = array(
            'email' => $customer->email,
            'firstname' => !empty($address->firstname) ? $address->firstname : null,
            'lastname' => !empty($address->lastname) ? $address->lastname : null,
            'company' => !empty($address->company) ? $address->company : null,
            'adr1' => !empty($address->address1) ? $address->address1 : null,
            'adr2' => !empty($address->address2) ? $address->address2 : null,
            'zip' => !empty($address->postcode) ? $address->postcode : null,
            'city' => !empty($address->city) ? $address->city : null,
            'countrycode' => !empty($country->iso_code) ? $country->iso_code : null,
            'phonenumber' => !empty($address->phone) ? $address->phone : null,
            'meta' => array(
                'source' => 'Prestashop'
            )
        );

        $response = $client->post('/customer', array('customer' => $cqCustomer));
        $data = json_decode($response->responseBody, true);

        if ($response->httpStatusCode != 200) {
            $logger::write(print_r('[Validation] Failed to create customer. ' . json_encode($cqCustomer), true));
            $error = $this->l($data['errors'][0]);
            Tools::redirect($link->getPageLink('order', true, null, 'step=1&error=' . $error));
        }

        $customer_id = $response->httpStatusCode == 200 ? $data['customerId'] : null;

        $lineItems = array();
        if (isset($products) && !empty($products)) {
            foreach($products as $item) {
                $lineItem = array(
                    "description" => $item['product_name'],
                    "netAmount" => $item['unit_price_tax_excl'],
                    "quantity" => $item['product_quantity'],
                    "productId" =>  (string) $item['product_id']
                );
                array_push($lineItems, $lineItem);
            }
        }

        $discountItems = array();
        if ($order->total_discounts_tax_excl != 0) {
            $discountItem = array(
                "description" => $this->l('Discount'),
                "netAmount" => $order->total_discounts_tax_excl
            );
            array_push($discountItems, $discountItem);
        }

        $shippingCostItems = array();
        if (isset($carrier)) {
            $shippingCostItem = array(
                "description" => $carrier->name,
                "netAmount" => $order->total_shipping_tax_excl,
                "taxable" => $order->carrier_tax_rate == 0 ? false : true
            );
            array_push($shippingCostItems, $shippingCostItem);
        }

        $taxItems = array();
        if (isset($products) && !empty($products) && $products[0]['tax_rate'] != 0) {
            $taxItem = array(
                "name" => $products[0]['tax_name'],
                "percent" => $products[0]['tax_rate'] / 100
            );
            array_push($taxItems, $taxItem);
        }

        $checkout = array(
            "charge" => array(
                "customerId" => $customer_id,
                "billingCurrency" => $currency->iso_code,
                "lineItems" => $lineItems,
                "discountItems" => !empty($discountItems) ? $discountItems : null,
                "shippingCostItems" => !empty($shippingCostItems) ? $shippingCostItems : null,
                "taxItems" => !empty($taxItems) ? $taxItems : null
            )
        );

        $response = $client->post('/checkout/validate-checkout-charge', $checkout);
        $data = json_decode($response->responseBody, true);

        if ($response->httpStatusCode != 200) {
            $logger::write(print_r('[Validation] Failed to validate checkout. ' . json_encode($checkout), true));
            $error = $data['errors'][0];
            Tools::redirect($link->getPageLink('order', true, null, 'step=1&error=' . $error));
        }

        $decimals = (int) strpos(strrev((float)$order->total_paid), ".");

        if ($order->total_paid != round($data['total'], $decimals)) {

            $checkout['charge'] = array(
                "customerId" => $customer_id,
                "billingCurrency" => $currency->iso_code,
                "lineItems" => array(
                    array(
                        "description" => $this->l('Order No.') . ' ' . $order->id,
                        "netAmount" => $order->total_paid
                    )
                )
            );
        }

        $settlement_currency = Configuration::get('COINQVEST_SETTLEMENT_ASSET');
        if (isset($settlement_currency) && $settlement_currency != "0") {
            $checkout['settlementAsset'] = $settlement_currency;
        }

        $checkout_language = Configuration::get('COINQVEST_CHECKOUT_LANGUAGE');
        if (isset($checkout_language) && $checkout_language != "0") {
            $checkout['checkoutLanguage'] = $checkout_language;
        }

        $returnUrl = $link->getPageLink('order-confirmation', true, null, array(
            'id_cart'     => $cart->id,
            'id_module'   => $this->module->id,
            'id_order'    => $this->module->currentOrder,
            'key'         => $customer->secure_key
        ));
        $cancelUrl = $link->getPageLink('order', true, null, 'step=3');

        $checkout['webhook'] = $link->getModuleLink('coinqvest', 'webhook');
        $checkout['pageSettings']['returnUrl'] = $returnUrl;
        $checkout['pageSettings']['cancelUrl'] = $cancelUrl;
        $checkout['meta'] = array('prestashopOrderId' => $order->id);

        $response = $client->post('/checkout/hosted', $checkout);
        $data = json_decode($response->responseBody, true);

        if ($response->httpStatusCode != 200) {
            $logger::write(print_r('[Validation] Failed to create checkout. ' . json_encode($checkout), true));
            $error = $this->l($data['errors'][0]);
            Tools::redirect($link->getPageLink('order', true, null, 'step=1&error=' . $error));
        }

        $id = $data['id'];
        $url = $data['url'];

        $sql = "INSERT INTO " . _DB_PREFIX_ . "coinqvest_orders (`order_id`, `coinqvest_checkout_id`) VALUES (" . (int)$order->id . ", '" . pSQL($id) . "')";
        if (!Db::getInstance()->execute($sql)) {
            $logger::write(print_r('[Validation] Failed to insert into table coinqvest_orders (Prestashop Order Id ' . $order->id . ', COINQVEST Checkout Id ' . $id . ')', true));
            Tools::redirect($cancelUrl);
        }

        Tools::redirect($url);

    }
}