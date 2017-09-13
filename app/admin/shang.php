<?php

/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/5
 * Time: 下午8:28
 */

if (! defined('IN_ANWSION'))
{
    die();
}
class shang extends AWS_ADMIN_CONTROLLER
{
    public function setup()
    {
        TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(1111));
    }

    public function index_action(){
        if(is_numeric($_GET['id'])){
            if($order_info = $this->model('shang')->fetch_row('flow', 'id = ' . intval($_GET['id']))){

                switch($order_info['item_type']){
                    case shang_class::TYPE_ARTICLE:
                        if($item_info = $this->model('article')->get_article_info_by_id($order_info['item_id'])){
                            $order_info['item_url'] = get_js_url('/article/' . $item_info['id']);
                            $order_info['item_title'] = $item_info['title'];
                        }
                        $order_info['item_type']  = '文章';
                        break;
                    case shang_class::TYPE_ANSWER:
                        if($item_info = $this->model('answer')->get_answer_by_id($order_info['item_id'])) {
                            $order_info['item_url'] = get_js_url('/question/' . $item_info['question_id']);
                            $order_info['item_title'] = cjk_substr($item_info['answer_content'], 0, 30, 'UTF-8', '...');
                        }
                        $item_info['id'] = $item_info['answer_id'];
                        $order_info['item_type']  = '问题回复';
                        break;

                    default:
                        break;
                }

                $order_info['user_info'] = $this->model('account')->get_user_info_by_uid($order_info['uid']);
                $order_info['item_user_info'] = $this->model('account')->get_user_info_by_uid($order_info['item_uid']);
                $order_info['shang_info'] = $this->model('shang')->fetch_row('shang', 'order_id = \'' . $order_info['order_id'] . '\'');
                TPL::assign('order_info',$order_info);
                TPL::output('admin/shang/info');
            }
        }
    }

    /*打赏*/
    public function list_action()
    {
        if ($_POST['action'] == 'search'){
            foreach ($_POST as $key => $val){
                if (in_array($key, ['user_name']))
                {
                    $val = rawurlencode($val);
                }
                $param[] = $key . '-' . $val;
            }

            H::ajax_json_output(AWS_APP::RSM([
                'url' => get_js_url('/admin/shang/list/' . implode('__', $param))
            ], 1, null));
        }

        $where = [];

        if($_GET['start_date']){
            $where[] = 'add_time >= ' . strtotime($_GET['start_date']);
        }
        if($_GET['end_date']){
            $where[] = 'add_time <= ' . (strtotime($_GET['end_date'])+86400);
        }

        if ($_GET['uid'])
        {
            $where[] = 'item_uid = ' . intval($_GET['uid']);
        }elseif ($_GET['user_name'])
        {
            $users = $this->model('account')->fetch_all('users', 'user_name like \'%' .
                $this->model('account')->quote($_GET['user_name']) . '%\'');
            if($users){
                foreach($users as $val){
                    $uids[] = $val['uid'];
                }
                $where[] = 'item_uid IN(' . implode(',', $uids) . ')';
            }else{
                //not found
                $where[] = 'item_uid = -1';
            }
        }
        $list = $this->model('shang')->fetch_page('flow', implode(' AND ', $where), 'add_time DESC', $_GET['page'],$this->per_page);
        if($list){
            foreach($list as $key => $val){
                $user_ids[$val['uid']] = $val['uid'];
                $user_ids[$val['item_uid']] = $val['item_uid'];
                switch($val['item_type']){
                    case shang_class::TYPE_ARTICLE:
                        if($item_info = $this->model('article')->get_article_info_by_id($val['item_id'])){
                            $val['item_url']   = get_js_url('/article/' . $item_info['id']);
                            $val['item_title'] = $item_info['title'];
                        }
                        $val['item_type']  = '文章';
                        break;

                    case shang_class::TYPE_ANSWER:
                        if($item_info = $this->model('answer')->get_answer_by_id($val['item_id'])) {
                            $val['item_url']   = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = cjk_substr(strip_tags(htmlspecialchars_decode($item_info['answer_content'])), 0, 30, 'UTF-8', '...');
                        }
                        $item_info['id']   = $item_info['answer_id'];
                        $val['item_type']  = '问题回复';
                        break;

                    default:
                        break;
                }
                $list[$key] = $val;
            }

            $user_infos = $this->model('account')->get_user_info_by_uids($user_ids);
            foreach($list as $key => $val){
                $list[$key]['user_info'] = $user_infos[$val['uid']];
                $list[$key]['item_user_info'] = $user_infos[$val['item_uid']];
            }
        }

        TPL::assign('list', $list);
        TPL::assign('total_rows', $total_rows = $this->model('shang')->found_rows());

        $url_param = array();

        foreach($_GET as $key => $val)
        {
            if (!in_array($key, array('app', 'c', 'act', 'page')))
            {
                $url_param[] = $key . '-' . $val;
            }
        }

        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/admin/shang/list/') . implode('__', $url_param),
            'total_rows' => $total_rows,
            'per_page' => $this->per_page
        ))->create_links());

        TPL::output('admin/shang/list');
    }

    public function withdraw_list_action(){
        if(isset($_GET['status'])){
            $where = "status = '".intval($_GET['status'])."'";
        }
        $withdraw_list = $this->model('shang')->fetch_page('withdraw', $where, "id desc", $_GET['page'], 10);
        $totals = $this->model('shang')->found_rows();
        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/admin/shang/withdraw/status-' . $_GET['status']),
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

        TPL::output('admin/shang/withdraw_list');
    }

    public function withdraw_apply_action(){
        $withdraw_info = $this->model('shang')->get_withdraw_info($_GET['id']);

        TPL::assign('withdraw_info', $withdraw_info);

        TPL::output('admin/shang/withdraw_apply');
    }

    public function config_action(){
        TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(1112));

        TPL::assign('setting', get_setting());
        TPL::output('admin/shang/config');
    }

    public function payment_action()
    {
        if(is_digits($_GET['id'])){

            $shang = $this->model('shang')->fetch_row('shang', 'id = ' . intval($_GET['id']));

            $shang['user_info'] = $this->model('account')->get_user_info_by_uid($shang['uid']);
            switch ($shang['item_type']){
                case shang_class::TYPE_ARTICLE:
                    $shang['item_type']  = '文章';
                    $shang['item_info']  = $this->model('article')->get_article_info_by_id($shang['item_id']);
                    $shang['item_url']   = get_js_url('/article/' . $shang['item_info']['id']);
                    $shang['item_title'] = $shang['item_info']['title'];
                    break;
                case shang_class::TYPE_ANSWER:
                    $shang['item_info'] = $this->model('answer')->get_answer_by_id($shang['item_id']);
                    $shang['item_type'] = '回答';
                    $shang['item_url']   = get_js_url('/question/' . $shang['item_info']['question_id']);
                    $shang['item_title'] = cjk_substr($shang['item_info']['answer_content'], 0, 30, 'UTF-8', '...');
                    break;
                case shang_class::TYPE_QUESTION:
                    $shang['item_type'] = '问题';
                    $shang['item_info'] = $this->model('question')->get_question_info_by_id($shang['item_id']);
                    $shang['item_url']   = get_js_url('/question/' . $shang['item_info']['question_id']);
                    $shang['item_title'] = $shang['item_info']['question_content'];
                    break;
                default:
                    break;
            }
            TPL::assign('shang', $shang);
            TPL::output('admin/shang/payment_info');
        }
        if ($_POST['action'] == 'search'){
            foreach ($_POST as $key => $val){
                if (in_array($key, ['user_name']))
                {
                    $val = rawurlencode($val);
                }
                $param[] = $key . '-' . $val;
            }

            H::ajax_json_output(AWS_APP::RSM([
                'url' => get_js_url('/admin/shang/payment/' . implode('__', $param))
            ], 1, null));
        }

        $where[] = "has_pay = " . shang_class::HAS_PAY;

        if($_GET['terrace_id']){
            $where[] = 'terrace_id = \'' . $this->model('account')->quote($_GET['terrace_id']) . '\'';
        }

        if ($_GET['uid'])
        {
            $where[] = 'uid = ' . intval($_GET['uid']);
        }elseif ($_GET['user_name'])
        {
            $users = $this->model('account')->fetch_all('users', 'user_name like \'%' .
                $this->model('account')->quote($_GET['user_name']) . '%\'');
            if($users){
                foreach($users as $val){
                    $uids[] = $val['uid'];
                }
                $where[] = 'uid IN(' . implode(',', $uids) . ')';
            }else{
                //not found
                $where[] = 'uid = -1';
            }
        }

        if($_GET['start_date']){
            $where[] = 'pay_time >= ' . strtotime($_GET['start_date']);
        }
        if($_GET['end_date']){
            $where[] = 'pay_time <= ' . (strtotime($_GET['end_date'])+86400);
        }

        $list = $this->model('shang')->fetch_page('shang', implode(' AND ', $where), 'pay_time DESC', $_GET['page'],$this->per_page);
        if($list){
            foreach($list as $key => $val){
                $user_ids[$val['uid']] = $val['uid'];
                switch($val['item_type']){
                    case shang_class::TYPE_ARTICLE:
                        if($item_info = $this->model('article')->get_article_info_by_id($val['item_id'])){
                            $val['item_url']   = get_js_url('/article/' . $item_info['id']);
                            $val['item_title'] = $item_info['title'];
                        }
                        $val['item_type']  = '文章';
                        break;

                    case shang_class::TYPE_ANSWER:
                        if($item_info = $this->model('answer')->get_answer_by_id($val['item_id'])) {
                            $val['item_url']   = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = cjk_substr(strip_tags(htmlspecialchars_decode($item_info['answer_content'])), 0, 30, 'UTF-8', '...');
                        }
                        $item_info['id']   = $item_info['answer_id'];
                        $val['item_type']  = '回答';
                        break;
                    case shang_class::TYPE_QUESTION:
                        if($item_info = $this->model('question')->get_question_info_by_id($val['item_id'])) {
                            $val['item_url']   = get_js_url('/question/' . $item_info['question_id']);
                            $val['item_title'] = $item_info['question_content'];
                        }
                        $item_info['id']   = $item_info['question_id'];
                        $val['item_type']  = '问题';
                        break;
                    default:
                        break;
                }
                $list[$key] = $val;
            }

            $user_infos = $this->model('account')->get_user_info_by_uids($user_ids);
            foreach($list as $key => $val){
                $list[$key]['user_info'] = $user_infos[$val['uid']];
            }
        }

        TPL::assign('list', $list);
        TPL::assign('total_rows', $total_rows = $this->model('shang')->found_rows());

        $url_param = array();

        foreach($_GET as $key => $val)
        {
            if (!in_array($key, array('app', 'c', 'act', 'page')))
            {
                $url_param[] = $key . '-' . $val;
            }
        }

        TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
            'base_url' => get_js_url('/admin/shang/payment/') . implode('__', $url_param),
            'total_rows' => $total_rows,
            'per_page' => $this->per_page
        ))->create_links());

        TPL::output('admin/shang/payment');
    }

    public function total_action(){
        if ($_POST['action'] == 'search'){
            foreach ($_POST as $key => $val){
                if (in_array($key, ['user_name']))
                {
                    $val = rawurlencode($val);
                }
                $param[] = $key . '-' . $val;
            }

            H::ajax_json_output(AWS_APP::RSM([
                'url' => get_js_url('/admin/shang/total/' . implode('__', $param))
            ], 1, null));
        }

        if($_GET['start_date']){
            $where[] = 'pay_time >= ' . strtotime($_GET['start_date']);
        }
        if($_GET['end_date']){
            $where[] = 'pay_time <= ' . (strtotime($_GET['end_date'])+86400);
        }

        $where = implode(' AND ', $where);
        $where1 = $where3 = 'has_pay = ' . shang_class::HAS_PAY . ' AND (pay_way = ' . shang_class::PAY_ALIPAY . ' OR pay_way = ' . shang_class::PAY_WEIXIN . ')';
        $where2 = $where4 = 'status = ' . shang_class::STATUS_OK;
        if($where){
            $where3 = $where . ' AND ' . $where1;
            $where4 = $where . ' AND ' . $where2;
        }

        TPL::assign('last_send', $this->model('shang')->sum('shang', 'money', $where3));
        TPL::assign('last_withdraw', $this->model('shang')->sum('withdraw', 'money', $where4));

        $total_send = $this->model('shang')->sum('shang', 'money', $where1);

        TPL::assign('total_send', $total_send);

        $total_withdraw = $this->model('shang')->sum('withdraw', 'money', $where2);
        TPL::assign('total_withdraw', $total_withdraw);

        TPL::assign('total_balance', $this->model('shang')->sum('users', 'balance'));
        TPL::assign('total_withdraw_apply', $this->model('shang')->sum('withdraw', 'money', 'status = ' . shang_class::STATUS_WAIT));

        TPL::output('admin/shang/total');
    }

    public function ajax_withdraw_apply_action(){
        if(!$_POST['status']){
            H::ajax_json_output(AWS_APP::RSM(null, -1, '参数错误'));
        }
        switch ($_POST['status']) {
            case 'ok':
                if(!$_POST['terrace_id']){
                    H::ajax_json_output(AWS_APP::RSM(null, -1, '支付订单ID为必填项'));
                }
                $this->model('shang')->withdraw_ok($_POST['id'], $_POST['terrace_id'], $_POST['info']);

                break;

            case 'close':
                $this->model('shang')->withdraw_close($_POST['id']);

                break;

            default:
                # code...
                break;
        }
        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }
}