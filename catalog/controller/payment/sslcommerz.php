<?php
namespace Opencart\Catalog\Controller\Extension\Sslcommerz\Payment;

class Sslcommerz extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('extension/sslcommerz/payment/sslcommerz');

        $data['confirm_action'] = $this->url->link('extension/sslcommerz/payment/sslcommerz|confirm', '', true);

        return $this->load->view('extension/sslcommerz/payment/sslcommerz', $data);
    }

    public function confirm(): void {
        $this->load->language('extension/sslcommerz/payment/sslcommerz');
        $this->load->model('checkout/order');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = 'No order to process.';
            $this->respondJson($json);
            return;
        }

        $order_id = (int)$this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $json['error'] = 'Invalid order.';
            $this->respondJson($json);
            return;
        }

        try {
            $redirect = $this->createPaymentSession($order_info);
            $json['redirect'] = $redirect;
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $this->respondJson($json);
    }

    public function success(): void {
        $this->load->model('checkout/order');

        $order_id = isset($this->request->get['tran_id']) ? $this->extractOrderId((string)$this->request->get['tran_id']) : ($this->session->data['order_id'] ?? 0);
        $val_id = $this->request->get['val_id'] ?? '';

        if (!$order_id || !$val_id) {
            $this->session->data['error'] = $this->language->get('text_payment_failed');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $order_info = $this->model_checkout_order->getOrder((int)$order_id);

        if (!$order_info) {
            $this->session->data['error'] = $this->language->get('text_payment_failed');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        try {
            $validated = $this->validateWithSSLCommerz($val_id);
            $this->assertValidation($validated, $order_info);

            $status_id = (int)$this->config->get('payment_sslcommerz_order_status_id');
            $comment = 'SSLCommerz payment successful. TrxID: ' . ($validated['tran_id'] ?? '');
            $this->model_checkout_order->addHistory((int)$order_id, $status_id, $comment, true);

            $this->response->redirect($this->url->link('checkout/success', '', true));
        } catch (\Exception $e) {
            $failed_status_id = (int)$this->config->get('payment_sslcommerz_failed_status_id');
            $this->model_checkout_order->addHistory((int)$order_id, $failed_status_id, 'SSLCommerz validation failed: ' . $e->getMessage(), false);
            $this->session->data['error'] = $this->language->get('text_payment_failed') . ' ' . $this->language->get('text_try_again');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function fail(): void {
        $this->load->model('checkout/order');

        $order_id = isset($this->request->get['tran_id']) ? $this->extractOrderId((string)$this->request->get['tran_id']) : ($this->session->data['order_id'] ?? 0);

        if ($order_id) {
            $failed_status_id = (int)$this->config->get('payment_sslcommerz_failed_status_id');
            $this->model_checkout_order->addHistory((int)$order_id, $failed_status_id, 'SSLCommerz payment failed/cancelled', false);
        }

        $this->session->data['error'] = $this->language->get('text_payment_failed');
        $this->response->redirect($this->url->link('checkout/checkout', '', true));
    }

    public function cancel(): void {
        $this->fail();
    }

    // IPN endpoint from SSLCommerz (server-to-server)
    public function ipn(): void {
        $this->load->model('checkout/order');

        $order_id = isset($this->request->post['tran_id']) ? $this->extractOrderId((string)$this->request->post['tran_id']) : 0;
        $val_id = $this->request->post['val_id'] ?? '';

        if (!$order_id || !$val_id) {
            $this->response->setOutput('Missing parameters');
            return;
        }

        $order_info = $this->model_checkout_order->getOrder((int)$order_id);

        if (!$order_info) {
            $this->response->setOutput('Order not found');
            return;
        }

        try {
            $validated = $this->validateWithSSLCommerz($val_id);
            $this->assertValidation($validated, $order_info);

            $status_id = (int)$this->config->get('payment_sslcommerz_order_status_id');
            $comment = 'SSLCommerz IPN: payment confirmed. TrxID: ' . ($validated['tran_id'] ?? '');
            $this->model_checkout_order->addHistory((int)$order_id, $status_id, $comment, false);

            $this->response->setOutput('IPN OK');
        } catch (\Exception $e) {
            $failed_status_id = (int)$this->config->get('payment_sslcommerz_failed_status_id');
            $this->model_checkout_order->addHistory((int)$order_id, $failed_status_id, 'SSLCommerz IPN failed: ' . $e->getMessage(), false);
            $this->response->setOutput('IPN FAILED');
        }
    }

    private function respondJson(array $json): void {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function createPaymentSession(array $order): string {
        $is_test = (bool)$this->config->get('payment_sslcommerz_test');
        $store_id = (string)$this->config->get('payment_sslcommerz_store_id');
        $store_passwd = (string)$this->config->get('payment_sslcommerz_store_password');

        $endpoint = $is_test
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

        $tran_id = $this->buildTranId((int)$order['order_id']);

        $success_url = $this->url->link('extension/sslcommerz/payment/sslcommerz|success', '', true);
        $fail_url    = $this->url->link('extension/sslcommerz/payment/sslcommerz|fail', '', true);
        $cancel_url  = $this->url->link('extension/sslcommerz/payment/sslcommerz|cancel', '', true);
        $ipn_url     = $this->url->link('extension/sslcommerz/payment/sslcommerz|ipn', '', true);

        $amount = number_format((float)$order['total'], 2, '.', '');
        $currency = $order['currency_code'];

        $payload = [
            'store_id'       => $store_id,
            'store_passwd'   => $store_passwd,
            'total_amount'   => $amount,
            'currency'       => $currency,
            'tran_id'        => $tran_id,
            'success_url'    => $success_url,
            'fail_url'       => $fail_url,
            'cancel_url'     => $cancel_url,
            'ipn_url'        => $ipn_url,

            // Customer
            'cus_name'       => trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')),
            'cus_email'      => $order['email'],
            'cus_add1'       => $order['payment_address_1'],
            'cus_city'       => $order['payment_city'],
            'cus_postcode'   => $order['payment_postcode'],
            'cus_country'    => $order['payment_iso_code_2'],
            'cus_phone'      => $order['telephone'],

            // Shipping (fallback to payment if not available)
            'ship_name'      => trim(($order['shipping_firstname'] ?? $order['firstname']) . ' ' . ($order['shipping_lastname'] ?? $order['lastname'])),
            'ship_add1'      => $order['shipping_address_1'] ?: $order['payment_address_1'],
            'ship_city'      => $order['shipping_city'] ?: $order['payment_city'],
            'ship_postcode'  => $order['shipping_postcode'] ?: $order['payment_postcode'],
            'ship_country'   => $order['shipping_iso_code_2'] ?: $order['payment_iso_code_2'],

            // Product info (generic)
            'product_name'   => 'Order #' . (int)$order['order_id'],
            'product_category' => 'General',
            'product_profile'  => 'general',
            'emi_option'     => 0
        ];

        $response = $this->httpPost($endpoint, $payload);
        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['status'])) {
            throw new \Exception('Invalid response from SSLCommerz.');
        }

        if (strtoupper($data['status']) !== 'SUCCESS') {
            $failed_status_id = (int)$this->config->get('payment_sslcommerz_failed_status_id');
            $this->load->model('checkout/order');
            $this->model_checkout_order->addHistory((int)$order['order_id'], $failed_status_id, 'SSLCommerz session failed: ' . ($data['failedreason'] ?? 'Unknown'), false);
            throw new \Exception('Unable to initiate payment session. ' . ($data['failedreason'] ?? ''));
        }

        $gateway_url = $data['GatewayPageURL'] ?? $data['redirect_url'] ?? '';
        if (!$gateway_url) {
            throw new \Exception('Gateway URL not provided by SSLCommerz.');
        }

        return $gateway_url;
    }

    private function validateWithSSLCommerz(string $val_id): array {
        $is_test = (bool)$this->config->get('payment_sslcommerz_test');
        $store_id = (string)$this->config->get('payment_sslcommerz_store_id');
        $store_passwd = (string)$this->config->get('payment_sslcommerz_store_password');

        $endpoint = $is_test
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

        $query = http_build_query([
            'val_id'       => $val_id,
            'store_id'     => $store_id,
            'store_passwd' => $store_passwd,
            'v'            => 1,
            'format'       => 'json'
        ]);

        $url = $endpoint . '?' . $query;

        $response = $this->httpGet($url);
        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['status'])) {
            throw new \Exception('Invalid validation response.');
        }

        return $data;
    }

    private function assertValidation(array $validated, array $order): void {
        $status = strtoupper((string)($validated['status'] ?? ''));
        if (!in_array($status, ['VALID', 'VALIDATED'], true)) {
            throw new \Exception('Payment not valid. Status: ' . $status);
        }

        // Check transaction/order consistency
        $tran_id = (string)($validated['tran_id'] ?? '');
        if ($this->extractOrderId($tran_id) !== (int)$order['order_id']) {
            throw new \Exception('Order mismatch.');
        }

        // Compare amount and currency
        $expected_amount = number_format((float)$order['total'], 2, '.', '');
        $paid_amount = number_format((float)($validated['amount'] ?? 0), 2, '.', '');
        $currency_ok = strtoupper($order['currency_code']) === strtoupper((string)($validated['currency_type'] ?? $validated['currency'] ?? ''));

        if ($expected_amount !== $paid_amount || !$currency_ok) {
            throw new \Exception('Amount or currency mismatch.');
        }
    }

    private function httpPost(string $url, array $payload): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $code >= 400) {
            throw new \Exception('HTTP error contacting SSLCommerz: ' . ($err ?: ('Status ' . $code)));
        }

        return $response;
    }

    private function httpGet(string $url): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $code >= 400) {
            throw new \Exception('HTTP error contacting SSLCommerz: ' . ($err ?: ('Status ' . $code)));
        }

        return $response;
    }

    private function buildTranId(int $order_id): string {
        // Keep it unique and include order reference for mapping
        return 'OC4-' . $order_id . '-' . bin2hex(random_bytes(4));
    }

    private function extractOrderId(string $tran_id): int {
        // Expected format: OC4-{order_id}-xxxx
        if (preg_match('/^OC4-(\d+)-/', $tran_id, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}
