
1、修改了discuz源程序中的 3 个文件：
	1) search.php	添加了两部分，
		对主题和全文搜索的处理使用sphinx
		//---------------------------------------------------------------------
			if ($srchtype == 'title' || $srchtype == 'fulltext') {	//全文搜索

				if (!empty($_POST['srchtype'])) {
					unset($_POST['formhash']);
					$sp_url = 'search.php?'.http_build_query($_POST);
					dheader("location: {$sp_url}");
				}
				require DISCUZ_ROOT.'./include/search_sphinx.inc.php';
				exit();

			} else
		//---------------------------------------------------------------------
		
		如果http_build_query不存在，构造一个简单的http_build_query函数
		//---------------------------------------------------------------------
		if (!function_exists('http_build_query')) {
			function http_build_query($data) {
				$param = array();
				foreach ((array)$data as $k => $v) {
					if (is_array($v)) {
						$param[] = http_build_query($v);
					} else {
						$param[] = $k.'='.urlencode($v);
					}
				}
				return join('&', $ret);
			}
		}
		//---------------------------------------------------------------------


	2) templates/default/search.htm
		添加一处，将默认的排序方式改为相关度
			<!-- for fulltext search -->
			<option value="rank" selected="selected">{lang order_rank}</option>
			<!-- /for fulltext search -->


	3) templates/default/templates.lang.php
		添加一处，相关度的中文
			'order_rank' => '相关度',

2、添加 4 个文件
	1) gotopost.php
		根据pid跳转到相应的帖子页面
	2) include/search_sphinx.inc.php
		全文搜索的处理程序
	3) include/sphinxapi.php
		sphinx的api
	4) templates/default/search_sphinx.htm
		全文搜索的页面
