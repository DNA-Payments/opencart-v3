<?php
class ControllerExtensionPaymentDNAPayments extends Controller {
  private $error = array();

  private $settings = array(
    'texts' => array(
      'heading_title',
      'text_edit',
      'text_enabled',
      'text_disabled',
      'text_yes',
      'text_no',
      'button_save',
      'button_cancel',
      'entry_status',
      'entry_sort_order',
      'entry_test_mode',
      'entry_client_id',
      'help_client_id',
      'entry_client_secret',
      'help_client_secret',
      'entry_terminal',
      'help_terminal',
      'entry_test_client_id',
      'help_test_client_id',
      'entry_test_client_secret',
      'help_test_client_secret',
      'entry_test_terminal',
      'help_test_terminal',
      'entry_description',
      'help_description',
      'entry_order_status_paid',
      'help_order_status_paid',
      'entry_type',
      'help_type',
      'entry_payment_method_name'      
    ),
    'fields' => array(
      array('name' => 'payment_dna_payments_client_id', 'value' => ''),
      array('name' => 'payment_dna_payments_client_secret', 'value' => ''),
      array('name' => 'payment_dna_payments_terminal', 'value' => ''),
      array('name' => 'payment_dna_payments_test_client_id', 'value' => ''),
      array('name' => 'payment_dna_payments_test_client_secret', 'value' => ''),
      array('name' => 'payment_dna_payments_test_terminal', 'value' => ''),
      array('name' => 'payment_dna_payments_test_mode', 'value' => '1'),
      array('name' => 'payment_dna_payments_description', 'value' => ''),
      array('name' => 'payment_dna_payments_sort_order', 'value' => '0'),
      array('name' => 'payment_dna_payments_status', 'value' => '0'),
      array('name' => 'payment_dna_payments_type', 'value' => '1'),
      array('name' => 'payment_dna_payments_order_status_paid_id', 'value' => '15'),
      array('name' => 'payment_dna_payments_payment_method_name', 'value' => 'Visa / Mastercard /American Express / Diners Club / Other')
    ),
    'errors' => array(
      'error_client_id',
      'error_client_secret',
      'error_terminal',
      'error_test_client_id',
      'error_test_client_secret',
      'error_test_terminal',
      'error_payment_method_name'
    ),
    'validate' => array(
      'always' => array('payment_dna_payments_payment_method_name'),
      'test' => array('payment_dna_payments_test_client_id', 'payment_dna_payments_test_client_secret', 'payment_dna_payments_test_terminal'),
      'prod' => array('payment_dna_payments_client_id', 'payment_dna_payments_client_secret', 'payment_dna_payments_terminal'),
    ),
  );

  public function index() {
    $this->load->language('extension/payment/dna_payments');

    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('setting/setting');

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
      $this->model_setting_setting->editSetting('payment_dna_payments', $this->request->post);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect($this->prepareExtensionUrlLink('marketplace/extension'));
    }

    if (isset($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } else {
      $data['error_warning'] = '';
    }

    foreach ($this->settings['errors'] as $error) {
      $data[$error] = (isset($this->error[$error])) ? $this->error[$error] : '';
    }

    foreach ($this->settings['texts'] as $text) {
      $data[$text] = $this->language->get($text);
    }

    foreach ($this->settings['fields'] as $field) {
      $data[$field['name']] = trim($this->getConfigByField($field['name'], $field['value']));
    }

    $data['breadcrumbs'] = $this->getBreadcrumbs();
    $data['action'] = $this->prepareUrlLink('extension/payment/dna_payments');
    $data['cancel'] = $this->prepareExtensionUrlLink('marketplace/extension');

    $this->load->model('localisation/order_status');
    $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

    $data['payment_types'] = array(
      array('id' => 1, 'title' => $this->language->get('type_1_title')),
      array('id' => 2, 'title' => $this->language->get('type_2_title')),
    );

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/payment/dna_payments', $data));
  }

  protected function validate() {
    if (!$this->user->hasPermission('modify', 'extension/payment/dna_payments')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    foreach ($this->settings['validate']['always'] as $field_name) {
      $this->validateField($field_name);
    }

    if (!$this->request->post['payment_dna_payments_test_mode']) {
      foreach ($this->settings['validate']['prod'] as $field_name) {
        $this->validateField($field_name);
      }
    } else {
      foreach ($this->settings['validate']['test'] as $field_name) {
        $this->validateField($field_name);
      }
    }

    return !$this->error;
  }

  protected function validateField($field_name) {
    $error_name = str_replace('payment_dna_payments', 'error', $field_name);
    if ($this->isEmpty($this->request->post[$field_name])) {
      $this->error[$error_name] = $this->language->get($error_name);
    }
  }

  protected function getBreadcrumbs() {
    return array(
      array(
        'text' => $this->language->get('text_home'),
        'href' => $this->prepareUrlLink('common/dashboard')
      ),
      array(
        'text' => $this->language->get('text_extension'),
        'href' => $this->prepareExtensionUrlLink('marketplace/extension')
      ),
      array(
        'text' => $this->language->get('heading_title'),
        'href' => $this->prepareUrlLink('extension/payment/dna_payments')
      )
    );
  }

  private function prepareUrlLink($link, $params = '')
  {
    return $this->url->link($link, 'user_token=' . $this->session->data['user_token'] . $params, true);
  }

  private function prepareExtensionUrlLink($link)
  {
    return $this->prepareUrlLink($link, '&type=payment');
  }

  private function getConfigByField($fieldName, $defaultFieldValue)
  {
    if (isset($this->request->post[$fieldName])) {
      return $this->request->post[$fieldName];
    }
    else if (!$this->isEmpty($this->config->get($fieldName))) {
      return $this->config->get($fieldName);
    }
    return $defaultFieldValue;
  }

  private function isEmpty($value) {
    return is_null($value) || $value === '';
  }
}