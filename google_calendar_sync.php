<?php
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_config.php');

function gcal_token_row($mb_id) {
    global $g5;
    $t = $g5['prefix'].'calendar_google_token';
    return sql_fetch("SELECT * FROM {$t} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
}

function gcal_refresh_access_token($mb_id) {
    global $g5;
    $row = gcal_token_row($mb_id);
    if (!$row || !$row['refresh_token']) return false;

    $post = http_build_query(array(
      'client_id'=>GCAL_CLIENT_ID,
      'client_secret'=>GCAL_CLIENT_SECRET,
      'refresh_token'=>$row['refresh_token'],
      'grant_type'=>'refresh_token'
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

    if ($http != 200 || !$res) return false;
    $tok = json_decode($res, true);
    if (empty($tok['access_token'])) return false;

    $t = $g5['prefix'].'calendar_google_token';
    $exp = date('Y-m-d H:i:s', time()+intval($tok['expires_in'])-30);
    sql_query("UPDATE {$t} SET access_token='".sql_real_escape_string($tok['access_token'])."', expires_at='{$exp}', updated_at=NOW() WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    return gcal_token_row($mb_id);
}

function gcal_get_valid_access_token($mb_id) {
    $row = gcal_token_row($mb_id);
    if (!$row) return '';
    if (strtotime($row['expires_at']) <= time()) {
      $row = gcal_refresh_access_token($mb_id);
      if (!$row) return '';
    }
    return $row['access_token'];
}

function gcal_api_request($method, $url, $access_token, $body = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $headers = array('Authorization: Bearer '.$access_token, 'Accept: application/json');
    if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $headers[] = 'Content-Type: application/json';
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return array('http'=>$http,'body'=>$res,'error'=>$err);
}