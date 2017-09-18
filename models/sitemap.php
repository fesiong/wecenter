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

class sitemap_class extends AWS_MODEL
{
	public $sitemap_limit = 50000;
	public $question_counter;
	public $article_counter;
	public $topic_counter;
	/**
	 * 主动推送，百度
	 */
	public function put_spider($link){
		$baidu_token = get_setting('baidu_token');
		if($baidu_token){
			$url = "http://data.zz.baidu.com/urls?site={$_SERVER['HTTP_HOST']}&token={$baidu_token}";
			$result = HTTP::request($url, 'POST', $link, 15);
			file_put_contents(ROOT_PATH . 'cache/put_spider.log', $link . '->' . $result . "\n");
		}
	}

	public function make_sitemap(){
		$this->question_counter = $this->count('question');
		$this->article_counter  = $this->count('article');
		$this->topic_counter    = $this->count('topic');

		$this->make_index();

		$this->make_question();
		$this->make_article();
		$this->make_topic();
	}

	public function make_index(){
		$index_file = ROOT_PATH . sitemap_generator::get_sitemap_url('index');
		$index_sitemap = new sitemap_generator($index_file, 'index');

		for($i = 1; $i <= ceil($this->question_counter/$this->sitemap_limit); $i++){
			$index_sitemap->add_sitemap('question', $i);
		}

		for($i = 1; $i <= ceil($this->article_counter/$this->sitemap_limit); $i++){
			$index_sitemap->add_sitemap('article', $i);
		}

		for($i = 1; $i <= ceil($this->topic_counter/$this->sitemap_limit); $i++){
			$index_sitemap->add_sitemap('topic', $i);
		}

		return $index_sitemap->save();
	}

	public function make_question(){
		if($this->question_counter > $this->sitemap_limit){
			for($i = 1; $i <= ceil($this->question_counter/$this->sitemap_limit); $i++){
				$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('question', $i);
				$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
				$datas = $this->query_all("select question_id,add_time from " . get_table('question') . " order by add_time desc", $this->sitemap_limit, ($i-1)*$this->sitemap_limit);
				foreach ($datas as $key => $item) {
					$sitemap->add_url(get_js_url('/question/' . $item['question_id']), date('Y-m-d H:i', $item['add_time']));
				}

				$sitemap->save();
			}
		}else{
			$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('question');
			$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
			$datas = $this->query_all("select question_id,add_time from " . get_table('question') . " order by add_time desc");

			foreach ($datas as $key => $item) {
				$sitemap->add_url(get_js_url('/question/' . $item['question_id']), date('Y-m-d H:i', $item['add_time']));
			}

			$sitemap->save();
		}
	}

	public function make_article(){
		if($this->article_counter > $this->sitemap_limit){
			for($i = 1; $i <= ceil($this->article_counter/$this->sitemap_limit); $i++){
				$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('article', $i);
				$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
				$datas = $this->query_all("select id,add_time from " . get_table('article') . " order by add_time desc", $this->sitemap_limit, ($i-1)*$this->sitemap_limit);
				foreach ($datas as $key => $item) {
					$sitemap->add_url(get_js_url('/article/' . $item['id']), date('Y-m-d H:i', $item['add_time']));
				}

				$sitemap->save();
			}
		}else{
			$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('article');
			$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
			$datas = $this->query_all("select id,add_time from " . get_table('article') . " order by add_time desc");

			foreach ($datas as $key => $item) {
				$sitemap->add_url(get_js_url('/article/' . $item['id']), date('Y-m-d H:i', $item['add_time']));
			}

			$sitemap->save();
		}
	}

	public function make_topic(){
		if($this->topic_counter > $this->sitemap_limit){
			for($i = 1; $i <= ceil($this->topic_counter/$this->sitemap_limit); $i++){
				$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('topic', $i);
				$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
				$datas = $this->query_all("select topic_id,topic_title,url_token,add_time from " . get_table('topic') . " order by add_time desc", $this->sitemap_limit, ($i-1)*$this->sitemap_limit);
				foreach ($datas as $key => $item) {
					if(!$item['url_token']){
						$item['url_token'] = $item['topic_title'];
					}
					$sitemap->add_url(get_js_url('/topic/' . $item['topic_id']), date('Y-m-d H:i', $item['add_time']));
				}

				$sitemap->save();
			}
		}else{
			$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url('topic');
			$sitemap = new sitemap_generator($sitemap_file, 'sitemap');
			$datas = $this->query_all("select topic_id,topic_title,url_token,add_time from " . get_table('topic') . " order by add_time desc");

			foreach ($datas as $key => $item) {
				if(!$item['url_token']){
					$item['url_token'] = $item['topic_title'];
				}
				$sitemap->add_url(get_js_url('/topic/' . $item['topic_id']), date('Y-m-d H:i', $item['add_time']));
			}

			$sitemap->save();
		}
	}

	public function add_to_sitemap($type, $link, $lastmod = 0){
        if(!$lastmod){
            $lastmod = time();
        }
		switch ($type) {
			case 'question':
				$counter = $this->count('question');
				break;
			case 'article':
				$counter = $this->count('article');
				break;
			case 'topic':
				$counter = $this->count('topic');
				break;
		}

		$sitemap_file = ROOT_PATH . sitemap_generator::get_sitemap_url($type, ceil($counter/$this->sitemap_limit));
		$sitemap = new sitemap_generator($sitemap_file, 'sitemap', true);
		$sitemap->add_url($link, date('Y-m-d H:i', $lastmod));
		$sitemap->save();
	}
}

class sitemap_generator
{
	public $sitemap_file;
	public $sitemap;
	public $error;

	public function __construct($sitemap_file, $type = 'sitemap', $load_file = false){
		$this->sitemap_file = $sitemap_file;
		if(is_file($this->sitemap_file) AND $load_file){
			$this->sitemap = new SimpleXMLElement(file_get_contents($this->sitemap_file));
		}else{
			$this->sitemap = $this->createEmptySitemap($type);
		}
	}

	public function createEmptySitemap($type = 'sitemap') {
        $str = '<?xml version="1.0" encoding="UTF-8"?>';
        if($type == 'index'){
			$str .= '<sitemapindex></sitemapindex>';
        }else{
        	$str .= '<urlset></urlset>';
        }
        
        return new SimpleXMLElement($str);
    }

	public function save(){
		$data = $this->sitemap->asXML();
        if(file_put_contents($this->sitemap_file, $data) === false) {
            $this->error = '写入站点地图数据失败';
            return false;
        }
        return true;
	}

	public function add_sitemap($type, $page = 1, $lastmod = 0){
		$url = static::get_sitemap_url($type, $page, true);

		$rating = $this->sitemap->addChild('sitemap');
		$rating->addChild('loc',$url);
        if($lastmod){
            $rating->addChild('lastmod',$lastmod);
        }
	}

	public function add_url($loc, $lastmod = 0, $changefreq = "daily", $priority = 0.8){
		$rating = $this->sitemap->addChild('url');
		$rating->addChild('loc',$loc);
        $rating->addChild('priority',$priority);
        if($lastmod){
            $rating->addChild('lastmod',$lastmod);
        }
        $rating->addChild('changefreq',$changefreq);
	}

	public static function get_sitemap_url($type, $page = 1, $path = false){
        switch ($type){
            case 'question':
				$url = 'sitemap-question';
				break;
			case 'article':
				$url = 'sitemap-article';
				break;
			case 'topic':
				$url = 'sitemap-topic';
				break;
			case 'index':
				$url = 'sitemap';
				break;
        }

        if($page > 1){
			$url .= '-' . $page;
		}

		$url .= '.xml';

		if($path){
			return base_url() . '/' . $url;
		}else{
			return $url;
		}
    }
}
