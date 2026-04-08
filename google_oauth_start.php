<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');

if (!$is_member) {
    alert('로그인이 필요합니다.');
}

gcal_ensure_tables();

$state = md5(uniqid('', true));
set_session('gcal_state', $state);
set_session('gcal_bo_table', isset($_GET['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['bo_table']) : '');

/**
 * scope 변경:
 * - 기존: calendar.events
 * - 변경: calendar (읽기+쓰기+다중 캘린더 접근 안정화)
 */
$scope = urlencode('https://www.googleapis.com/auth/calendar');

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth'
    .'?client_id='.urlencode(GCAL_CLIENT_ID)
    .'&redirect_uri='.urlencode(GCAL_REDIRECT_URI)
    .'&response_type=code'
    .'&access_type=offline'
    .'&prompt=consent'
    .'&scope='.$scope
    .'&state='.$state;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Google OAuth Redirect</title>
</head>
<body>
<script>
(function() {
    var authUrl = <?php echo json_encode($auth_url); ?>;
    try {
        if (window.top !== window.self) {
            window.top.location.href = authUrl;
        } else {
            window.location.href = authUrl;
        }
    } catch (e) {
        window.location.href = authUrl;
    }
})();
</script>
<noscript>
    <a href="<?php echo htmlspecialchars($auth_url, ENT_QUOTES, 'UTF-8'); ?>">Google 인증으로 이동</a>
</noscript>
</body>
</html>