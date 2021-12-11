<?php
class ModelExtensionPaymentDNAPayments extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/dna_payments');

		$status = $this->config->get('payment_dna_payments_status') == 1;

		$method_data = array();

		$title = $this->config->get('payment_dna_payments_payment_method_name');

		if ($status) {
			$method_data = array(
				'code'       => 'dna_payments',
				'title'      =>  empty($title) ? 'Visa / Mastercard / American Express / Diners Club / Other' : $title,
				'terms'      => '',
				'sort_order' => $this->config->get('payment_dna_payments_sort_order')
			);
		}

		return $method_data;
	}
}