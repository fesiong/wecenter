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

class manual_class extends AWS_MODEL
{
	public function get_manual_info_by_id($id)
	{
		if (!is_digits($id))
		{
			return false;
		}

		static $manuals;

		if (!$manuals[$id])
		{
            $manuals[$id] = $this->fetch_row('manual', 'id = ' . $id);
		}

		return $manuals[$id];
	}

	public function remove_manual($id)
	{
		if (!$manual_info = $this->get_manual_info_by_id($id))
		{
			return false;
		}

		return $this->delete('manual', 'id = ' . intval($id));
	}

	public function save_manual($data, $id = null)
	{
		if($id AND !$manual_info = $this->get_manual_info_by_id($id)){
		    return false;
        }

        if($id) {
            $this->update('manual', $data, 'id = ' . intval($id));
        } else
        {
            $data['add_time'] = time();
            $this->insert('manual', $data);
        }

		return true;
	}

	public function get_manual_list($category_id, $page, $per_page, $order_by = 'sort asc')
	{
		$where = array();

		if ($category_id)
		{
			$where[] = 'category_id = ' . intval($category_id);
		}

		return $this->fetch_page('manual', implode(' AND ', $where), $order_by, $page, $per_page);
	}

	public function update_views($id)
	{
		if (AWS_APP::cache()->get('update_views_manual_' . md5(session_id()) . '_' . intval($id)))
		{
			return false;
		}

		AWS_APP::cache()->set('update_views_manual_' . md5(session_id()) . '_' . intval($id), time(), 60);

		$this->shutdown_query("UPDATE " . $this->get_table('manual') . " SET views = views + 1 WHERE id = " . intval($id));

		return true;
	}
}