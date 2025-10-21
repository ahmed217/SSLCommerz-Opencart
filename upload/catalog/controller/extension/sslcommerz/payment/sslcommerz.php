
<?php
namespace Opencart\Catalog\Controller\Extension\Sslcommerz\Payment;

class Sslcommerz extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/sslcommerz/payment/sslcommerz');
        $data['action'] = 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
        $this->response->setOutput($this->load->view('extension/sslcommerz/payment/sslcommerz', $data));
    }
}
