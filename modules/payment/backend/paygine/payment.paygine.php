<?php
/**
 * Обработка данных, полученных от системы Paygine
 *
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2018 OOO «Диафан» (http://www.diafan.ru/)
 */

if (!defined('DIAFAN')) {
    $path = __FILE__;
    while (!file_exists($path . '/includes/404.php')) {
        $parent = dirname($path);
        if ($parent == $path) exit;
        $path = $parent;
    }
    include $path . '/includes/404.php';
}

if (!empty($_REQUEST["callback"])) {
    $xml = file_get_contents("php://input");


    if (!$xml)
        echo("Empty data");
    $xml = simplexml_load_string($xml);

    if (!$xml)
        die("Non valid XML was received");
    $response = json_decode(json_encode($xml));
    if (!$response)
        die("Non valid XML was received");

    $order_id = intval($response->reference);

    if ($order_id == 0)
        die("Invalid order id: {$order_id}");

    $pay = $this->diafan->_payment->check_pay($order_id, 'paygine');

    if (!orderAsPayed($response, $pay)) {
        $this->diafan->_payment->fail($pay, 'pay');
    } else {
        echo("ok");
        $this->diafan->_payment->success($pay, 'pay');
    }
} else {
    $pay = $this->diafan->_payment->check_pay($_REQUEST["reference"], 'paygine');

    $paygine_order_id = intval($_REQUEST["id"]);
    if (!$paygine_order_id)
        return false;

    $paygine_operation_id = intval($_REQUEST["operation"]);
    if (!$paygine_operation_id)
        return false;

    if (checkPaymentStatus($pay, $paygine_order_id, $paygine_operation_id)) {
        $this->diafan->_payment->success($pay);
    } else {
        $this->diafan->_payment->fail($pay);
    }
}


function checkPaymentStatus($pay, $paygine_order_id, $paygine_operation_id)
{
    // check payment operation state
    $signature = base64_encode(md5($pay["params"]['paygine_sector'] . $paygine_order_id . $paygine_operation_id . $pay["params"]['paygine_password']));

    if (!$pay["params"]['paygine_test']) {
        $paygine_url = 'https://pay.paygine.com';
    } else {
        $paygine_url = 'https://test.paygine.com';
    }

    $query = http_build_query(array(
        'sector' => $pay["params"]['paygine_sector'],
        'id' => $paygine_order_id,
        'operation' => $paygine_operation_id,
        'signature' => $signature
    ));
    $context = stream_context_create(array(
        'http' => array(
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . "Content-Length: " . strlen($query) . "\r\n",
            'method' => 'POST',
            'content' => $query
        )
    ));

    $repeat = 3;
    while ($repeat) {

        $repeat--;

        sleep(2);

        $xml = file_get_contents($paygine_url . '/webapi/Operation', false, $context);


        if (!$xml)
            break;

        $xml = simplexml_load_string($xml);
        if (!$xml)
            break;
        $response = json_decode(json_encode($xml));

        if (!$response)
            break;

        $order_id = intval($response->reference);
        if ($order_id == 0)
            return false;

        if (($response->type != 'PURCHASE' && $response->type != 'EPAYMENT') || $response->state != 'APPROVED')
            return false;

        $tmp_response = json_decode(json_encode($response), true);
        unset($tmp_response["signature"]);
        unset($tmp_response["protocol_message"]);

        $signature = base64_encode(md5(implode('', $tmp_response) . $pay["params"]['paygine_password']));
        if (!$signature === $response->signature) {
            break;
        }
        return true;
    }

    return false;
}

function orderAsPayed($response, $pay)
{

    $order_id = intval($response->reference);
    if ($order_id == 0)
        die("Invalid order id: {$order_id}");

    if (!$pay)
        die("No such order id: {$order_id}");


    $tmp_response = (array)$response;
    unset($tmp_response["signature"]);
    $signature = base64_encode(md5(implode('', $tmp_response) . $pay["params"]['paygine_password']));
    if ($signature !== $response->signature)
        die("Invalid signature");

    if (($response->type != 'PURCHASE' && $response->type != 'EPAYMENT' && $response->type != 'AUTHORIZE') || $response->state != 'APPROVED')
        return false;

    return true;
}










