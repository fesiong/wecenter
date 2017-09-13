<?php

/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/7
 * Time: 下午3:10
 */
if (!defined('IN_ANWSION'))
{
    die;
}

define('IN_MOBILE', true);
require_once ROOT_PATH . 'models/shang/WxPay.Api.php';
require_once ROOT_PATH . 'models/shang/WxPay.JsApiPay.php';
require_once ROOT_PATH . 'models/shang/WxPay.Notify.php';

class shang extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'black';
        $rule_action['actions'] = array();

        return $rule_action;
    }

    public function setup()
    {
        if ($_GET['ignore_ua_check'] == 'FALSE')
        {
            HTTP::set_cookie('_ignore_ua_check', 'FALSE');
        }

        if (!is_mobile())
        {
            switch ($_GET['act'])
            {
                default:
                    HTTP::redirect('/shang/');
                    break;
            }
        }

        if (!$this->user_id AND !$this->user_info['permission']['visit_site'] AND $_GET['act'] != 'login' AND $_GET['act'] != 'register')
        {
            HTTP::redirect('/m/login/url-' . base64_encode($_SERVER['REQUEST_URI']));
        }

        switch ($_GET['act'])
        {
            default:
                if (!$this->user_id)
                {
                    HTTP::redirect('/m/login/url-' . base64_encode($_SERVER['REQUEST_URI']));
                }
                break;
            case 'shang':
            case 'payment':
                break;
        }

        TPL::import_clean();

        TPL::import_css(array(
            'mobile/css/mobile.css',
        ));

        TPL::import_js(array(
            'js/jquery.2.js',
            'js/jquery.form.js',
            'mobile/js/framework.js',
            'mobile/js/aws-mobile.js',
            'mobile/js/app.js',
            'mobile/js/aw-mobile-template.js'
        ));

        if (in_weixin())
        {
            $noncestr = mt_rand(1000000000, 9999999999);

            TPL::assign('weixin_noncestr', $noncestr);

            $jsapi_ticket = $this->model('openid_weixin_weixin')->get_jsapi_ticket($this->model('openid_weixin_weixin')->get_access_token(get_setting('weixin_app_id'), get_setting('weixin_app_secret')));

            $url = ($_SERVER['HTTPS'] AND !in_array(strtolower($_SERVER['HTTPS']), array('off', 'no'))) ? 'https' : 'http';

            $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            TPL::assign('weixin_signature', $this->model('openid_weixin_weixin')->generate_jsapi_ticket_signature(
                $jsapi_ticket,
                $noncestr,
                TIMESTAMP,
                $url
            ));
        }
    }

    public function index_action(){
        //收到的赏金
        $total['receive'] = $this->model('shang')->get_total_receive_amount($this->user_id);
        $total['send'] = $this->model('shang')->get_total_send_amount($this->user_id);
        $total['withdraw'] = $this->model('shang')->get_total_withdraw_amount($this->user_id);
        $total['balance'] = $this->user_info['balance'];

        TPL::assign('total', $total);
        TPL::output('m/shang/index');
    }

    public function withdraw_action(){


        TPL::output('m/shang/withdraw');
    }

    public function withdraw_list_action(){
        $withdraw_list = $this->model('shang')->fetch_page('withdraw', 'uid = ' . $this->user_id, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/shang/withdraw_list/'),
            'total_rows' => $totals,
            'per_page' => 10
        ))->create_links());
        if($withdraw_list){
            foreach($withdraw_list as $key => $val){
                $user_ids[] = $val['uid'];
            }
            $user_infos = $this->model('account')->get_user_info_by_uids($user_ids);
            foreach($withdraw_list as $key => $val){
                $withdraw_list[$key]['user_info'] = $user_infos[$val['uid']];
            }
        }

        TPL::assign('withdraw_list', $withdraw_list);

        TPL::output('m/shang/withdraw_list');
    }

    public function receive_action(){

        $receive_list = $this->model('shang')->fetch_page('flow', 'item_uid = ' . $this->user_id, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/m/shang/receive/'),
            'total_rows' => $totals,
            'per_page' => 10
        ))->create_links());

        if($receive_list){
            foreach($receive_list as $key => $val){
                $user_ids[] = $val['uid'];
                switch($val['item_type']){
                    case shang_class::TYPE_ARTICLE:
                        if($item_info = $this->model('article')->get_article_info_by_id($val['item_id'])){
                            $val['item_url'] = get_js_url('/article/' . $item_info['id']);
                            $val['item_title'] = $item_info['title'];
                        }
                        $val['item_type'] = '文章';
                        break;

                    case shang_class::TYPE_ANSWER:
                        if($item_info = $this->model('answer')->get_answer_by_id($val['item_id'])) {
                            $val['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = cjk_substr($item_info['answer_content'], 0, 30, 'UTF-8', '...');
                        }
                        $item_info['id'] = $item_info['answer_id'];
                        $val['item_type'] = '问题回复';
                        break;

                    default:
                        break;
                }
                $receive_list[$key] = $val;
            }
            $user_infos = $this->model('account')->get_user_info_by_uids($user_ids);
            foreach($receive_list as $key => $val){
                $receive_list[$key]['user_info'] = $user_infos[$val['uid']];
            }
        }
        TPL::assign('receive_list', $receive_list);

        TPL::output('m/shang/receive');
    }

    public function send_action(){

        $send_list = $this->model('shang')->fetch_page('shang', "has_pay = " . shang_class::HAS_PAY . ' AND uid = ' . $this->user_id, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/m/shang/send/'),
            'total_rows' => $totals,
            'per_page' => 10
        ))->create_links());

        if($send_list){
            foreach($send_list as $key => $val){
                switch($val['item_type']){
                    case shang_class::TYPE_ARTICLE:
                        if($item_info = $this->model('article')->get_article_info_by_id($val['item_id'])){
                            $val['item_url'] = get_js_url('/article/' . $item_info['id']);
                            $val['item_title'] = $item_info['title'];
                            $uids[] = $val['item_uid'] = $item_info['uid'];
                        }
                        $val['item_type'] = '文章';
                        break;

                    case shang_class::TYPE_QUESTION:
                        if($item_info = $this->model('question')->get_question_info_by_id($val['item_id'])){
                            $val['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = $item_info['question_content'];
                            $uids[] = $val['item_uid'] = $item_info['published_uid'];
                        }
                        $val['item_type'] = '问题悬赏';
                        break;

                    case shang_class::TYPE_ANSWER:
                        if($item_info = $this->model('answer')->get_answer_by_id($val['item_id'])) {
                            $val['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = cjk_substr(strip_tags(htmlspecialchars_decode($item_info['answer_content'])), 0, 30, 'UTF-8', '...');
                            $uids[] = $val['item_uid'] = $item_info['uid'];
                        }
                        $item_info['id'] = $item_info['answer_id'];
                        $val['item_type'] = '问题回复';
                        break;

                    default:
                        break;
                }
                $send_list[$key] = $val;
            }
            $user_infos = $this->model('account')->get_user_info_by_uids($uids);

            foreach($send_list as $key => $val){
                $send_list[$key]['user_info'] = $user_infos[$val['item_uid']];
            }
        }
        TPL::assign('send_list', $send_list);

        TPL::output('m/shang/send');
    }

    public function shang_action(){
        if($_POST){

        }else{
            switch($_GET['item_type']){
                case "article":
                    if($item_info = $this->model('article')->get_article_info_by_id($_GET['id'])){
                        $item_info['item_url'] = get_js_url('/m/article/' . $item_info['id']);
                        $item_info['item_title'] = $item_info['title'];
                    }
                    break;

                case 'question':
                    if($item_info = $this->model('question')->get_question_info_by_id($_GET['id'])){
                        $item_info['id']  = $item_info['question_id'];
                        $item_info['uid'] = $item_info['published_uid'];
                        $item_info['item_url'] = get_js_url('/m/question/' . $item_info['question_id']);
                        $item_info['item_title'] = $item_info['question_content'];
                    }
                    break;

                case 'answer':
                    if($item_info = $this->model('answer')->get_answer_by_id($_GET['id'])) {
                        $item_info['item_url'] = get_js_url('/m/question/' . $item_info['question_id']);
                        $item_info['item_title'] = cjk_substr($item_info['answer_content'], 0, 30, 'UTF-8', '...');
                        $item_info['id'] = $item_info['answer_id'];
                    }
                    break;

                default:
                    break;
            }
            if(!$item_info){
                H::redirect_msg(AWS_APP::lang()->_t('打赏的对象不存在啦'), get_js_url('/m/'));
            }
            $item_info['user_info'] = $this->model('account')->get_user_info_by_uid($item_info['uid']);

            TPL::assign('item_info', $item_info);
            TPL::output('m/shang/shang');
        }

    }

    public function ajax_payment_action(){
        $this->model('shang')->save_shang($this->user_id, $_POST['item_type'], $_POST['item_id'], $_POST['pay_money'], $_POST['pay_way']);
    }

    public function payment_action(){
        $this->crumb('打赏支付', '/m/shang/payment/');
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
                $item_info['item_url'] = get_js_url('/m/article/' . $item_info['id']);
                $order_info['note'] = '文章打赏';
                break;

            case shang_class::TYPE_QUESTION:
                if(!$item_info = $this->model('question')->get_question_info_by_id($order_info['item_id'], false)){
                    H::redirect_msg(AWS_APP::lang()->_t('找不到要打赏的对象'));
                }
                $item_info['id'] = $item_info['question_id'];
                $item_info['item_url'] = get_js_url('/m/question/' . $item_info['question_id']);
                $order_info['note'] = '问题悬赏';
                break;

            case shang_class::TYPE_ANSWER:
                if(!$item_info = $this->model('answer')->get_answer_by_id($order_info['item_id'])) {
                    H::redirect_msg(AWS_APP::lang()->_t('找不到要打赏的对象'));
                }
                $item_info['id'] = $item_info['answer_id'];
                $item_info['item_url'] = get_js_url('/m/question/' . $item_info['question_id']);
                $order_info['note'] = '回答打赏';
                break;

            default:
                break;
        }
        switch ($_GET['pay_way']){
            default:
            case 'balance':
                if($this->user_info['balance'] < $order_info['money']){
                    H::redirect_msg(AWS_APP::lang()->_t('您的余额不足以支付该笔订单。'));
                }

                $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . $this->user_id);
                $this->model('account')->update_users_fields([
                    'balance' => $user_balance - $order_info['money']
                ], $this->user_id);

                $this->model('shang')->set_ok_shang($order_info['order_id'], time(), shang_class::PAY_BALANCE);
                break;
            case 'weixin':
                if(in_weixin() AND get_setting('wxpay_openid')){
                    //微信的信息
                    $tools = new JsApiPay();
                    $openId = $tools->GetOpenid();
                    //
                    if($openId) {
                        $input = new WxPayUnifiedOrder();

                        $input->SetBody("打赏-" . $order_info['note']);
                        $input->SetAttach("item_id:" . $order_info['item_id']);
                        $input->SetOut_trade_no($order_info['order_id']);
                        $input->SetTotal_fee($order_info['money']);//单位是分
                        $input->SetTime_start(date("YmdHis"));
                        $input->SetTime_expire(date("YmdHis", time() + 3600));
                        $input->SetGoods_tag($order_info['note']);
                        $input->SetNotify_url(get_js_url('/shang/wx_notify/'));
                        $input->SetTrade_type("JSAPI");
                        $input->SetProduct_id($order_info['item_id']);
                        $input->SetOpenid($openId);

                        $order = WxPayApi::unifiedOrder($input);
                        $jsApiParameters = $tools->GetJsApiParameters($order);
                        TPL::assign('jsApiParameters', $jsApiParameters);
                        //END
                    }
                }
                break;
            case 'alipay':
                if(get_setting('alipay_partner')){
                    //支付宝的信息
                    $notify_url = get_js_url('/shang/aliwap_notify/');
                    $call_back_url = $item_info['item_url'];
                    $order_name = "打赏-" . $order_info['note'];

                    $req_id = $order_info['order_id'];

                    $html_text = $this->model('shang_aliwap')->buildRequestHttp(array(
                        "service" => "alipay.wap.trade.create.direct",
                        "partner" => get_setting('alipay_partner'),
                        "sec_id" => strtoupper('MD5'),
                        "format"    => 'xml',
                        "v" => '2.0',
                        "req_id"    => $req_id,
                        "req_data"  => '<direct_trade_create_req><notify_url>' . $notify_url . '</notify_url><call_back_url>' . $call_back_url . '</call_back_url><seller_account_name>' . get_setting('alipay_seller_email') . '</seller_account_name><out_trade_no>' . $req_id . '</out_trade_no><subject>' . $order_name . '</subject><total_fee>' . number_format($order_info['money'] / 100,2) . '</total_fee><merchant_url>' . base_url() . '</merchant_url></direct_trade_create_req>',
                        "_input_charset"    => strtolower('utf-8')
                    ));
                    // 解析远程模拟提交后返回的信息
                    $para_html_text = $this->model('shang_aliwap')->parseResponse(urldecode($html_text));

                    // 获取 request_token
                    $request_token = $para_html_text['request_token'];

                    $alipay_form = $this->model('shang_aliwap')->buildRequestForm2(array(
                        "service" => "alipay.wap.auth.authAndExecute",
                        "partner" => get_setting('alipay_partner'),
                        "sec_id" => strtoupper('MD5'),
                        "format"    => 'xml',
                        "v" => '2.0',
                        "req_id"    => $req_id,
                        "req_data"  => '<auth_and_execute_req><request_token>' . $request_token . '</request_token></auth_and_execute_req>',
                        "_input_charset"    => strtolower('utf-8')
                    ), 'get', '确认');

                    TPL::assign('alipay_form', $alipay_form);
                    //END
                }
                break;
        }

        TPL::assign('order_info', $order_info);
        TPL::assign('item_info', $item_info);
        TPL::output('m/shang/payment');
    }
}
