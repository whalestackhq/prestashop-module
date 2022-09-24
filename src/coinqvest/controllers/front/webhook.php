<?php
use Coinqvest\Classes\Helpers;

class CoinqvestWebhookModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $logger = new Coinqvest\Sdk\CQLoggingService();
        $payload = file_get_contents("php://input");
        $requestHeaders = array_change_key_case($this->getRequestHeaders(), CASE_UPPER);

        if (empty($payload) || !$this->validateWebhook($requestHeaders, $payload)) {
            $logger::write('Incoming webhook failed validation: ' . print_r($payload, true));
            http_response_code(401);
            exit;
        }

        $payload_decoded = json_decode($payload, true);

        // old webhook format which is not used anymore. Just abort with status code 200.
        if (isset($payload_decoded['type'])) {
            http_response_code(200);
            exit;
        }

        if (!isset($payload_decoded['eventType'])) {
            http_response_code(401);
            exit;
        }

        $checkoutId = (isset($payload_decoded['data']['checkout'])) ? $payload_decoded['data']['checkout']['id'] : $payload_decoded['data']['refund']['checkoutId'];
        $sql = "SELECT `order_id` FROM `" . _DB_PREFIX_ . "coinqvest_orders` WHERE coinqvest_checkout_id = '" . pSQL($checkoutId) . "'";
        $result = Db::getInstance()->getRow($sql);
        if (!isset($result) || empty($result)) {
            $logger::write(print_r('[Webhook] No order found for COINQVEST checkout id ' . pSQL($checkoutId), true));
            $logger::write(print_r('[Webhook] Payload: ' . pSQL($payload), true));
            http_response_code(401);
            exit;
        }

        $order = new Order($result['order_id']);
        if (!isset($order->id)) {
            $logger::write(print_r('[Webhook] No order found for order id ' . (int)$result['order_id'], true));
            $logger::write(print_r('[Webhook] Payload: ' . pSQL($payload), true));
            http_response_code(401);
            exit;
        }

        if($this->isStateSet($order, $payload_decoded))
        {
            http_response_code(200);
            exit;
        }

        $update = $this->updateOrderStatus($order, $payload_decoded);
        if (!$update) {
            $logger::write(print_r('[Webhook] Not able to update order status ' . (int)$order->id, true));
            $logger::write(print_r('[Webhook] Payload: ' . pSQL($payload), true));
            http_response_code(401);
        }

        http_response_code(200);
        exit;
    }


    public function validateWebhook($requestHeaders, $payload)
    {
        if (!isset($requestHeaders['X-WEBHOOK-AUTH'])) {
            return false;
        }

        $sig = $requestHeaders['X-WEBHOOK-AUTH'];
        $api_secret = Helpers::decrypt(Configuration::get('COINQVEST_API_SECRET'), Configuration::get('COINQVEST_HASH'));
        $sig2 = hash('sha256', $api_secret . $payload);
        if ($sig === $sig2) {
            return true;
        }

        return false;
    }


    public function getRequestHeaders()
    {
        if (!function_exists('getallheaders'))
        {
            $headers = array();

            foreach ($_SERVER as $name => $value ) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }


    public function updateOrderStatus($order, $payload)
    {
        switch ($payload['eventType']) {
            case 'CHECKOUT_COMPLETED':
                $orderStatus = 'PS_OS_PAYMENT';
                break;
            case 'CHECKOUT_UNDERPAID':
                $orderStatus = 'COINQVEST_UNDERPAID';
                break;
            case 'UNDERPAID_ACCEPTED':
                $orderStatus = 'PS_OS_PAYMENT';
                break;
            case 'REFUND_COMPLETED':
                $orderStatus = 'PS_OS_REFUND';
                $this->setRefundId($order, $payload);
                break;
            default:
                $orderStatus = false;
        }

        if ($orderStatus !== false)
        {
            $history = new OrderHistory();
            $history->id_order = $order->id;
            $history->changeIdOrderState((int)Configuration::get($orderStatus), $order->id);
            $history->add(true);
            return true;
        }

        return false;
    }


    public function isStateSet($order, $payload)
    {
        switch ($payload['eventType']) {
            case 'CHECKOUT_COMPLETED':
                $orderStatus = 'PS_OS_PAYMENT';
                break;
            case 'CHECKOUT_UNDERPAID':
                $orderStatus = 'COINQVEST_UNDERPAID';
                break;
            case 'UNDERPAID_ACCEPTED':
                $orderStatus = 'PS_OS_PAYMENT';
                break;
            case 'REFUND_COMPLETED':
                $orderStatus = 'PS_OS_REFUND';
                break;
            default:
                $orderStatus = false;
        }

        if ($orderStatus !== false)
        {
            $orderHistory = $order->getHistory($order->id_lang);

            $orderHistoryStates = array();
            foreach ($orderHistory as $item)
            {
                array_push($orderHistoryStates, $item['id_order_state']);
            }

            if (in_array(Configuration::get($orderStatus), $orderHistoryStates))
            {
                return true;
            }
        }
        return false;
    }

    public function setRefundId($order, $payload)
    {
        $sql = "UPDATE " . _DB_PREFIX_ . "coinqvest_orders SET `coinqvest_refund_id` = '" . pSQL($payload['data']['refund']['id']) . "' WHERE order_id = " . (int)$order->id;
        if (!Db::getInstance()->execute($sql))
        {
           return false;
        }
        return true;
    }
}