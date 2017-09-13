<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
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

		if ($this->user_info['permission']['visit_site'])
		{
			$rule_action['actions'][] = 'square';
			$rule_action['actions'][] = 'index';
		}

		return $rule_action;
	}

	public function index_action()
	{
		$manual_info = $this->model('manual')->get_manual_info_by_id($_GET['id']);

		$categories = $this->model('system')->fetch_category('manual', 0);

        TPL::assign('category_list', $categories);

        $manual_list = $this->model('manual')->fetch_all('manual', null, 'category_id asc, sort asc');
        TPL::assign('manual_list', $manual_list);

        if(!$manual_info){
        	$manual_info = reset($manual_list);
        }
		
		$manual_info['message'] = FORMAT::parse_attachs(nl2br(FORMAT::parse_bbcode($manual_info['message'])));

		$this->crumb($manual_info['title'], '/manual/' . $manual_info['id']);
		
        TPL::assign('manual_info', $manual_info);

        $this->model('manual')->update_views($manual_info['id']);

		if(!$manual_info['keywords']){
			$manual_info['keywords'] = implode(',', $this->model('system')->analysis_keyword($manual_info['title']));
		}
		TPL::set_meta('keywords', $manual_info['keywords']);

		if(!$manual_info['description']){
			$manual_info['description'] = $manual_info['title'] . ' - ' . cjk_substr(str_replace("\r\n", ' ', strip_tags($manual_info['message'])), 0, 128, 'UTF-8', '...');
		}
		TPL::set_meta('description', $manual_info['description']);

		TPL::output('manual/index');
	}
}
