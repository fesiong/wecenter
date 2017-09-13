<?php
/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/6
 * Time: 上午10:57
 */


if (!defined('IN_ANWSION'))
{
	die;
}

class notify extends AWS_CONTROLLER
{

	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'black';
		$rule_action['actions'] = array();

		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();
	}

	public function aliwap_action()
	{
		$result = $this->model('shang_aliwap')->verifyNotify();


		$verify_result = 'fail';

		if ($result)
		{
			$doc = new DOMDocument();

			switch (get_setting('alipay_sign_type')) {
                default:
				case 'MD5':
					$doc->loadXML($_POST['notify_data']);

					break;

				case '0001':
					$doc->loadXML($this->model('shang_aliwap')->decrypt($_POST['notify_data']));

					break;
			}

			if ($doc->getElementsByTagName("notify")->item(0)->nodeValue)
			{
				//商户订单号
				$out_trade_no = $doc->getElementsByTagName("out_trade_no")->item(0)->nodeValue;
				//支付宝交易号
				$trade_no = $doc->getElementsByTagName( "trade_no" )->item(0)->nodeValue;
				//交易状态
				$trade_status = $doc->getElementsByTagName( "trade_status" )->item(0)->nodeValue;

				$order_info = $this->model('shang')->get_shang_info($out_trade_no);

				if ($trade_status == 'TRADE_FINISHED' OR $trade_status == 'TRADE_SUCCESS')
				{
                    $this->model('shang')->set_ok_shang($order_info['order_id'], $trade_no, shang_class::PAY_ALIPAY);

					$verify_result = 'success';
				}
			}
		}

		exit($verify_result);
	}

	public function alipay_action()
	{
		$result = $this->model('shang_alipay')->verifyNotify();

        $order_info = $this->model('shang')->get_shang_info($_POST['out_trade_no']);

		if ($result AND $_POST['total_fee'] == ($order_info['price'] / 100))
		{
            $this->model('shang')->set_ok_shang($order_info['order_id'], $_POST['trade_no'], shang_class::PAY_ALIPAY);

			$result = 'success';
		}
		else
		{
			$result = 'fail';
		}

		exit($result);
	}
}