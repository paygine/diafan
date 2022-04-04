<?php

if (! defined('DIAFAN'))
{
	$path = __FILE__;
	while(! file_exists($path.'/includes/404.php'))
	{
		$parent = dirname($path);
		if($parent == $path) exit;
		$path = $parent;
	}
	include $path.'/includes/404.php';
}

class Payment_paygine_model extends Diafan
{
	public function get($params, $pay)
	{
        $currency = '643';

        if (empty($params["paygine_test"])) {
            $paygine_url = 'https://pay.paygine.com';
        } else {
            $paygine_url = 'https://test.paygine.com';
        }

        $desc=$pay["desc"];

        $amount = $pay["summ"];
        $signature = base64_encode(md5($params["paygine_sector"] . intval($amount * 100) . $currency . $params["paygine_password"]));

        $fiscalPositions='';

        $result["fiscal_position"] = '';

        if(! empty($pay["details"]["discount"]))
        {
            $s = 0;
            foreach($pay["details"]["goods"] as &$r)
            {
                $s += $r["summ"];
            }
            foreach($pay["details"]["goods"] as &$r)
            {
                $r["price"] = number_format($r["price"] * ($pay["summ"]/$s), 2, '.', '');
                $r["summ"] = number_format($r["price"] * $r["count"], 2, '.', '');
            }
        }
        if(! empty($pay["details"]["goods"]))
        {
            $fiscalAmount = 0;
            foreach($pay["details"]["goods"] as $row)
            {
                $result["fiscal_position"].=$row["count"].';';
                $price = $row["price"] * 100;
                $result["fiscal_position"].=$price.';';
                $result["fiscal_position"].=$params["paygine_tax"].';';
                $result["fiscal_position"].=str_ireplace(['|', ';'], ['', ''], $row["name"]).'|';

                $fiscalAmount += $row["count"] * $price;
            }
            if ($pay["details"]["delivery"]["summ"]) {
                $result["fiscal_position"].='1;';
                $price = $pay["details"]["delivery"]["summ"] * 100;
                $result["fiscal_position"].=$price.';';
                $result["fiscal_position"].=$params["paygine_tax"].';';
                $result["fiscal_position"].='Доставка|';

                $fiscalAmount += $price;
            }
            $amountDiff = abs($fiscalAmount - $amount * 100);
            if ($amountDiff) {
                $result["fiscal_position"].='1;';
                $result["fiscal_position"].=$amountDiff.';';
                $result["fiscal_position"].='6;';
                $result["fiscal_position"].='coupon;14';
            }
        }

        if (!empty($params["paygine_kkt"])) {
            if ($params["paygine_kkt"]==1) {
                $TAX = (strlen($params["paygine_tax"]) > 0) ?
                    intval($params["paygine_tax"]) : 7;
                if ($TAX > 0 && $TAX < 7) {
                    $fiscalPositions = $result["fiscal_position"];
                }
            }
        }

        $query = http_build_query(array(
            'sector' => $params["paygine_sector"],
            'reference' => $pay["id"],
            'amount' => intval($amount * 100),
            'fiscal_positions' => $fiscalPositions,
            'description' => $desc,
            'email' => $pay["details"]["email"],
            'phone' => $pay["details"]["phone"],
            'currency' => $currency,
            'mode' => 1,
            'url' => BASE_PATH_HREF.'payment/get/paygine',
            'signature' => $signature
        ));

        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($query) . "\r\n",
                'method'  => 'POST',
                'content' => $query
            )
        ));

        $paygine_order_id = file_get_contents($paygine_url . '/webapi/Register', false, $context);

        $resultUrl = '';
        if (intval($paygine_order_id) == 0) {
            $pay["text"] = $paygine_order_id;
        } else {
            $signature = base64_encode(md5($params["paygine_sector"] . $paygine_order_id . $params["paygine_password"]));
            $resultUrl =  "{$paygine_url}/webapi/Purchase?sector={$params["paygine_sector"]}&id={$paygine_order_id}&signature={$signature}";
        }

        $result['data'] = array(
            'sector' => $params["paygine_sector"],
            'reference' => $pay["id"],
            'amount' => intval($amount * 100),
            'fiscal_positions' => $fiscalPositions,
            'description' => $desc,
            'email' => $pay["details"]["email"],
            'phone' => $pay["details"]["phone"],
            'currency' => $currency,
            'mode' => 1,
            'url' => BASE_PATH_HREF.'payment/get/paygine',
            'signature' => $signature
        );

        //$result['pay'] = $pay;

        $result["resultUrl"]      = $resultUrl;
        $result["text"]      = $pay["text"];

        return $result;
	}
}
