<?php
//ini_set( "display_errors", 1 );
if(main_index_files != 1024){
    echo "503 : Direct Access is Denied!";
    exit;
}else if(!isset($system_v)){
    echo "100 : Initialize Error.";
    exit;
}
if(!isset($sql)){
    $sql = "";
}
include_once "./resource_check.php";

$system_v['login']['valid'] = 0;
$system_v['login']['id'] = "옵저버 : ".get_user_ip()[0];
$system_v['login']['authorize_phrase'] = "로그인";
$system_v['login']['authorize_link'] = "./?mode=authorize";
$system_v['login']['number'] = null;

if(isset($_SESSION['login']) && $_SESSION['login']['valid'] == 1) {
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] == get_user_ip()) {
        $system_v['login']['valid'] = 1;
        $system_v['login']['id'] = "유저 : " . $_SESSION['login']['id'];
        $system_v['login']['number'] = $_SESSION['login']['number'];
        $system_v['login']['authorize_phrase'] = "로그아웃";
        $system_v['login']['authorize_link'] = "./?mode=authorize&logout";
        //모든 기기에 로그인된 세션 파기 옵션이 데이터베이스에 만들어놨기에, 여기에 구현해야함.
        $sql_statement_prepared = "SELECT acc_number, acc_onelogin from board_account where acc_number = ?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("i", $_SESSION['login']['number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if ($sql->error) {
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if ($count_row == 1) {
            $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
        } else {
            echo json_encode(['error' => 1, 'description' => '현재 로그인된 사용자 정보를 검증하는 도중 충돌이 발생했습니다. 인터넷 접속 프로그램을 완전히 닫고 재로그인 하십시오.', 'notice' => 1]);
            exit;
        }
        if ($_SESSION['login']['onelogin'] != $proc_data['acc_onelogin']) {
            unset($_SESSION['login']);
            //혹시 몰라서 session_destroy가 안될 수 있기 때문에, unset 함수 사용.
            $lg_e = 0;
            $lg_r = time();
            if (isset($_SESSION['form_request_limit'])) {
                $lg_e = $_SESSION['form_request_limit'];
            }
            if (isset($_SESSION['form_limit_time'])) {
                $lg_r = $_SESSION['form_limit_time'];
            }
            session_destroy();
            session_start();
            $system_v['login']['valid'] = 0;
            $system_v['login']['id'] = "옵저버 : " . get_user_ip()[0];
            $system_v['login']['authorize_phrase'] = "로그인";
            $system_v['login']['authorize_link'] = "./?mode=authorize";
            $system_v['login']['number'] = null;
            if (!isset($_SESSION['form_request_limit'])) {
                $_SESSION['form_request_limit'] = $lg_e;
            }
            if (!isset($_SESSION['form_limit_time'])) {
                $_SESSION['form_limit_time'] = $lg_r;
            }
        }
        $_SESSION['user_ip'] = get_user_ip();
    }else {
        $_SESSION['login']['valid'] = 0;
    }
}
//ip 일치 여부 확인

?>