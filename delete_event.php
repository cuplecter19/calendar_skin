<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(0);

if (!$is_member) {
    echo json_encode(array('success'=>false,'error'=>'login required')); exit;
}

$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i','',$_POST['bo_table']) : '';
$wr_id    = isset($_POST['wr_id']) ? intval($_POST['wr_id']) : 0;

if (!$bo_table) {
    echo json_encode(array('success'=>false,'error'=>'bo_table required')); exit;
}
if (!$wr_id) {
    echo json_encode(array('success'=>false,'error'=>'wr_id required')); exit;
}

$write_table = $g5['write_prefix'].$bo_table;

// 테이블 존재 확인
$chk = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
if (!$chk || !sql_fetch_array($chk)) {
    echo json_encode(array('success'=>false,'error'=>'board table not found')); exit;
}

// 글 존재 확인
$row = sql_fetch("SELECT wr_id, mb_id, wr_4 FROM {$write_table} WHERE wr_id='".intval($wr_id)."' AND wr_is_comment=0");
if (!$row || !$row['wr_id']) {
    echo json_encode(array('success'=>false,'error'=>'event not found')); exit;
}

// 권한 확인: 본인 글이거나 관리자
if ($row['mb_id'] !== $member['mb_id'] && !$is_admin) {
    echo json_encode(array('success'=>false,'error'=>'permission denied')); exit;
}

// 댓글도 함께 삭제
sql_query("DELETE FROM {$write_table} WHERE wr_parent='".intval($wr_id)."'");

// 본글 삭제 (wr_parent가 자기 자신이 아닌 경우 대비)
sql_query("DELETE FROM {$write_table} WHERE wr_id='".intval($wr_id)."'");

// 구글 매핑 테이블에서도 삭제
$map_table = $g5['prefix'].'calendar_google_map';
$chk2 = sql_query("SHOW TABLES LIKE '{$map_table}'", false);
if ($chk2 && sql_fetch_array($chk2)) {
    sql_query("DELETE FROM {$map_table} WHERE bo_table='".sql_real_escape_string($bo_table)."' AND wr_id='".intval($wr_id)."'");
}

// 글 수 갱신
sql_query("UPDATE {$g5['board_table']}
           SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
           WHERE bo_table='".sql_real_escape_string($bo_table)."'");

echo json_encode(array('success'=>true,'deleted_wr_id'=>$wr_id));