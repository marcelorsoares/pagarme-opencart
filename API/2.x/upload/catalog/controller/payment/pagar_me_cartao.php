<?php

require_once DIR_SYSTEM . 'library/PagarMe/Pagarme.php';

class ControllerPaymentPagarMeCartao extends Controller
{

    private $error;

    public function index()
    {

        $this->language->load('payment/pagar_me_cartao');
        $this->load->model('checkout/order');
        $this->load->model('account/customer');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $customer = $this->model_account_customer->getCustomer($order_info['customer_id']);

        if ($this->customer->isLogged()) {
            $data['nome_cartao'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        }

        $data['pagar_me_cartao_criptografia'] = $this->config->get('pagar_me_cartao_criptografia');
        $data['total'] = str_replace(".", "", number_format($order_info['total'], 2, ".", ""));

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_information'] = $this->language->get('text_information');
        $data['text_wait'] = $this->language->get('text_wait');
        $data['text_information'] = $this->config->get('pagar_me_cartao_text_information');
        $data['url'] = $this->url->link('payment/pagar_me_cartao/confirm', '', 'SSL');
        $data['url2'] = $this->url->link('payment/pagar_me_cartao/error', '', 'SSL');

        /* Parcelas */
        Pagarme::setApiKey($this->config->get('pagar_me_cartao_api'));

        try {
            $numero_parcelas = floor($order_info['total'] / $this->config->get('pagar_me_cartao_valor_parcela'));

            $max_parcelas = $numero_parcelas ? $numero_parcelas : 1;

            if ($max_parcelas > $this->config->get('pagar_me_cartao_max_parcelas')) {
                $max_parcelas = $this->config->get('pagar_me_cartao_max_parcelas');
            }

            $this->session->data['calculated_installments'] =  $data['parcelas'] = PagarMe_Transaction::calculateInstallmentsAmount($data['total'], $this->config->get('pagar_me_cartao_taxa_juros'), $max_parcelas, $this->config->get('pagar_me_cartao_parcelas_sem_juros'));
        } catch (Exception $e) {
            $this->log->write("Erro Pagar.me: " . $e->getMessage());
            $json['error'] = $e->getMessage();
        }

        $data['stylesheet'] = 'catalog/view/theme/default/stylesheet/pagar_me.css';

        //Check if Opencart Version is equal to 2.2
        preg_match("/^2.2.*/", VERSION, $version);
        if (!empty($version)) {
            return $this->load->view('payment/pagar_me_cartao.tpl', $data);
        }
        return $this->load->view('default/template/payment/pagar_me_cartao.tpl', $data);
    }

    public function confirm()
    {


        $this->load->model('checkout/order');
        $this->load->model('payment/pagar_me_cartao');

        $result = $this->model_payment_pagar_me_cartao->getPagarMeOrderByOrderId($this->session->data['order_id']);

        $comentario = "N&uacute;mero da transa&ccedil;&atilde;o: " . $result['transaction_id'] . "<br />";
        $comentario .= " Cartão: " . strtoupper($result['bandeira']) . "<br />";
        $comentario .= " Parcelado em: " . $result['n_parcela'] . "x";

        $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('pagar_me_cartao_order_processing'), $comentario, true);

        $this->response->redirect($this->url->link('checkout/success'));
    }

    public function error()
    {


        $this->load->model('checkout/order');

        $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('pagar_me_cartao_order_refused'));

        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();

            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
        }

        $this->language->load('payment/pagar_me_cartao');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'href' => $this->url->link('common/home'),
            'text' => $this->language->get('text_home'),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'href' => $this->url->link('checkout/cart'),
            'text' => $this->language->get('text_basket'),
            'separator' => $this->language->get('text_separator')
        );

        $data['breadcrumbs'][] = array(
            'href' => $this->url->link('checkout/checkout', '', 'SSL'),
            'text' => $this->language->get('text_checkout'),
            'separator' => $this->language->get('text_separator')
        );

        $data['breadcrumbs'][] = array(
            'href' => $this->url->link('payment/cielo_message'),
            'text' => $this->language->get('text_no_success'),
            'separator' => $this->language->get('text_separator')
        );

        $data['heading_title'] = $this->language->get('heading_title');

        if ($this->customer->isLogged()) {
            $data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/order', '', 'SSL'), $this->url->link('information/contact'));
        } else {
            $data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
        }

        $data['button_continue'] = $this->language->get('button_continue');

        $data['continue'] = $this->url->link('common/home');

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/pagar_me_cartao_message.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/pagar_me_cartao_message.tpl';
        } else {
            $this->template = 'default/template/payment/pagar_me_cartao_message.tpl';
        }

        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );

        $this->response->setOutput($this->render());
    }

    public function callback()
    {
        Pagarme::setApiKey($this->config->get('pagar_me_cartao_api'));

        $requestBody = file_get_contents("php://input");
        $headers = getallheaders();
        if(PagarMe::validateRequestSignature($requestBody, $headers['X-Hub-Signature'])){
            if(isset($this->request->post['transaction']['metadata']['id_pedido'])){
                $event = $this->request->post['event'];
                $this->load->model('checkout/order');
                $order_id = $this->request->post['transaction']['metadata']['id_pedido'];

                if ($event == 'transaction_status_changed') {
                    $current_status = 'pagar_me_cartao_order_' . $this->request->post['current_status'];

                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get($current_status), '', true);

                    $this->log->write('Pagar.me Postback: Pedido '.$order_id.' atualizado para '.$this->request->post['current_status']);

                    return http_response_code(200);
                }
            }
        }else{
            $this->log->write('Pagar.me Postback: Falha ao validar o POSTback');

            return http_response_code(403);
        }
    }

    public function payment()
    {

        $this->load->model('checkout/order');
        $this->load->model('account/customer');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $customer = $this->model_account_customer->getCustomer($order_info['customer_id']);

        $numero = 'Sem Número';
        $complemento = '';
        $customer_name = trim($order_info['payment_firstname']).' '.trim($order_info['payment_lastname']);
        /* Pega os custom fields de CPF/CNPJ, número e complemento */
        $this->load->model('account/custom_field');

        $default_group = $this->config->get('config_customer_group_id');
        if(isset($customer['customer_group_id'])){
            $default_group = $customer['customer_group_id'];
        }

        $custom_fields = $this->model_account_custom_field->getCustomFields($default_group);
        foreach($custom_fields as $custom_field){
            if($custom_field['location'] == 'address'){
                if(strtolower($custom_field['name']) == 'numero' || strtolower($custom_field['name']) == 'número'){
                    $numero = $order_info['payment_custom_field'][$custom_field['custom_field_id']];
                }elseif(strtolower($custom_field['name']) == 'complemento'){
                    $complemento = $order_info['payment_custom_field'][$custom_field['custom_field_id']];
                }
            }
        }

        $chosen_installments = $this->request->post['installments'];
        $amount = $this->session->data['calculated_installments']['installments'][$chosen_installments]['amount'];
        $interest_amount = (($amount / 100) - $order_info['total']);
        Pagarme::setApiKey($this->config->get('pagar_me_cartao_api'));

        $transaction = new PagarMe_Transaction(array(
            'amount' => $amount,
            'card_hash' => $this->request->post['card_hash'],
            'installments' => $chosen_installments,
            'postback_url' => HTTP_SERVER . 'index.php?route=payment/pagar_me_cartao/callback',
            "customer" => array(
                "name" => $customer_name,
                "document_number" => $this->request->post['cpf_customer'],
                "email" => $order_info['email'],
                "address" => array(
                    "street" => $order_info['payment_address_1'],
                    "street_number" => $numero,
                    "neighborhood" => $order_info['payment_address_2'],
                    "complementary" => $complemento,
                    "city" => $order_info['payment_city'],
                    "state" => $order_info['payment_zone_code'],
                    "country" => $order_info['payment_country'],
                    "zipcode" => $this->removeSeparadores($order_info['payment_postcode']),
                ),
                "phone" => array(
                    "ddd" => substr(preg_replace('/[^0-9]/', '', $order_info['telephone']), 0, 2),
                    "number" => substr(preg_replace('/[^0-9]/', '', $order_info['telephone']), 2, 9),
                )
            ),
            'metadata' => array(
                'id_pedido' => $order_info['order_id'],
                'loja' => $this->config->get('config_name'),
            )));

        $json = array();
        try{
            $transaction->charge();

            $this->load->model('payment/pagar_me_cartao');
            $this->model_payment_pagar_me_cartao->insertInterestRate($order_info['order_id'], $interest_amount);
            $this->model_payment_pagar_me_cartao->updateOrderAmount($order_info['order_id'], ($amount/100));

            $this->model_payment_pagar_me_cartao->addTransactionId($this->session->data['order_id'], $transaction->id, $chosen_installments, $this->request->post['bandeira']);

            $this->log->write('Pagar.me Transaction: '.$transaction->id. ' | Status: '.$transaction->status.' | Pedido: '.$order_info['order_id']);

            $json['success'] = true;

        }catch(Exception $e){
            $this->log->write('Erro Pagar.me cartão: ' . $e->getMessage());
            $json['error'] = $e->getMessage();
        }

        $this->response->setOutput(json_encode($json));
    }

    private function removeSeparadores($string)
    {
        $nova_string = str_replace(array('.', '-', '/', '(', ')', ' '), array('', '', '', '', '', ''), $string);

        return $nova_string;
    }

}

?>
