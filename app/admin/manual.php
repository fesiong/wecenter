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

if (! defined('IN_ANWSION'))
{
	die();
}

class manual extends AWS_ADMIN_CONTROLLER
{
	public function setup()
	{
		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(321));
	}

	public function list_action()
	{

		if ($manual_list = $this->model('manual')->fetch_page('manual', null, 'id DESC', $_GET['page'], $this->per_page))
		{
            $search_manual_total = $this->model('manual')->found_rows();
		}

		if ($manual_list)
		{
			foreach ($manual_list AS $key => $val)
			{
				$category_ids[$val['category_id']] = $val['category_id'];
			}

			if ($category_ids)
			{
                $categories = $this->model('category')->fetch_all('category', 'id IN(' . implode(',', $category_ids) . ')');
                $category_list = array();
                foreach ($categories as $val){
                    $category_list[$val['id']] = $val;
                }
                unset($categories);
			}

			foreach ($manual_list AS $key => $val)
			{
                $manual_list[$key]['category_info'] = $category_list[$val['category_id']];
			}
		}

		$url_param = array();

		foreach($_GET as $key => $val)
		{
			if (!in_array($key, array('app', 'c', 'act', 'page')))
			{
				$url_param[] = $key . '-' . $val;
			}
		}

		TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
			'base_url' => get_js_url('/admin/manual/list/') . implode('__', $url_param),
			'total_rows' => $search_manual_total,
			'per_page' => $this->per_page
		))->create_links());

		$this->crumb(AWS_APP::lang()->_t('开发文档管理'), 'admin/manual/list/');

		TPL::assign('list', $manual_list);

		TPL::output('admin/manual/list');
	}

	public function publish_action(){
        if($_GET['id']){
            $manual_info = $this->model('manual')->get_manual_info_by_id($_GET['id']);
        }
        TPL::assign('manual_info', $manual_info);

        $categories = $this->model('system')->build_category_html('manual', 0, $manual_info['category_id']);
        TPL::assign('category_list', $categories);

        TPL::assign('attach_access_key', md5($this->user_id . time()));

        TPL::import_js('js/app/publish.js');

        import_editor_static_files();
        TPL::import_js('js/fileupload.js');

        TPL::output('admin/manual/publish');
    }

    public function ajax_save_manual_action(){
        HTTP::no_cache_header();

        if (!$_POST['title'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入标题')));
        }

        if (!$_POST['category_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择分类')));
        }

        $result = $this->model('manual')->save_manual([
            'title'       => htmlspecialchars($_POST['title']),
            'category_id' => $_POST['category_id'],
            'keywords'    => $_POST['keywords'],
            'description' => $_POST['description'],
            'message'     => htmlspecialchars($_POST['message'])
        ], $_POST['id']);

        if(!$result){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('保存的时候出错,请刷新重试')));
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'url' => get_js_url('/admin/manual/list/')
        ), 1, null));
    }
}