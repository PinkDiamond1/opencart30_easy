<?php

class ControllerCheckoutDibseasy extends Controller {

    public function index() {
        // Validate cart has products and has stock.
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        if ('hosted' == $this->config->get('payment_dibseasy_checkout_type')) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        $this->load->language('checkout/checkout');
        $this->load->language('checkout/dibseasy');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('catalog/view/theme/default/stylesheet/easy_checkout.css');
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_checkout_option'] = sprintf($this->language->get('text_checkout_option'), 1);
        $data['text_checkout_account'] = sprintf($this->language->get('text_checkout_account'), 2);
        $data['text_checkout_payment_address'] = sprintf($this->language->get('text_checkout_payment_address'), 2);
        $data['text_checkout_shipping_address'] = sprintf($this->language->get('text_checkout_shipping_address'), 3);
        $data['text_checkout_shipping_method'] = sprintf($this->language->get('text_checkout_shipping_method'), 4);
        if ($this->cart->hasShipping()) {
            $data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 5);
            $data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 6);
        } else {
            $data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 3);
            $data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 4);
        }

        $this->load->model('extension/payment/dibseasy');
        $dibs_model = $this->model_extension_payment_dibseasy;
        $checkoutData = $dibs_model->getCheckoutData();
        $data['paymentId'] = '';
        if ($paymentId = $this->model_extension_payment_dibseasy->getPaymentId()) {
            $data['paymentId'] = $paymentId;
        } else {
            $data['initerror'] = 'An error occurred during payment initialization';
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        $data['logged'] = $this->customer->isLogged();

        if (isset($this->session->data['account'])) {
            $data['account'] = $this->session->data['account'];
        } else {
            $data['account'] = '';
        }
        $data['shipping_required'] = $this->cart->hasShipping();
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['checkout_url'] = $this->config->get('payment_dibseasy_otherpayment_button_url');
        $data['button_checkout_label'] = $this->language->get('button_checkout_label');
        if (empty($data['checkout_url'])) {
            $data['checkout_url'] = $this->url->link('checkout/checkout', '', true);
        }
		$data['debugmode'] = $this->config->get('payment_dibseasy_frontend_debug');
		$data['datastring'] = print_r($_SESSION['datastring'],true);
        $this->response->setOutput($this->load->view('checkout/dibseasy', array_merge($data, $checkoutData)));
    }

    public function updateview() {
        $this->load->model('extension/payment/dibseasy');
        $action = $this->request->post['action'];
        $this->load->language('checkout/dibseasy');
        try {
            switch ($action) {
                case 'set-shipping-method':
                    $code = $this->request->post['code'];
                    $this->model_extension_payment_dibseasy->setShippingMethod($code);
                    break;
                case 'start':
                    $this->model_extension_payment_dibseasy->start();
                    break;
                case 'address-changed':
                    $this->model_extension_payment_dibseasy->setShippingAddress();
                    break;
            }
            $data['shipping_methods'] = $this->model_extension_payment_dibseasy->getShippingMethods();
            $data['totals'] = $this->model_extension_payment_dibseasy->getTotals(true);
            $data['code'] = isset($this->session->data['shipping_method']['code']) ? $this->session->data['shipping_method']['code'] : null;
            $data['checkout_url'] = $this->config->get('payment_dibseasy_otherpayment_button_url');
            if (empty($data['checkout_url'])) {
                $data['checkout_url'] = $this->url->link('checkout/checkout', '', true);
            }

            $data['button_checkout_label'] = $this->language->get('button_checkout_label');

            $data['order_summary_label'] = $this->language->get('order_summary_label');

            $data['shipping_methods_label'] = $this->language->get('shipping_methods_label');

            $data['shipping_total_label'] = $this->language->get('shipping_total_label');

            $data['currency_code'] = $this->session->data['currency'];

            //Get consumer details
            $customerType = $this->config->get('payment_dibseasy_allowed_customer_type');
            if ('b2b' != $customerType) {
                if ($this->customer->isLogged()) {
                    $this->load->model('account/customer');
                    $this->load->model('account/address');
                    $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
                    $address_info = $this->model_account_address->getAddress($customer_info['address_id']);
                    
                    $name = $customer_info['firstname']." ".$customer_info['lastname'];
                    $email = $customer_info['email'];
                    $telephone = $customer_info['telephone'];
                    
                    $address_1 = $address_info['address_1'];
                    $address_2 = $address_info['address_2'];
                    $postcode = $address_info['postcode'];
                    $city = $address_info['city'];
                    $country = $address_info['country'];
                
                } else {
                    $name = $this->session->data['guest']['firstname']." ".$this->session->data['guest']['lastname'];
                    $email = $this->session->data['guest']['email'];
                    $telephone = $this->session->data['guest']['telephone'];
                    $address_1 = (!empty($this->session->data['shipping_address']['address_1'])) ? $this->session->data['shipping_address']['address_1'] : null;
                    $address_2 = (!empty($this->session->data['shipping_address']['address_2'])) ? $this->session->data['shipping_address']['address_2'] : null;
                    $postcode = (!empty($this->session->data['shipping_address']['postcode'])) ? $this->session->data['shipping_address']['postcode'] : null;
                    $city = (!empty($this->session->data['shipping_address']['city'])) ? $this->session->data['shipping_address']['city'] : null;
                    $country = (!empty($this->session->data['shipping_address']['iso_code_3'])) ? $this->session->data['shipping_address']['iso_code_3'] : null;
                }

                $phonePrefix = substr($telephone, 0, 3);
                $number = substr($telephone, 3);
                $data['consumer_data'] = array(
                    ['name' => !empty($name) ? $name : null],
                    ["email" => $email],
                    ['phoneNumber' => $phonePrefix . "-" . $number],
                    ["addressLine1" => $address_1],
                    ["addressLine2" => $address_2],
                    ["postalCode" => $postcode],
                    ["city" => $city],
                    ["country" => $country]
                );
            }
            //echo "<pre>";print_r($customer_info);  die;
            //end consumer details

            $result = array('outputHtml' => $this->load->view('checkout/dibseasy_totals', $data));
        } catch (Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $result = array('outputHtml' => [], 'exception' => 1, 'message' => $e->getMessage());
            unset($this->session->data['dibseasy']['paymentid']);
        }
        echo json_encode($result);
    }

}
