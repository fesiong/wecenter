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

class main extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'white';

        $rule_action['actions'] = array(
            'qrcode',
            'alipay_notify',
            'wx_notify',
            'aliwap_notify',
            'check_pay'
        );

        return $rule_action;
    }

    public function setup()
    {
        $this->crumb(AWS_APP::lang()->_t('我的打赏'), '/shang/');
    }

    public function index_action()
    {
        if (is_mobile())
        {
            HTTP::redirect('/m/shang/');
        }
        //收到的赏金
        $total['receive'] = $this->model('shang')->get_total_receive_amount($this->user_id);
        $total['send'] = $this->model('shang')->get_total_send_amount($this->user_id);
        $total['withdraw'] = $this->model('shang')->get_total_withdraw_amount($this->user_id);
        $total['balance'] = $this->user_info['balance'];

        TPL::assign('total', $total);

        TPL::output('shang/index');
    }

    public function withdraw_action(){


        TPL::output('shang/withdraw');
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
                $withdraw_list[$key]['user_info'] = $this->user_info;
            }
        }

        TPL::assign('withdraw_list', $withdraw_list);

        TPL::output('shang/withdraw_list');
    }

    public function receive_action(){

        $receive_list = $this->model('shang')->fetch_page('flow', 'item_uid = ' . $this->user_id, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/shang/receive/'),
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

        TPL::output('shang/receive');
    }

    public function send_action(){

        $send_list = $this->model('shang')->fetch_page('shang', "has_pay = " . shang_class::HAS_PAY . ' AND uid = ' . $this->user_id, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/shang/send/'),
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

        TPL::output('shang/send');
    }

    public function qrcode_action(){
        include(AWS_PATH . 'Services/phpqrcode/qrlib.php');
        $url = urldecode($_GET["data"]);
        QRcode::png($url, null, QR_ECLEVEL_L, 4);
    }

    //定期轮询查看是否已经支付过了
    public function check_pay_action(){
        if(!is_digits($_GET['id'])){
            die();
        }
        $order_info = $this->model('shang')->get_shang($_GET['id']);
        if($order_info['has_pay'] != shang_class::HAS_PAY){
            die();
        }

        echo 1;
        exit;
    }

    public function wx_notify_action(){
        $notify = new NativeNotifyCallBack();
        $notify->Handle(true);
    }

    public function aliwap_notify_action()
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

    public function alipay_notify_action()
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