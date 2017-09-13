<?php
/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/6
 * Time: 上午10:57
 */

define('IN_AJAX', TRUE);

if (!defined('IN_ANWSION'))
{
	die;
}

require_once ROOT_PATH . 'models/shang/WxPay.Api.php';
require_once ROOT_PATH . 'models/shang/WxPay.JsApiPay.php';
require_once ROOT_PATH . 'models/shang/WxPay.Notify.php';

class ajax extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'black';
		
		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();
	}

	public function payment_action(){

		if(!$_POST['item_type']){
			$_POST['item_type'] = 'answer';
		}
		if(!$_POST['item_id'] || !$_POST['pay_money']){
			H::ajax_json_output(AWS_APP::RSM(null, -1, '填写打赏金额和打赏对象'));
		}
		switch($_POST['item_type']){
			case "article":
				if(!$item_info = $this->model('article')->get_article_info_by_id($_POST['item_id'])){
					H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
				}
				$item_info['item_type'] = shang_class::TYPE_ARTICLE;
				break;
				
			case 'question':
				if(!$item_info = $this->model('question')->get_question_info_by_id($_POST['item_id'])){
					H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
				}
                $item_info['id']  = $item_info['question_id'];
                $item_info['uid'] = $item_info['published_uid'];
                $item_info['item_type'] = shang_class::TYPE_QUESTION;
				break;

			case 'answer':
				if(!$item_info = $this->model('answer')->get_answer_by_id($_POST['item_id'])) {
					H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
				}
				$item_info['id'] = $item_info['answer_id'];
                $item_info['item_type'] = shang_class::TYPE_ANSWER;
				break;

			default:
				H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
				break;
		}


		if(!is_digits($_POST['pay_money']) || $_POST['pay_money'] < 1){
			H::ajax_json_output(AWS_APP::RSM(null, -1, '打赏的金额需要大于或等于1元呢'));
		}

		if($_POST['pay_way'] AND $_POST['pay_way'] == 'balance'){
            if(($_POST['pay_money'] * 100) > $this->user_info['balance']){
                H::ajax_json_output(AWS_APP::RSM(null, -1, '当前账户余额不足以支付该订单,请选择其它支付方式'));
            }
        }

		$user_info = $this->model('account')->get_user_info_by_uid($item_info['uid']);
		//开始处理逻辑
		//save_shang($uid, $item_type, $item_id, $price)
		if(!$order_info = $this->model('shang')->save_shang($this->user_id, $item_info['item_type'], $item_info['id'], ($_POST['pay_money'] * 100))){
			H::ajax_json_output(AWS_APP::RSM(null, -1, '无法生成支付信息，请稍候重试'));
		}

		if(is_mobile()){
			H::ajax_json_output(AWS_APP::RSM(array(
				'url' => get_js_url('/m/shang/payment/id-' . $order_info['order_id'] . '__pay_way-' . $_POST['pay_way'])
			), 1, null));
		}else{
			H::ajax_json_output(AWS_APP::RSM(array(
				'url' => get_js_url('/shangpay/shang/' . $order_info['order_id'])
			), 1, null));
		}
	}
}
