<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');

if (!$is_member) alert('로그인이 필요합니다.');
gcal_ensure_tables();

$state = isset($_GET['state']) ? $_GET['state'] : '';
$code = isset($_GET['code']) ? $_GET['code'] : '';
$save = get_session('gcal_state');
$bo_table = get_session('gcal_bo_table');

if (!$code || !$state || $state !== $save) alert('OAuth 인증 실패', G5_BBS_URL.'/board.php?bo_table='.$bo_table);

$post = http_build_query(array(
  'code'=>$code,
  'client_id'=>GCAL_CLIENT_ID,
  'client_secret'=>GCAL_CLIENT_SECRET,
  'redirect_uri'=>GCAL_REDIRECT_URI,
  'grant_type'=>'authorization_code'
));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http != 200 || !$res) alert('토큰 발급 실패', G5_BBS_URL.'/board.php?bo_table='.$bo_table);

$tok = json_decode($res, true);
if (empty($tok['access_token'])) alert('토큰 파싱 실패', G5_BBS_URL.'/board.php?bo_table='.$bo_table);

$t = $g5['prefix'].'calendar_google_token';
$mb_id = sql_real_escape_string($member['mb_id']);
$access = sql_real_escape_string($tok['access_token']);
$refresh = isset($tok['refresh_token']) ? sql_real_escape_string($tok['refresh_token']) : '';
$exp = date('Y-m-d H:i:s', time()+intval($tok['expires_in'])-30);

$row = sql_fetch("SELECT id FROM {$t} WHERE mb_id='{$mb_id}'");
if ($row && $row['id']) {
    $set_refresh = $refresh ? ", refresh_token='{$refresh}'" : "";
    sql_query("UPDATE {$t} SET access_token='{$access}', expires_at='{$exp}', updated_at=NOW() {$set_refresh} WHERE mb_id='{$mb_id}'");
} else {
    sql_query("INSERT INTO {$t} SET mb_id='{$mb_id}', access_token='{$access}', refresh_token='{$refresh}', expires_at='{$exp}', updated_at=NOW()");
}

goto_url(G5_BBS_URL.'/board.php?bo_table='.$bo_table);