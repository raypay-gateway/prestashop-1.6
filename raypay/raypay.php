<?php
/**
 * RayPay payment gateway
 *
 * @developer Hanieh Ramzanpour
 * @publisher RayPay
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */
if (!defined('_PS_VERSION_'))
    exit;

class raypay extends PaymentModule
{

    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {

        $this->name = 'raypay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
        $this->author = 'Developer: Hanieh Ramznpour, Publisher: Saminray';
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        parent::__construct();
        $this->displayName = 'RayPay';
        $this->description = 'پرداخت از طریق درگاه رای پی';
        $this->confirmUninstall = 'Are you sure you want to uninstall this module?';
        if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
            $this->warning ='هیچ واحد پولی برای این ماژول انتخاب نشده است.';
        $config_userID = Configuration::getMultiple(array('raypay_user_id'));
        $config_marketingID = Configuration::getMultiple(array('raypay_marketing_id'));
        $config_sandbox = Configuration::getMultiple(array('raypay_sandbox'));
        if (!isset($config_userID['raypay_user_id']) || !isset($config_marketingID['raypay_marketing_id']))
            $this->warning = 'لطفا اطلاعات درگاه خود را در بخش تنظیمات درگاه رای پی وارد نمایید.';

    }

    public function install()
    {
        if (!parent::install()
            || !Configuration::updateValue('raypay_user_id', '')
            || !Configuration::updateValue('raypay_marketing_id', '')
            || !Configuration::updateValue('raypay_sandbox', '')
            || !Configuration::updateValue('raypay_success_massage', '')
            || !Configuration::updateValue('raypay_failed_massage', '')
            || !Configuration::updateValue('raypay_currency', '')
            || !Configuration::updateValue('raypay_HASH_KEY', $this->hash_key())
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayShoppingCartFooter')
            || !$this->addOrderState('درگاه پرداخت رای پی') )
            return false;
        else
            return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()
            || !Configuration::deleteByName('raypay_user_id')
            || !Configuration::deleteByName('raypay_marketing_id')
            || !Configuration::deleteByName('raypay_sandbox')
            || !Configuration::deleteByName('raypay_success_massage')
            || !Configuration::deleteByName('raypay_failed_massage')
            || !Configuration::deleteByName('raypay_currency')
            || !Configuration::deleteByName('raypay_HASH_KEY') )
            return false;
        else
            return true;
    }

    public function addOrderState($name)
    {
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);

        // check if order state exist
        foreach ($states as $state) {
            if (in_array($this->name, $state)) {
                $state_exist = true;
                break;
            }
        }

        // If the state does not exist, we create it.
        if (!$state_exist) {
            // create new order state
            $order_state = new OrderState();
            $order_state->color = '#bbbbbb';
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->template = '';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;

            // Update object
            $order_state->add();
        }

        return true;
    }

    public function hash_key()
    {
        $en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        $one = rand(1, 26);
        $two = rand(1, 26);
        $three = rand(1, 26);
        return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$three] . rand(0, 9) . rand(10, 99);
    }

    public function getContent()
    {
        if (Tools::isSubmit('raypay_submit')) {
            Configuration::updateValue('raypay_user_id', $_POST['raypay_user_id']);
            Configuration::updateValue('raypay_marketing_id', $_POST['raypay_marketing_id']);
            Configuration::updateValue('raypay_sandbox', $_POST['raypay_sandbox']);
            Configuration::updateValue('raypay_currency', $_POST['raypay_currency']);
            Configuration::updateValue('raypay_success_massage', $_POST['raypay_success_massage']);
            Configuration::updateValue('raypay_failed_massage', $_POST['raypay_failed_massage']);
            $this->_html .= '<div class="conf confirm">' . 'با موفقیت ذخیره شد.' . '</div>';
        }

        $this->_generateForm();
        return $this->_html;
    }

    private function _generateForm()
    {
        $this->_html .= '<div align="center"><form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
        $this->_html .= $this->l('شناسه کاربری :') . '<br><br>';
        $this->_html .= '<input type="text" name="raypay_user_id" value="' . Configuration::get('raypay_user_id') . '" ><br><br>';
        $this->_html .= $this->l('شناسه کسب و کار :') . '<br><br>';
        $this->_html .= '<input type="text" name="raypay_marketing_id" value="' . Configuration::get('raypay_marketing_id') . '" ><br><br>';
        $this->_html .= $this->l('فعالسازی SandBox :') . '<br><br>';
        $this->_html .= '<select name="raypay_sandbox"><option value="yes"' . (Configuration::get('raypay_sandbox') == "yes" ? 'selected="selected"' : "") . '>' . $this->l('بله') . '</option><option value="no"' . (Configuration::get('raypay_sandbox') == "no" ? 'selected="selected"' : "") . '>' . $this->l('خیر') . '</option></select><br><br>';
        $this->_html .= $this->l('واحد پول :') . '<br><br>';
        $this->_html .= '<select name="raypay_currency"><option value="rial"' . (Configuration::get('raypay_currency') == "rial" ? 'selected="selected"' : "") . '>' . $this->l('Rial') . '</option><option value="toman"' . (Configuration::get('raypay_currency') == "toman" ? 'selected="selected"' : "") . '>' . $this->l('Toman') . '</option></select><br><br>';
        $this->_html .= $this->l('پیام پرداخت موفق :') . '<br><br>';
        $this->_html .= '<textarea dir="auto" name="raypay_success_massage" style="margin: 0px; width: 351px; height: 57px;">' . (!empty(Configuration::get('raypay_success_massage')) ? Configuration::get('raypay_success_massage') : "پرداخت شما با موفقیت انجام شد.") . '</textarea><br><br>';
        $this->_html .= 'متن پیامی که می خواهید بعد از پرداخت به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {order_id} برای نمایش شماره سفارش و {invoice_id} برای نمایش شناسه ارجاع بانکی رای پی استفاده نمایید.<br><br>';
        $this->_html .= $this->l('پیام پرداخت ناموفق :') . '<br><br>';
        $this->_html .= '<textarea dir="auto" name="raypay_failed_massage" style="margin: 0px; width: 351px; height: 57px;">' . (!empty(Configuration::get('raypay_failed_massage')) ? Configuration::get('raypay_failed_massage') : "پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.") . '</textarea><br><br>';
        $this->_html .= 'متن پیامی که می خواهید بعد از پرداخت به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {order_id} برای نمایش شماره سفارش و {invoice_id} برای نمایش شناسه ارجاع بانکی رای پی استفاده نمایید.<br><br>';
        $this->_html .= '<input type="submit" name="raypay_submit" value="' . $this->l(' ذخیره کنید') . '" class="button">';
        $this->_html .= '</form><br></div>';
    }

    /**
     * @param \CartCore $cart
     */
    public function do_payment($cart)
    {
        $user_id = Configuration::get('raypay_user_id');
        $marketing_id = Configuration::get('raypay_marketing_id');
        $sandbox = !(Configuration::get('raypay_sandbox') == 'no');
        $invoice_id             = round(microtime(true) * 1000);
        $amount = $cart->getOrderTotal();
        if (Configuration::get('raypay_currency') == "toman") {
            $amount *= 10;
        }

        // Customer information
        $details = $cart->getSummaryDetails();
        $delivery = $details['delivery'];
        $name = $delivery->firstname . ' ' . $delivery->lastname;
        $phone = $delivery->phone_mobile;

        if (empty($phone_mobile)) {
            $phone = $delivery->phone;
        }

        $states = OrderState::getOrderStates((int)$this->context->language->id);
        $state_id = 1; //Awaiting check payment
        // check if order state exist
        foreach ($states as $state) {
            if ( in_array($this->name, $state) ) {
                $state_id = $state['id_order_state'];
                break;
            }
        }

        $this->validateOrder( $cart->id, $state_id, $amount, $this->displayName, '', array(), (int)$this->context->currency->id );
        $order_id = Order::getOrderByCartId((int)($cart->id));

        $desc = 'پرداخت فروشگاه پرستاشاپ نسخه 1.6، سفارش شماره: ' . $order_id;
        $url = $this->context->link->getPageLink('index',true);;
        $callback =  $url. 'modules/raypay/callback.php?do=callback&hash=' .md5($amount . $order_id . Configuration::get('raypay_HASH_KEY')) . '&order_id=' . $order_id ;
        $mail = Context::getContext()->customer->email;

        $data = array(
            'amount'       => strval($amount),
            'invoiceID'    => strval($invoice_id),
            'userID'       => $user_id,
            'redirectUrl'  => $callback,
            'factorNumber' => strval($order_id),
            'marketingID' => $marketing_id,
            'email'        => $mail,
            'mobile'       => $phone,
            'fullName'     => $name,
            'comment'      => $desc,
            'enableSandBox'      => $sandbox,
        );


        $url  = 'https://api.raypay.ir/raypay/api/v1/Payment/pay';
        $options = array('Content-Type: application/json');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$options );
        $result = curl_exec($ch);
        $result = json_decode($result );
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status != 200 || empty($result) || empty($result->Data)) {
            $msg         = sprintf('خطا هنگام ایجاد تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
            $this->saveOrder($msg, Configuration::get( 'PS_OS_ERROR' ), $order_id);
            $this->context->cookie->raypay_message = $msg;
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
            Tools::redirect( "/index.php?controller=$checkout_type&submitReorder=&id_order=$order_id&raypay-message=$msg");
            exit;
        } else {
            $token = $result->Data;
            $link='https://my.raypay.ir/ipg?token=' . $token;
            Tools::redirect($link);
        }
    }

    /**
     * @param $message
     * @param $paymentStatus
     * @param $order_id
     * 13 for waiting ,8 for payment error and Configuration::get('PS_OS_PAYMENT') for payment is OK
     */
    public function saveOrder($message, $paymentStatus, $order_id, $transaction_id = 0)
    {
        $history = new OrderHistory();
        $history->id_order = (int)$order_id;
        $history->changeIdOrderState($paymentStatus, (int)($order_id)); //order status=4
        $history->addWithemail();

        $order_message = new Message();
        $order_message->message = $message;
        $order_message->id_order = (int)$order_id;
        $order_message->add();

        if( !$transaction_id )
            return;

        $sql = 'SELECT reference FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order  = "' . $order_id . '"';
        $reference = Db::getInstance()->executes($sql);
        $reference = $reference[0]['reference'];

        $sql = ' UPDATE `' . _DB_PREFIX_ . 'order_payment` SET `transaction_id` = "' . $transaction_id . '" WHERE `order_reference` = "' . $reference . '"';
        $result = Db::getInstance()->Execute($sql);
    }

    public function error($str)
    {
        return '<div class="alert alert-danger error" dir="rtl" style="text-align: right;padding: 15px;">' . $str . '</div>';
    }

    public function success($str)
    {
        return '<div class="conf alert-success confirm" dir="rtl" style="text-align: right;padding: 15px;">' . $str . '</div>';
    }

    public function hookPayment($params)
    {
        global $smarty;
        $smarty->assign('name', $this->description);

        $output = '';
        if( !empty($_GET['raypay-message']) )
            $output .= $this->error( $_GET['raypay-message'] );

        if ($this->active)
            $output .= $this->display(__FILE__, 'raypay.tpl');

        return $output;
    }

    public function hookDisplayShoppingCartFooter(){
        global $cookie;
        $output = '';
        if( !empty($_GET['raypay-message']) ){
            $output .= $this->error( $_GET['raypay-message'] );
        }
        else if( !empty($cookie->raypay_message) ){
            $output .= $this->error( $cookie->raypay_message );
            $cookie->raypay_message = '';
        }
        else if( !empty($_SESSION['raypay-message']) ){
            $output .= $this->error( $_SESSION['raypay-message'] );
            $_SESSION['raypay-message'] = '';
        }

        return $output;
    }

    public function hookPaymentReturn($params)
    {
        global $smarty;
        $output = '';

        if( !empty($_GET['raypay-message']) ){
            $smarty->assign('message', $_GET['raypay-message']);
        }
        if ($this->active)
            $output .= $this->display(__FILE__, 'raypay-confirmation.tpl');

        return $output;
    }
}