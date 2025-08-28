<?php
namespace Opencart\Catalog\Model\Extension\Sslcommerz\Payment;

class Sslcommerz extends \Opencart\System\Engine\Model {
    public function getMethods(array $address = []): array {
        if (!$this->config->get('payment_sslcommerz_status')) {
            return [];
        }

        // Minimum total check
        $min_total = (float)($this->config->get('payment_sslcommerz_min_total') ?? 0);
        $total = $this->cart->getTotal();

        if ($min_total > 0 && $total < $min_total) {
            return [];
        }

        // Geo zone check
        $geo_zone_id = (int)$this->config->get('payment_sslcommerz_geo_zone_id');
        if ($geo_zone_id) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0')");
            if (!$query->num_rows) {
                return [];
            }
        }

        $method_data = [
            'code'       => 'sslcommerz',
            'name'       => 'SSLCommerz',
            'sort_order' => (int)$this->config->get('payment_sslcommerz_sort_order'),
        ];

        return [$method_data];
    }
}
