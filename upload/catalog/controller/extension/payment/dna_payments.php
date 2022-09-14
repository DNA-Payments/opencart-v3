<?php
class ControllerExtensionPaymentDNAPayments extends Controller {

  public function index() {
    $this->load->language('extension/payment/dna_payments');
    $this->load->model('checkout/order');
    $data = array();

    $data['text_loading'] = $this->language->get('text_loading');
    $data['button_confirm'] = $this->language->get('button_confirm');

    $is_test_mode = $this->config->get('payment_dna_payments_test_mode') == '1';
    $order_id = $this->session->data['order_id'];
    $order_info = $this->model_checkout_order->getOrder($order_id);
    $products = $this->model_checkout_order->getOrderProducts($order_id);
    $totals = $this->model_checkout_order->getOrderTotals($order_id);

    $has_shipping = !empty($order_info['shipping_code']);
    $invoiceId = strval($order_id);
    $terminal = $is_test_mode ? $this->config->get('payment_dna_payments_test_terminal') : $this->config->get('payment_dna_payments_terminal');
    $amount = round((float)$order_info['total'], 2);
    $currency = $order_info['currency_code'];

    $data['isTestMode'] = $is_test_mode;
    $data['isFullRedirect'] = $this->config->get('payment_dna_payments_type') == '1';

    $itemTotal = 0;
    $shippingTotal = 0;
    $taxTotal = 0;

    foreach ($totals as $t) {
      switch($t['code']) {
        case 'sub_total':
          $itemTotal += (float)$t['value'];
          break;
        case 'shipping':
          $shippingTotal += (float)$t['value'];
          break;
        case 'tax':
          $taxTotal += (float)$t['value'];
          break;
      }
    }

    $handlingTotal = (float)$order_info['total'] - ($itemTotal + $shippingTotal + $taxTotal);

    $orderLines = array();
    foreach($products as $p) {
      $orderLines[] = array(
        'reference' => $p['product_id'],
        'name' => $p['name'],
        'quantity' => (float)$p['quantity'],
        'unitPrice' => round((float)$p['price'], 2),
        'totalAmount' => round((float)$p['total'], 2)
      );
    }

    $response = $this->sendPost($this->getAuthUrl(), array(
      'grant_type' => 'client_credentials',
      'scope' => 'payment ' . ($data['isFullRedirect'] ? 'integration_hosted' : 'integration_embedded'),
      'client_id' => $is_test_mode ? $this->config->get('payment_dna_payments_test_client_id') : $this->config->get('payment_dna_payments_client_id'),
      'client_secret' => $this->getClientSecret(),
      'terminal' => $terminal,
      'invoiceId' => $invoiceId,
      'amount' => $amount,
      'currency' => $currency
    ));

    if ($response != null && $response['status'] >= 200 && $response['status'] < 400) {
      $data['result'] = 'success';
      $data['paymentData'] = array(
        'invoiceId' => $invoiceId,
        'backLink' => $this->url->link('checkout/success', '', true),
        'failureBackLink' => $this->url->link('checkout/failure', '', true),
        'postLink' => $this->getPostLink(),
        'failurePostLink' => $this->getPostLink(),
        'language' => 'eng', // $order_info['language_code'],
        'description' => $this->config->get('payment_dna_payments_description'),
        'accountId' => $order_info['customer_id'] ? $order_info['customer_id'] : '',
        'terminal' => $terminal,
        'amount' => $amount,
        'currency' => $currency,
        'accountCountry' => $order_info['payment_iso_code_2'],
        'accountCity' => $order_info['payment_city'],
        'accountStreet1' => $order_info['payment_address_1'],
        'accountEmail' => $order_info['email'],
        'accountFirstName' => $order_info['payment_firstname'],
        'accountLastName' => $order_info['payment_lastname'],
        'accountPostalCode' => $order_info['payment_postcode'],
        'shippingAddress' => $has_shipping ? array(
          'firstName' => !empty($order_info['shipping_firstname']) ? $order_info['shipping_firstname'] : $order_info['payment_firstname'],
          'lastName' => !empty($order_info['shipping_lastname']) ? $order_info['shipping_lastname'] : $order_info['payment_lastname'],
          'streetAddress1' => !empty($order_info['shipping_address_1']) ? $order_info['shipping_address_1'] : $order_info['payment_address_1'],
          'streetAddress2' => !empty($order_info['shipping_address_2']) ? $order_info['shipping_address_2'] : $order_info['payment_address_2'],
          'postalCode' => !empty($order_info['shipping_postcode']) ? $order_info['shipping_postcode'] : $order_info['payment_postcode'],
          'city' => !empty($order_info['shipping_city']) ? $order_info['shipping_city'] : $order_info['payment_city'],
          'phone' => $order_info['telephone'],
          'region' => !empty($order_info['shipping_zone']) ? $order_info['shipping_zone'] : $order_info['payment_zone'],
          'country' => !empty($order_info['shipping_iso_code_2']) ? $order_info['shipping_iso_code_2'] : $order_info['payment_iso_code_2']
        ) : null,
        'orderLines' => $orderLines,
        'amountBreakdown' => array(
          'itemTotal' => array('totalAmount' => round($itemTotal, 2)),
          'shipping' => array('totalAmount' => round($shippingTotal, 2)),
          'handling' => array('totalAmount' => round($handlingTotal, 2)),
          'taxTotal' => array('totalAmount' => round($taxTotal, 2))
        ),
        'shippingPreference' => $has_shipping ? '' : 'NO_SHIPPING',
        'auth' => $response['response']
      );
    } else {
      $data['result'] = 'failure';
      $data['messages'] = [$this->language->get('invalid_auth_data')];
    }
    return $this->load->view('extension/payment/dna_payments', $data);
  }

  public function confirm() {
    if ($this->session->data['payment_method']['code'] == 'dna_payments') {
      $this->load->model('checkout/order');
    }
  }

  public function callback() {
    $content = file_get_contents('php://input');
    $response = json_decode($content, TRUE);

    $this->log('notification', $content);

    if (!$response) {
      return $this->log('error', 'No JSON response');
    }

    $order_id = $response['invoiceId'];
    if (!$order_id) {
      return $this->log('error', 'order_id is missing.');
    }

    if (!$this->isValidSignature($response, $this->getClientSecret())) {
      return $this->log('error', 'not valid signature');
    }

    $this->load->model('checkout/order');
    $order_info = $this->model_checkout_order->getOrder($order_id);
    if (!$order_info) {
      return $this->log('error', 'Order ' . $order_id . ' is missing.');
    }

    if ($response['success']) {
      $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_dna_payments_order_status_paid_id'), '', TRUE);
    } else {
      $this->log('error', 'Order ' . $order_id . '. Error code: ' . $response['errorCode']);
      $this->log('error', 'Order ' . $order_id . '. Error message: ' . $response['message']);
    }
  }

  private function sendPost($url, array $data)
  {
    // Setup cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded'
      ),
      CURLOPT_POSTFIELDS => http_build_query($data)
    ));

    // Send the request
    $response = curl_exec($ch);

    // Check for errors
    $result = null;
  
    if (!curl_errno($ch)) {
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      // Decode the response
      $body = json_decode($response, TRUE);

      $result = [
        'status' => $http_code,
        'response' => $body
      ];
    }

    curl_close($ch);
    return $result;
  }

  private function log($method, $message) {
    $this->log->write('dna_payments ' . $method . '. ' . print_r($message, true));
  }

  private function getPostLink()
  {
    return $this->url->link('extension/payment/dna_payments/callback', '', true);
  }

  private function isValidSignature($result, $secret)
  {
    $string = $result['id'] . $result['amount'] . $result['currency'] . $result['invoiceId'] . $result['errorCode'] . json_encode($result['success']);
    return base64_encode(hash_hmac('sha256', $string, $secret, true)) == $result['signature'];
  }

  private function getClientSecret() {
    $is_test_mode = $this->config->get('payment_dna_payments_test_mode') == '1';
    return $is_test_mode ? $this->config->get('payment_dna_payments_test_client_secret') : $this->config->get('payment_dna_payments_client_secret');
  }

  private function getAuthUrl() {
    $is_test_mode = $this->config->get('payment_dna_payments_test_mode') == '1';
    return $is_test_mode ? 'https://test-oauth.dnapayments.com/oauth2/token' : 'https://oauth.dnapayments.com/oauth2/token';
  }
}
