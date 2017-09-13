<?php

/**
 * Created by PhpStorm.
 * User: fesiong
 * Date: 16/9/6
 * Time: 上午11:25
 */
define('IN_AJAX', TRUE);


if (!defined('IN_ANWSION'))
{
    die;
}

class ajax extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'white';

        $rule_action['actions'] = array();

        return $rule_action;
    }

    function setup()
    {
        HTTP::no_cache_header();
    }

    public function withdraw_apply_action()
    {
        if($_POST['amount'] < 2){
            H::ajax_json_output(AWS_APP::RSM(null, -1, '提现金额必须大于等于2元。'));
        }

        if($_POST['amount'] > ($this->user_info['balance'] / 100)){
            H::ajax_json_output(AWS_APP::RSM(null, -1, '提现金额必须小于等于您可提现的金额' . ($this->user_info['balance'] / 100) . '元。'));
        }

        if($this->model('shang')->withdraw_apply($this->user_id, $_POST['amount'] * 100)){
            $url = get_js_url('/shang/withdraw_list/');
            if(is_mobile()){
                $url = get_js_url('/m/shang/withdraw_list/');
            }
            H::ajax_json_output(AWS_APP::RSM(array(
                'url' => $url
            ), 1, null));
        }else{
            H::ajax_json_output(AWS_APP::RSM(null, -1, '申请提现过程中出现了小差错，请稍候重试。'));
        }
    }

    public function remove_account_action(){
        $this->model('account')->update_users_attrib_fields(array(
            'alipay_account' => '',
            'alipay_name'    => '',
        ), $this->user_id);
        H::ajax_json_output(AWS_APP::RSM(null, 1, ''));
    }

    public function save_alipay_account_action(){
        if($_POST['alipay_account']){
            $this->model('account')->update_users_attrib_fields(array(
                'alipay_name'   => htmlspecialchars($_POST['alipay_name']),
                'alipay_account' => htmlspecialchars($_POST['alipay_account'])
            ), $this->user_id);
            H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('保存成功')));
        }
        H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('你好')));
    }
}