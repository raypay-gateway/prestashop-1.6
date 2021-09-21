<?php
/**
 * RayPay payment gateway
 *
 * @developer Hanieh Ramznpour
 * @publisher RayPay
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */
@session_start();
if (isset($_GET['do'])) {
    include(dirname(__FILE__) . '/../../config/config.inc.php');
    include(dirname(__FILE__) . '/../../header.php');
    include_once(dirname(__FILE__) . '/raypay.php');
    global $cookie;

    $raypay = new raypay;
    if ($_GET['do'] == 'payment') {
        $raypay->do_payment($cart);
    }

    $order_id = $_GET['order_id'];

    if (!empty($order_id) ) {
        $amount = $cart->getOrderTotal();
        if (Configuration::get('raypay_currency') == "toman") {
            $amount *= 10;
        }
        if ( md5($amount . $order_id . Configuration::get('raypay_HASH_KEY')) == $_GET['hash']) {
            $url = 'https://api.raypay.ir/raypay/api/v1/Payment/verify';
            $options = array('Content-Type: application/json');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($_POST));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
            $result = curl_exec($ch);
            $result = json_decode($result);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

                if ( $http_status != 200 ) {
                    $msg = sprintf('خطا هنگام بررسی تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
                    echo $raypay->error( $msg );
                }
                else {
                    $state           = $result->Data->Status;
                    $verify_order_id = $result->Data->FactorNumber;
                    $verify_invoice_id = $result->Data->InvoiceID;
                    $verify_amount   = $result->Data->Amount;

                    if ( empty($verify_order_id) || empty($verify_amount) || $state !== 1 ) {
                        echo $raypay->error( raypay_get_failed_message( $verify_invoice_id, $verify_order_id ) );
                    }
                    else {
                        error_reporting( E_ALL );

                        if ( Configuration::get( 'raypay_currency' ) == "toman" ){
                            $amount /= 10;
                        }

                        $message = raypay_get_success_massage( $verify_invoice_id, $verify_order_id );
                        $raypay->saveOrder( $message, Configuration::get( 'PS_OS_PAYMENT' ), (int)$verify_order_id, $verify_invoice_id);

                        $_SESSION['order' . $verify_order_id] = '';
                        Tools::redirect( 'index.php?controller=order-confirmation'.
                            '&id_cart=' . $cart->id .
                            '&id_module=' . $raypay->id .
                            '&id_order=' . $verify_order_id .
                            '&key=' . Context::getContext()->customer->secure_key .
                            '&raypay-message=' . $raypay->success($message)
                        );
                    }
                }

        } else {
            echo $raypay->error( $raypay->l('پارامترها به درستی وارد نشده اند') );
        }
    }
    else{
        echo $raypay->error( $raypay->l('خطا هنگام بازگشت از درگاه پرداخت') );
    }
    include_once(dirname(__FILE__) . '/../../footer.php');
} else {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

function raypay_get_failed_message($invoice_id, $order_id)
{
    return str_replace(["{invoice_id}", "{order_id}"], [$invoice_id, $order_id], Configuration::get('raypay_failed_massage'));
}

function raypay_get_success_massage($invoice_id, $order_id)
{
    return str_replace(["{invoice_id}", "{order_id}"], [$invoice_id, $order_id], Configuration::get('raypay_success_massage')) ;
}