
<?php
namespace Opencart\Admin\Controller\Extension\Sslcommerz\Payment;

class Sslcommerz extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/sslcommerz/payment/sslcommerz');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->response->setOutput($this->load->view('extension/sslcommerz/payment/sslcommerz', []));
    }

    public function install(): void {
        // Register events here if needed
    }

    public function uninstall(): void {
        // Remove events here if needed
    }
}
