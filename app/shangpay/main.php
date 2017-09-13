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

require_once ROOT_PATH . 'models/shang/WxPay.Api.php';
require_once ROOT_PATH . 'models/shang/WxPay.NativePay.php';
require_once ROOT_PATH . 'models/shang/WxPay.Notify.php';

class main extends AWS_CONTROLLER
{

	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'black'; //黑名单,黑名单中的检查  'white'白名单,白名单以外的检查
		$rule_action['actions'] = array();

		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();

		$this->crumb('Loading...', '/shangpay/');
	}

	public function shang_action()
	{
		$this->crumb('打赏支付', '/shangpay/shang/');
		if(!is_digits($_GET['id'])){
			H::redirect_msg(AWS_APP::lang()->_t('没有找到订单信息'));
		}
		if(!$order_info = $this->model('shang')->get_shang($_GET['id'])){
			H::redirect_msg(AWS_APP::lang()->_t('没有找到订单信息'));
		}
		if($order_info['has_pay'] == shang_class::HAS_PAY){
			H::redirect_msg(AWS_APP::lang()->_t('该订单已经支付过'));
		}

		switch($order_info['item_type']){
			case shang_class::TYPE_ARTICLE:
				if(!$item_info = $this->model('article')->get_article_info_by_id($order_info['item_id'])){
					H::redirect_msg(AWS_APP::lang()->_t('找不到要打赏的对象'));
				}
				$item_info['item_url'] = get_js_url('/article/' . $item_info['id']);
                $order_info['note'] = '文章打赏';
				break;

			case shang_class::TYPE_QUESTION:
				if(!$item_info = $this->model('question')->get_question_info_by_id($order_info['item_id'], false)){
					H::redirect_msg(AWS_APP::lang()->_t('找不到要打赏的对象'));
				}
                $item_info['id'] = $item_info['question_id'];
				$item_info['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                $order_info['note'] = '问题悬赏';
				break;

			case shang_class::TYPE_ANSWER:
				if(!$item_info = $this->model('answer')->get_answer_by_id($order_info['item_id'])) {
					H::redirect_msg(AWS_APP::lang()->_t('找不到要打赏的对象'));
				}
				$item_info['id'] = $item_info['answer_id'];
				$item_info['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                $order_info['note'] = '回答打赏';
				break;

			default:
				break;
		}

		if(get_setting('wxpay_openid')){
			//微信的信息
			$notify = new NativePay();
			$input = new WxPayUnifiedOrder();
			$WxPayConfig = new WxPayConfig();

			$input->SetBody("打赏-" . $order_info['note']);
			$input->SetAttach("item_id:" . $order_info['item_id']);
			$input->SetOut_trade_no($order_info['order_id']);
			$input->SetTotal_fee($order_info['price']);//单位是分
			$input->SetTime_start(date("YmdHis"));
			$input->SetTime_expire(date("YmdHis", time() + 3600));
			$input->SetGoods_tag($order_info['note']);
			$input->SetNotify_url(get_js_url('/shangpay/wx_notify/'));
			$input->SetTrade_type("NATIVE");
			$input->SetProduct_id($order_info['item_id']);
			$result = $notify->GetPayUrl($input);
			$url = $result["code_url"];

			TPL::assign('url', $url);
			//END
		}
		if(get_setting('alipay_partner')){
			//支付宝的信息

			TPL::assign('notify_url', get_js_url('/shangpay/notify/alipay/'));
			TPL::assign('partner', get_setting('alipay_partner'));
			TPL::assign('seller_email', get_setting('alipay_seller_email'));
			TPL::assign('order_id', $order_info['order_id']);
			TPL::assign('amount', number_format($order_info['price'] / 100,2));
			TPL::assign('return_url', $item_info['item_url']);
			TPL::assign('order_name', $order_info['note']);
			TPL::assign('show_url', base_url());
			TPL::assign('sign', $this->model('shang_alipay')->createSign($order_info['order_id'], $order_info['note'], number_format($order_info['price'] / 100,2), $item_info['item_url']));
			//END
		}
		TPL::assign('order_info', $order_info);
		TPL::assign('item_info', $item_info);
		TPL::output('shangpay/shang');
	}

	public function wx_notify_action(){
		$notify = new NativeNotifyCallBack();
		$notify->Handle(true);
	}

	//定期轮询查看是否已经支付过了
	public function check_pay_action(){
	    if(!is_digits($_GET['id'])){
	        die();
        }
        $order_info = $this->model('shang')->get_shang_info($_GET['id']);
        if($order_info['has_pay'] != shang_class::HAS_PAY){
            die();
        }

		echo 1;
		exit;
	}

    public function pay_by_balance_action(){
        if(!$this->user_id){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('您没有登录')));
        }
        if(!$order_info = $this->model('shang')->get_shang_info($_POST['order_id'])){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('订单不存在或状态不正确')));
        }

        if($order_info['has_pay'] == shang_class::HAS_PAY){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('订单状态不正确')));
        }

        if($this->user_info['balance'] < $order_info['price']){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('您的余额不足以支付该笔订单。')));
        }

        $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . $this->user_id);
        $this->model('account')->update_users_fields([
            'balance' => $user_balance - $order_info['price']
        ], $this->user_id);

        $this->model('shang')->set_ok_shang($order_info['order_id'], time(), shang_class::PAY_BALANCE);

        //H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('您已成功支付!')));
    }
}

class NativeNotifyCallBack extends WxPayNotify
{

	public function NotifyProcess($data, &$msg)
	{
		if(!$order_info = AWS_APP::model('shang')->get_shang_info($data['out_trade_no'])){
			return false;
		}

		if($order_info['has_pay'] == shang_class::HAS_PAY){
			return true;
		}

        AWS_APP::model('shang')->set_ok_shang($order_info['order_id'], $data['transaction_id'], shang_class::PAY_WEIXIN);

		return true;
	}
}