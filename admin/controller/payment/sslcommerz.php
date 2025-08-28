<?php
namespace Opencart\Admin\Controller\Extension\Sslcommerz\Payment;

class Sslcommerz extends \Opencart\System\Engine\Controller {
    private array $error = [];

    public function index(): void {
        $this->load->language('extension/sslcommerz/payment/sslcommerz');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_sslcommerz', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            ));
            return;
        }

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/sslcommerz/payment/sslcommerz', 'user_token=' . $this->session->data['user_token'], true)
            ]
        ];

        $data['action'] = $this->url->link('extension/sslcommerz/payment/sslcommerz', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        // Errors
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['error_store_id'] = $this->error['store_id'] ?? '';
        $data['error_store_password'] = $this->error['store_password'] ?? '';

        // Fields
        $fields = [
            'payment_sslcommerz_store_id'             => '',
            'payment_sslcommerz_store_password'       => '',
            'payment_sslcommerz_test'                 => 1,
            'payment_sslcommerz_status'               => 0,
            'payment_sslcommerz_sort_order'           => 0,
            'payment_sslcommerz_order_status_id'      => 2, // Processing
            'payment_sslcommerz_pending_status_id'    => 1, // Pending
            'payment_sslcommerz_failed_status_id'     => 10, // Failed
            'payment_sslcommerz_geo_zone_id'          => 0,
            'payment_sslcommerz_min_total'            => '0.00'
        ];

        foreach ($fields as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $data[$key] = $this->config->get($key) ?? $default;
            }
        }

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/sslcommerz/payment/sslcommerz', $data));
    }

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/sslcommerz/payment/sslcommerz')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_sslcommerz_store_id'])) {
            $this->error['store_id'] = $this->language->get('error_store_id');
        }

        if (empty($this->request->post['payment_sslcommerz_store_password'])) {
            $this->error['store_password'] = $this->language->get('error_store_password');
        }

        return !$this->error;
    }
}
