<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

// JSON 응답 전용
header('Content-Type: application/json; charset=utf-8');

// 에러 출력 억제
@ini_set('display_errors', '0');
error_reporting(0);

if (!$is_member) {
    echo json_encode(array('success'=>false,'error'=>'login required')); exit;
}

$bo_table    = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i','',$_POST['bo_table']) : '';
$src_wr_id   = isset($_POST['src_wr_id']) ? intval($_POST['src_wr_id']) : 0;
$target_date = isset($_POST['target_date']) ? preg_replace('/[^0-9\-]/','',$_POST['target_date']) : '';

if (!$bo_table) {
    echo json_encode(array('success'=>false,'error'=>'bo_table required')); exit;
}
if (!$src_wr_id) {
    echo json_encode(array('success'=>false,'error'=>'src_wr_id required')); exit;
}
if (!$target_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    echo json_encode(array('success'=>false,'error'=>'invalid target_date')); exit;
}
if (strtotime($target_date) === false) {
    echo json_encode(array('success'=>false,'error'=>'target_date parse failed')); exit;
}

$write_table = $g5['write_prefix'].$bo_table;

// 테이블 존재 확인
$chk = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
if (!$chk || !sql_fetch_array($chk)) {
    echo json_encode(array('success'=>false,'error'=>'board table not found')); exit;
}

$src = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id='".intval($src_wr_id)."' AND wr_is_comment=0");
if (!$src || !$src['wr_id']) {
    echo json_encode(array('success'=>false,'error'=>'source event not found')); exit;
}

$src_start = $src['wr_1'] ? $src['wr_1'] : substr($src['wr_datetime'],0,10);
$src_end   = $src['wr_2'] ? $src['wr_2'] : $src_start;
$diff_days = intval((strtotime($src_end)-strtotime($src_start))/86400);
if ($diff_days < 0) $diff_days = 0;

$new_start = $target_date;
$new_end   = $diff_days > 0 ? date('Y-m-d', strtotime($new_start.' +'.$diff_days.' days')) : $new_start;

sql_query("INSERT INTO {$write_table}
    SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
        wr_reply='', wr_comment=0, wr_comment_reply='',
        ca_name='".sql_real_escape_string($src['ca_name'])."',
        wr_option='',
        wr_subject='".sql_real_escape_string($src['wr_subject'])."',
        wr_content='".sql_real_escape_string($src['wr_content'])."',
        wr_link1='', wr_link2='',
        wr_hit=0, wr_good=0, wr_nogood=0,
        mb_id='".sql_real_escape_string($member['mb_id'])."',
        wr_name='".sql_real_escape_string($member['mb_nick'])."',
        wr_password='', wr_email='', wr_homepage='',
        wr_datetime=NOW(), wr_last=NOW(),
        wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
        wr_1='".sql_real_escape_string($new_start)."',
        wr_2='".sql_real_escape_string($new_end)."',
        wr_3='".sql_real_escape_string($src['wr_3'])."',
        wr_4='', wr_5='local',
        wr_6='".sql_real_escape_string($src['wr_6'])."',
        wr_7='".sql_real_escape_string($src['wr_7'])."',
        wr_8='".sql_real_escape_string($src['wr_8'])."',
        wr_9='',
        wr_is_comment=0");

$new_id = sql_insert_id();
if (!$new_id) {
    echo json_encode(array('success'=>false,'error'=>'insert failed')); exit;
}

sql_query("UPDATE {$write_table} SET wr_parent='{$new_id}' WHERE wr_id='{$new_id}'");

// 글 수 갱신
sql_query("UPDATE {$g5['board_table']}
           SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
           WHERE bo_table='".sql_real_escape_string($bo_table)."'");

echo json_encode(array('success'=>true,'new_wr_id'=>$new_id,'copied_to'=>$new_start,'new_end'=>$new_end));