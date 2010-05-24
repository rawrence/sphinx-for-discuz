<?php
/**
 * @author jayeeliu <jayeeliu@gmail.com>
 * @version $Id$
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./include/common.inc.php';
require_once DISCUZ_ROOT.'./include/forum.func.php';
require_once DISCUZ_ROOT.'./include/misc.func.php';

$sp_keyword = isset($srchtxt) ? trim($srchtxt) : '';
$sp_keyword = urldecode($srchtxt);

//作者
/*
if($srchuname) {
	$srchuid = $comma = '';
	$srchuname = str_replace('*', '%', addcslashes($srchuname, '%_'));
	$query = $db->query("SELECT uid FROM {$tablepre}members WHERE username LIKE '".str_replace('_', '\_', $srchuname)."' LIMIT 50");
	while($member = $db->fetch_array($query)) {
		$srchuid .= "$comma'$member[uid]'";
		$comma = ', ';
	}
	if(!$srchuid) {
		$sqlsrch .= ' AND 0';
	}
} elseif($srchuid) {
	$srchuid = "'$srchuid'";
}
*/
if ($srchuname) {
	$query	= $db->query("SELECT uid FROM {$tablepre}members WHERE username='{$srchuname}'");
	$auth	= $db->fetch_row($query);
	!empty($auth) && $srchuid = $auth[0];
}

if (empty($srchtxt) && empty($srchuname)) {
	showmessage('search_invalid', 'search.php');
}

require DISCUZ_ROOT.'./include/sphinxapi.php';

$sp_host	= '192.168.0.120';
$sp_port	= 3312;

$cl = new SphinxClient();
//设置服务器
$cl->SetServer($sp_host, $sp_port);
//设置搜索模式(处理空格的和关系)
if (preg_match('|\s+|', $key)) {
	$cl->SetMatchMode(SPH_MATCH_ALL);
} else {
	$cl->SetMatchMode(SPH_MATCH_PHRASE);
}

if ($srchtype == 'fulltext') {
	//设置个字段权重
	$weight = array(
		'subject'		=> 70,
		'message'		=> 30,
	);
	$cl->SetFieldWeights($weight);
	$sp_index = 'posts;posts_delta';
	$sp_hightlight_index = 'posts';
} else {
	$weight = array(
		'subject'		=> 100,
	);
	$sp_index	= 'threads;threads_delta';
	$sp_hightlight_index = 'threads';
}
$cl->SetFieldWeights($weight);

//帖子作者
if (!empty($srchuid)) {
	$cl->setfilter('authorid', array($srchuid));
}

if ($srchtype == 'title') {
	//特殊主题:	投票主题   商品主题   悬赏主题   活动主题   辩论主题   视频主题
	$sp_special_filter = array(1, 2, 3, 4, 5, 6);
	if (!empty($special) && is_array($special)) {
		foreach ($special as $spc) {
			in_array($spc, $sp_special_filter) && $sp_special[] = $spc;
		}
		!empty($sp_special) && $cl->setfilter('special', $sp_special);
	}

	/*
	 * 主题范围
	 * digest		= array(0,1,2,3)
	 * displayorder = array(0,1,2,3)
	*/
	$sp_filter = array('digest'=>array(1,2,3), 'top'=>array(1,2,3));
	if (isset($srchfilter) && isset($sp_filter[$srchfilter])) {
		$cl->setfilter($srchfilter, $sp_filter[$srchfilter]);
	}
}

//搜索时间
if (!empty($srchfrom)) {
	$srchfrom		= (int)$srchfrom;
	$sp_time_now	= time();
	$ps_time_select = $sp_time_now - $srchfrom;
	//$before默认是false	所选时间到现在		$before=1时，表示这段时间之外
	$cl->setfilterrange('dateline', $ps_time_select, $sp_time_now, $before);
}

//排序类型
$sp_order	= isset($ascdesc) && $ascdesc == 'asc' ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC;
$sp_orderby	= array('lastpost', 'dateline', 'replies', 'views');//'rank', 
if (in_array($orderby, $sp_orderby)) {
	$cl->SetSortMode($sp_order, $orderby);
} else {
	$cl->SetSortMode(SPH_SORT_EXPR, '@weight');
}

//搜索论坛板块范围
if(!empty($srchfid) && $srchfid != 'all') {
	foreach($srchfid as $forum) {
		if($forum = intval(trim($forum))) {
			$sp_fids[] = $forum;
		}
	}
	!empty($sp_fids) && $cl->setfilter('fid', $sp_fids);
}

//分页
$sp_page	= isset($page) ? abs((int)$page) : 1;
$sp_page	= max(1, $sp_page);
$sp_perpage = 10;
$sp_start	= ($page - 1) * $sp_perpage;
$sp_total_result = 100;
$cl->setlimits($sp_start , $sp_perpage);

$res = $cl->query($sp_keyword, $sp_index );

/*	显示sphinx错误信息，用于调试
if ($cl->GetLastWarning()) {
	var_dump($res);
	die("WARNING: " . $cl->GetLastWarning() . "\n\n");
}
//*/

if (empty($res['matches'])) {
	include template('search_threads');
	exit();
}

//分页
$query_string	= 'search.php?'.preg_replace('|&page=\d+|', '', $_SERVER['QUERY_STRING']);
$multipage		= multi($res['total'], $sp_perpage, $sp_page, $query_string);

//从数据获取信息
$sp_res_keys	= array_keys($res['matches']);
$sp_find_ids	= join(',', $sp_res_keys);
$sp_res_order	= array_flip($sp_res_keys);

//高亮显示匹配词
$sp_build_opts = array(
	'before_match'	=> '<em style="color:red;">',
	'after_match'	=> '</em>'
);

if ($srchtype == 'fulltext') {
	$postlist	= $titles = $contents = array();
	$sql = "SELECT p.pid, p.tid, p.author, p.authorid, p.subject as ptitle, p.message as content, p.fid, f.name as fname,
			t.subject as ttitle, t.lastpost, t.views, t.replies
			FROM {$tablepre}posts p, {$tablepre}threads t, {$tablepre}forums f
			WHERE p.tid=t.tid AND f.fid = p.fid AND p.pid IN ({$sp_find_ids})";

	$query = $db->query($sql);
	while($row = $db->fetch_array($query)) {
		$row['lastpost'] = gmdate($dateformat .' '. $timeformat, $row['lastpost'] + $timeoffset * 3600);
		$key = $sp_res_order[$row['pid']];
		$postlist[$key] = $row;
		if (trim($row['ptitle']) != '') {
			$titles[$key] = $row['ptitle'];
		} else {
			$titles[$key] = 'RE:'.$row['ttitle'];
		}
		$contents[$key] = $row['content'];
	}
	//排序
	ksort($titles);
	ksort($postlist);
	ksort($contents);

	$sp_titles		= $cl->BuildExcerpts($titles, $sp_hightlight_index, $sp_keyword, $sp_build_opts);
	$sp_contents	= $cl->BuildExcerpts($contents, $sp_hightlight_index, $sp_keyword, $sp_build_opts);
	for ($i=0, $l=count($contents); $i<$l; $i++) {
		$postlist[$i]['content']	= $sp_contents[$i];
		$postlist[$i]['title']	= $sp_titles[$i];
	}
	
	include template('search_sphinx');
} else {
	$threadlist = $titles = array ();
	$query = $db->query("SELECT * FROM {$tablepre}threads WHERE tid IN ({$sp_find_ids})");// AND displayorder>='0'
	while($thread = $db->fetch_array($query)) {
		$threadlist[$sp_res_order[$thread['tid']]] = procthread($thread);
		$titles[$sp_res_order[$thread['tid']]] = $thread['subject'];
	}
	
	$sp_titles	= $cl->BuildExcerpts($titles, $sp_hightlight_index, $sp_keyword, $sp_build_opts);
	//排序
	ksort($titles);
	ksort($threadlist);
	for ($i=0, $l=count($titles); $i<$l; $i++) {
		$threadlist[$i]['subject']	= $sp_titles[$i];
	}

	include template('search_threads');
}
