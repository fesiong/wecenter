<?php

/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/5
 * Time: 下午4:53
 */

if (!defined('IN_ANWSION'))
{
    die;
}

require_once ROOT_PATH . 'models/shang/WxPay.Api.php';
require_once ROOT_PATH . 'models/shang/WxPay.NativePay.php';
require_once ROOT_PATH . 'models/shang/WxPay.Notify.php';
require_once ROOT_PATH . 'models/shang/alipay.php';

class shang_class extends AWS_MODEL
{
    const PAY_ALIPAY     = 1;//支付宝
    const PAY_WEIXIN     = 2;//微信
    const PAY_BALANCE    = 3;//余额支付

    const TYPE_QUESTION  = 1;//问题悬赏
    const TYPE_ANSWER    = 2;//回复打赏
    const TYPE_ARTICLE   = 3;//文章打赏

    const HAS_PAY        = 1;//是否已支付

    const PAY_TYPE_SPARE = 1;//打赏
    const PAY_TYPE_OFFER = 2;//悬赏

    const STATUS_WAIT    = 0;
    const STATUS_OK      = 1;
    const STATUS_CLOSE   = 2;

    public function save_shang($uid, $item_type, $item_id, $money, $pay_way){
        if(!$item_type OR !$item_id OR $money < 1){
            H::ajax_json_output(AWS_APP::RSM(null, -1, '填写打赏金额和打赏对象'));
        }
        switch($item_type){
            case "article":
                if(!$item_info = $this->model('article')->get_article_info_by_id($item_id)){
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
                }
                $item_info['item_type'] = self::TYPE_ARTICLE;
                break;
                
            case 'question':
                if(!$item_info = $this->model('question')->get_question_info_by_id($item_id)){
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
                }
                $item_info['id']  = $item_info['question_id'];
                $item_info['uid'] = $item_info['published_uid'];
                $item_info['item_type'] = self::TYPE_QUESTION;
                break;

            case 'answer':
                if(!$item_info = $this->model('answer')->get_answer_by_id($item_id)) {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
                }
                $item_info['id'] = $item_info['answer_id'];
                $item_info['item_type'] = self::TYPE_ANSWER;
                break;

            default:
                H::ajax_json_output(AWS_APP::RSM(null, -1, '找不到要打赏的对象'));
                break;
        }

        if($pay_way == 'balance'){
            $user_info = $this->fetch_row('users', 'uid = ' . intval($uid));
            if(($money * 100) > $user_info['balance']){
                H::ajax_json_output(AWS_APP::RSM(null, -1, '当前账户余额不足以支付该订单,请选择其它支付方式'));
            }
        }

        switch ($pay_way) {
            case 'balance':
                $way = self::PAY_BALANCE;
                break;
            case 'alipay':
                $way = self::PAY_ALIPAY;
                break;
            case 'weixin':
            default:
                $way = self::PAY_WEIXIN;
                break;
        }

        $order_id = date('ymdHis') . substr(microtime(), 2, 6);
        $id = $this->insert('shang', [
            'uid' => intval($uid),
            'item_type' => $item_info['item_type'],
            'item_id'   => intval($item_id),
            'money'     => $money * 100,//单位：分
            'pay_way'   => $way,
            'add_time'  => time(),
            'order_id'  => $order_id
        ]);

        if(is_mobile()){
            H::ajax_json_output(AWS_APP::RSM(array(
                'url' => get_js_url('/m/shang/payment/id-' . $order_id . '__pay_way-' . $pay_way)
            ), 1, null));
        }

        return $this->make_payment($order_id);
    }

    public function make_payment($order_id, $from = null){
        $order = $this->get_shang($order_id);
        if(!$order){
            H::ajax_json_output(AWS_APP::RSM(null, -1, '订单不存在'));
        }

        $note = '打赏';
        switch ($order['pay_way']) {
            case self::PAY_BALANCE:
                $user_info = $this->fetch_row('users', 'uid = ' . intval($order['uid']));
                if($order['money'] > $user_info['balance']){
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '当前账户余额不足以支付该订单,请选择其它支付方式'));
                }
                $this->model('account')->update_users_fields([
                    'balance' => $user_info['balance'] - $order['money']
                ], $this->user_id);

                $this->model('shang')->set_ok_shang($order['order_id'], time(), self::PAY_BALANCE);

                H::ajax_json_output(AWS_APP::RSM(null, 1, '余额支付成功，感谢您的支持！'));
                break;

            case self::PAY_ALIPAY:
                if(get_setting('alipay_partner')){
                    //支付宝的信息
                    $alipay = new shang_alipay_class();
                    $alipay_query['_input_charset'] = 'utf-8';
                    $alipay_query['return_url']     = urlencode(get_js_url(str_replace('/?/', '/', $_SERVER['REQUEST_URI'])));
                    $alipay_query['notify_url']     = urlencode(get_js_url('/shang/alipay_notify/'));
                    $alipay_query['out_trade_no']   = $order['order_id'];
                    $alipay_query['partner']        = get_setting('alipay_partner');
                    $alipay_query['payment_type']   = 1;
                    $alipay_query['seller_email']   = urlencode(get_setting('alipay_seller_email'));
                    $alipay_query['service']        = 'create_direct_pay_by_user';
                    $alipay_query['show_url']       = urlencode(base_url());
                    $alipay_query['subject']        = urlencode($note);
                    $alipay_query['total_fee']      = number_format(($order['money']/100), 2);
                    $alipay_query['sign']           = $alipay->createSign($order['order_id'], $note,  number_format(($order['money']/100), 2), get_js_url('/shang/alipay_notify/'), get_js_url(str_replace('/?/', '/', $_SERVER['REQUEST_URI'])));
                    $alipay_query['sign_type']      = 'MD5';

                    $alipay_url = 'https://mapi.alipay.com/gateway.do?' . http_build_query($alipay_query);

                    H::ajax_json_output(AWS_APP::RSM(array('url' => $alipay_url), 1, null));
                    //END
                }else{
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '系统没开启支付宝支付！'));
                }
                break;
            case self::PAY_WEIXIN:
                if(get_setting('wxpay_openid')){
                    //微信的信息
                    $notify = new NativePay();
                    $input = new WxPayUnifiedOrder();

                    $input->SetBody($note);
                    $input->SetAttach("item_id:" . $order['item_id']);
                    $input->SetOut_trade_no($order['order_id']);
                    $input->SetTotal_fee($order['price']);//单位是分
                    $input->SetTime_start(date("YmdHis"));
                    $input->SetTime_expire(date("YmdHis", time() + 3600));
                    $input->SetGoods_tag($order['note']);
                    $input->SetNotify_url(get_js_url('/shang/wx_notify/'));
                    $input->SetTrade_type("NATIVE");
                    $input->SetProduct_id($order['item_id']);
                    $result = $notify->GetPayUrl($input);
                    $url = $result["code_url"];
                    $qr_code = get_js_url('/shang/qrcode/?data=' . urlencode($url));

                    H::ajax_json_output(AWS_APP::RSM(null, 1, '<div style="text-align:center"><div>打开微信扫一扫 支付</div><img width="200" src="'.$qr_code.'"></div><script>setInterval(function(){$.get("'. get_js_url('shang/check_pay/' . $order['order_id']) .'", function(data){if(data == 1){FE.alert("付款成功，感谢您的支持！");setTimeout(function(){window.location.href = "'.get_js_url(str_replace('/?/', '/', $_SERVER['REQUEST_URI'])).'";}, 3000);}});},5000);</script>'));
                    //END
                }else{
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '系统没开启微信支付！'));
                }
                break;
        }
    }

    public function get_shang($order_id){
        if(!is_digits($order_id)){
            return false;
        }
        return $this->fetch_row('shang', "order_id = '$order_id'");
    }

    public function set_ok_shang($order_id, $terrace_id, $pay_way = self::PAY_ALIPAY){
        if(!is_digits($order_id)){
            return false;
        }
        if(!$shang = $this->get_shang($order_id)){
            return false;
        }
        if($shang['has_pay'] == self::HAS_PAY){
            return true;
        }
        $this->update('shang', [
            'pay_time'   => time(),
            'has_pay'    => self::HAS_PAY,
            'terrace_id' => $terrace_id,
            'pay_way'    => $pay_way
        ], "order_id = '$order_id'");

        switch ($shang['item_type']){
            case self::TYPE_ANSWER:
                if($answer = $this->model('answer')->get_answer_by_id($shang['item_id'])){
                    $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . intval($answer['uid']));
                    $this->model('account')->update_users_fields([
                        'balance' => $user_balance + $shang['money']
                    ], $answer['uid']);

                    $this->log_flow($shang['uid'], $shang['order_id'], $shang['money'],
                        $shang['item_id'], $shang['item_type'], $answer['uid']);

                    $notification_id = $this->model('notify')->send($shang['uid'], $answer['uid'], notify_class::TYPE_CONTEXT, notify_class::CATEGORY_CONTEXT, $shang['item_id'], [
                        'content' => '有人给你的回复打赏了'.($shang['money'] / 100).'元，<a href="' . get_js_url('shang/'). '">前去查看</a>',
                    ]);
                }

                break;
            case self::TYPE_ARTICLE:
                if($article = $this->model('article')->get_article_info_by_id($shang['item_id'])){
                    $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . intval($article['uid']));
                    $this->model('account')->update_users_fields([
                        'balance' => $user_balance + $shang['money']
                    ], $article['uid']);

                    $this->log_flow($shang['uid'], $shang['order_id'], $shang['money'],
                        $shang['item_id'], $shang['item_type'], $article['uid']);

                    $notification_id = $this->model('notify')->send($shang['uid'], $article['uid'], notify_class::TYPE_CONTEXT, notify_class::CATEGORY_CONTEXT, $shang['item_id'], [
                        'content' => '有人给你的文章打赏了'.($shang['money'] / 100).'元，<a href="' . get_js_url('shang/'). '">前去查看</a>',
                    ]);
                }
                break;
            case self::TYPE_QUESTION;
                if($question = $this->model('question')->get_question_info_by_id($shang['item_id'], false)){
                    $question_money = $this->model('question')->fetch_one('question', 'money', 'question_id = ' . intval($question['question_id']));
                    $this->update('question', [
                        'money'       => $question_money + $shang['money'],
                        'expity_date' => 3,
                        'pay_time'    => time()
                    ], "question_id = " . $question['question_id']);
                }
                break;
            default:
                break;

        }

        return true;
    }

    public function log_flow($uid, $order_id, $money, $item_id, $item_type, $item_uid, $pay_type = self::PAY_TYPE_SPARE){
        $id = $this->insert('flow', [
            'uid'       => intval($uid),
            'order_id'  => $order_id,
            'money'     => $money,
            'item_id'   => $item_id,
            'item_type' => $item_type,
            'item_uid'  => $item_uid,
            'pay_type'  => $pay_type,
            'add_time'  => time()
        ]);

        return $id;
    }

    public function set_ok_question_shang($uid, $money, $answer_id){
        if($answer = $this->model('answer')->get_answer_by_id($answer_id)){
            $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . intval($answer['uid']));
            $this->model('account')->update_users_fields([
                'balance' => $user_balance + $money
            ], $answer['uid']);

            $this->log_flow($uid, $answer['question_id'], $money,
                $answer['answer_id'], self::TYPE_ANSWER, $answer['uid'], self::PAY_TYPE_OFFER);

            $notification_id = $this->model('notify')->send($uid, $answer['uid'], notify_class::TYPE_CONTEXT, notify_class::CATEGORY_CONTEXT, $answer['question_id'], [
                'content' => '有人给你的回复悬赏了'.($money / 100).'元，<a href="' . get_js_url('shang/'). '">前去查看</a>',
            ]);
        }
    }

    public function auto_question_shang(){
        $old_time = strtotime('-3 day');
        $question_list = $this->fetch_all('question', "money > 0 AND has_pay = 0");
        if($question_list){
            foreach ($question_list as $key => $value) {
                if($value['expity_date']){
                    $old_time = strtotime('-' . $value['expity_date'] . ' day');
                }
                if($value['pay_time'] > $old_time){
                    unset($question_list[$key]);
                }
            }
            if($question_list){
                foreach ($question_list as $key => $value) {
                    $answer_list = $this->model('answer')->get_answer_list_by_question_id($value['question_id'], 3, null, 'agree_count DESC, add_time ASC');
                    if($answer_list){
                        $count = sizeof($answer_list);
                        $first_money = $one_money = intval($value['money']/$count);

                        if($one_money * $count < $value['money']){
                            $first_money = $one_money + ($value['money'] - $one_money * $count);
                        }

                        $first = true;
                        foreach ($answer_list as $k => $v) {
                            if($first){
                                $money = $first_money;
                                $first = false;
                            }else{
                                $money = $one_money;
                            }
                            if($money){
                                $this->set_ok_question_shang($value['published_uid'], $money, $v['answer_id']);
                            }
                        }

                        $this->set_question_has_pay($value['question_id']);
                    }
                }
            }
        }
    }

    public function set_question_has_pay($question_id){
        return $this->update('question', [
            'has_pay' => self::HAS_PAY
        ], 'question_id = ' . intval($question_id));
    }

    public function received_users($limit = 5){
        if($order_list = AWS_APP::cache()->get('received_users' . $limit))
        {
            return $order_list;
        }
        $order_list = $this->query_all('select *,sum(money) as money from ' . get_table('flow') . ' GROUP BY item_uid order by money desc', $limit);
        foreach($order_list as $key => $val){
            $uids[] = $val['item_uid'];
        }
        $user_infos = $this->model('account')->get_user_info_by_uids($uids);
        foreach($order_list as $key => $val){
            $order_list[$key]['user_info'] = $user_infos[$val['answer_uid']];
        }
        AWS_APP::cache()->set('received_users' . $limit , $order_list, 600);

        return $order_list;
    }

    public function sender_users($limit = 5){
        if($order_list = AWS_APP::cache()->get('sender_users' . $limit))
        {
            return $order_list;
        }
        $order_list = $this->query_all('select *,sum(money) as money from ' . get_table('shang') . ' where has_pay = '.self::HAS_PAY.' GROUP BY uid order by money desc', $limit);
        foreach($order_list as $key => $val){
            $uids[] = $val['uid'];
        }
        $user_infos = $this->model('account')->get_user_info_by_uids($uids);
        foreach($order_list as $key => $val){
            $order_list[$key]['user_info'] = $user_infos[$val['uid']];
        }
        AWS_APP::cache()->set('sender_users' . $limit , $order_list, 600);

        return $order_list;
    }

    public function get_withdraw_info($id){
        if($withdraw_info = $this->fetch_row('withdraw', 'id = ' . intval($id))){
            $withdraw_info['user_info'] = $this->model('account')->get_user_info_by_uid($withdraw_info['uid'], true);
        }
        return $withdraw_info;
    }

    public function get_total_receive_amount($uid){
        return $this->sum('flow', 'money', "item_uid = " . intval($uid));
    }

    public function get_total_send_amount($uid){
        return $this->sum('shang', 'money', "has_pay = " . self::HAS_PAY . " and uid = " . intval($uid));
    }

    public function get_total_withdraw_amount($uid){
        return $this->sum('withdraw', 'money', "status = " . self::STATUS_OK . " and uid = " . intval($uid));
    }

    public function withdraw_apply($uid, $amount){
        $user_info = $this->model('account')->get_user_info_by_uid($uid);

        if($user_info['balance'] < $amount){
            return false;
        }
        $real_money = $amount * 98 / 100;
        $this->insert('withdraw', array(
            'uid' => intval($uid),
            'money' => intval($amount),
            'real_money' => $real_money,
            'add_time' => time(),
            'status' => self::STATUS_WAIT,
        ));

        $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . intval($uid));
        $this->update('users', array(
            'balance' => intval($user_balance - $amount)
        ), 'uid = ' . intval($uid));

        return true;
    }

    public function get_shang_info($order_id){
        if(!is_digits($order_id)){
            return false;
        }
        return $this->fetch_row('shang', "order_id = '$order_id'");
    }

    public function get_answer_shang($answer_id){
        return $this->sum('flow', 'money', "item_type = " . self::TYPE_ANSWER . " AND item_id = " . intval($answer_id));
    }

    public function get_article_shang($article_id){
        return $this->sum('flow', 'money', "item_type = " . self::TYPE_ARTICLE . " AND item_id = " . intval($article_id));
    }

    public function withdraw_close($id){
        if(!$withdraw_info = $this->get_withdraw_info($id)){
            return false;
        }

        $user_balance = $this->model('account')->fetch_one('users', 'balance', 'uid = ' . intval($withdraw_info['uid']));
        $this->update('users', array(
            'balance' => intval($user_balance + $withdraw_info['money'])
        ), 'uid = ' . intval($withdraw_info['uid']));

        $this->update('withdraw', array(
            'status' => self::STATUS_CLOSE
        ), 'id = ' . intval($id));
        return true;
    }

    public function withdraw_ok($id, $terrace_id = null, $info = null){
        if(!$withdraw_info = $this->get_withdraw_info($id)){
            return false;
        }

        $this->update('withdraw', array(
            'status' => self::STATUS_OK,
            'terrace_id' => htmlspecialchars($terrace_id),
            'info' => htmlspecialchars($info),
            'pay_time' => time()
        ), 'id = ' . intval($id));
        return true;
    }

    public function get_shang_users_by_question_id($question_id){
        $flows = $this->fetch_all('flow', 'order_id = ' . intval($question_id));

        if($flows){
            foreach ($flows as $key => $val){
                $uids[] = $val['item_uid'];
            }
        }
        if($uids){
            $user_infos = $this->model('account')->get_user_info_by_uids($uids);
        }

        return $user_infos;
    }

    public function get_last_shang_list($limit = 5){
        $flow = $this->fetch_all('flow', null, 'add_time DESC', $limit);
        if($flow){
            foreach ($flow as $key => $val){
                switch ($val['item_type']){
                    case self::TYPE_ARTICLE:
                        $val['item_type']  = '文章';
                        $val['item_info']  = $this->model('article')->get_article_info_by_id($val['item_id']);
                        $val['item_url']   = get_js_url('/article/' . $val['item_info']['id']);
                        $val['item_title'] = $val['item_info']['title'];
                        break;
                    case self::TYPE_ANSWER:
                        $val['item_info'] = $this->model('answer')->get_answer_by_id($val['item_id']);
                        $val['item_type'] = '回答';
                        $val['item_url']   = get_js_url('/question/' . $val['item_info']['question_id']);
                        $val['item_title'] = cjk_substr($val['item_info']['answer_content'], 0, 30, 'UTF-8', '...');
                        break;
                    case self::TYPE_QUESTION:
                        $val['item_type'] = '问题';
                        $val['item_info'] = $this->model('question')->get_question_info_by_id($val['item_id']);
                        $val['item_url']   = get_js_url('/question/' . $val['item_info']['question_id']);
                        $val['item_title'] = $val['item_info']['question_content'];
                        break;
                    default:
                        break;
                }
                $flow[$key] = $val;
                $uids[] = $val['uid'];
                $uids[] = $val['item_uid'];
            }
            $user_infos = $this->model('account')->get_user_info_by_uids($uids);
            foreach ($flow as $key => $val){
                $flow[$key]['user_info'] = $user_infos[$val['uid']];
                $flow[$key]['item_user_info'] = $user_infos[$val['item_uid']];
            }
        }

        return $flow;
    }

    //crond 自动提现给用户
    public function auto_withdraw(){
        $withdraw = $this->fetch_all('withdraw', "status = " . self::STATUS_WAIT);
        if($withdraw){
            $time = time();
            foreach ($withdraw as $key => $item){
                $return = false;
                if($item['last_time']){
                    switch ($item['times']){
                        case 1:
                            if($item['last_time'] + 1800 < $time){
                                $return = $this->withdraw($item['uid'], $item['id']);
                            }
                            break;
                        case 2:
                            if($item['last_time'] + 7200 < $time){
                                $return = $this->withdraw($item['uid'], $item['id']);
                            }
                            break;
                        case 3:
                            if($item['last_time'] + 43200 < $time){
                                $return = $this->withdraw($item['uid'], $item['id']);
                            }
                            break;
                        default:
                            if($item['last_time'] + 86400 < $time){
                                $return = $this->withdraw($item['uid'], $item['id']);
                            }
                            break;
                    }
                }else{
                    $return = $this->withdraw($item['uid'], $item['id']);
                }
                if($return == -1){
                    //出错提示
                }
            }
        }
    }

    public function withdraw($uid = null, $withdraw_id = null){
        if(!$uid OR !$withdraw_id){
            return false;
        }

        $withdraw = $this->fetch_row('withdraw', 'id = ' . intval($withdraw_id));
        if(!$withdraw OR $withdraw['uid'] != $uid OR $withdraw['status'] != '0'){
            return false;
        }
        $weixin_user = $this->fetch_row('users_weixin', 'uid = ' . intval($withdraw['uid']));
        if(!$weixin_user){
            return false;
        }

        $input = new WxPayToUser();
        $input->SetAmount($withdraw['real_money']);
        $input->Setdesc('支付UID-' . $withdraw['uid'] . '提现');
        $input->SetOpenid($weixin_user['openid']);
        $input->SetPartner_trade_no($withdraw['order_id']);

        $result = WxPayApi::toUser($input);
        if($result['result_code'] != "SUCCESS"){
            //出错,也许是钱不够,后面会再次执行。
            $this->update('withdraw', [
                'times' => $withdraw['times'] +1,
                'last_time' => time()
            ], 'id = ' . intval($withdraw['id']));
            return -1;
        }

        $this->update('withdraw', [
            'status' => self::STATUS_OK,
            'pay_time' => strtotime($result['payment_time']),
            'terrace_id' => $result['payment_no']
        ], 'id = ' . intval($withdraw['id']));

        return 1;
    }
}