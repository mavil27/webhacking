<?php
session_start();
//상위레벨에 세션 시작.
const main_index_files = 1024;
if(!isset($system_v)){
    $system_v = array();
}
$sql = "";
include_once "./phpresources/resource_check.php";
include_once "./phpresources/is_valid_user.php";

//token 존재 여부 및 token 일치 여부 확인.
if(!isset($_SESSION['board_attachment_token']) || !isset($_GET['access_token'])){
    unset($_SESSION['board_attachment_token']);
    echo json_encode(['error' => 1, 'description' => '401 파일 엑세스를 위한 세션이 만료되었습니다. 새로고침하여 문제를 해결할 수도 있습니다.', 'notice' => 1]);
    exit;
}
$access_token = trim($_GET['access_token']);
$access_token = urldecode(base64_decode($access_token));
if($_SESSION['board_attachment_token'] != $access_token){
    unset($_SESSION['board_attachment_token']);
    echo json_encode(['error' => 1, 'description' => '401 파일 엑세스를 위한 세션이 만료되었거나 접근이 잘못되었습니다. 새로고침하여 문제를 해결할 수도 있습니다.', 'notice' => 1]);
    exit;
}
//모드 데이터 값이 존재하는지 확인
if(!isset($_POST['request_mode']) || !isset($_GET['file'])){
    unset($_SESSION['board_attachment_token']);
    echo json_encode(['error' => 1, 'description' => '503 Invalid Request sets.', 'notice' => 1]);
    exit;
}
switch($_POST['request_mode']){
    case "get_attachment":
    case "check_param_valid":
        if($_SESSION['login']['valid'] == 0){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '파일 다운로드를 위해서는 로그인을 하셔야 합니다.', 'notice' => 1]);
            exit;
        }
        break;
    default:
        unset($_SESSION['board_attachment_token']);
        echo json_encode(['error' => 1, 'description' => '503 Invalid Request sets.', 'notice' => 1]);
        exit;
}
if(!isset($_SESSION['board_attachment_number']) || !preg_match("/^([0-9]){1,20}$/", $_SESSION['board_attachment_number'])){
    echo json_encode(['error' => 1, 'description' => '100 파일 엑세스 권한은 있으나, 파일 정보를 읽을 수 없습니다.', 'notice' => 1]);
    exit;
}
$system_v['error_count'] = 0;
$system_v['error_msg'] = "";

$sql_statement_prepared = "SELECT board_number, board_attachment from lee_board where board_number = ?";
$sql->prepare($sql_statement_prepared);
$sql->bind_param("i",$_SESSION['board_attachment_number']);
$sql->execute();
$db_result = $sql->get_result();
if($sql->error){
    error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
}
$count_row = $db_result->num_rows;
if($count_row != 1){
    $system_v['error_count'] += 1;
    $system_v['error_msg'] .= "100 파일 정보를 검증하는 도중에, 충돌이 발생했습니다. 데이터베이스 오류입니다.";
}
$proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
$file_name = mb_substr( $proc_data['board_attachment'], 40);
if($file_name != trim($_GET['file'])){
    $system_v['error_count'] += 1;
    $system_v['error_msg'] .= "404 파일 정보 불일치. 해당 파일을 찾을 수 없습니다.";
}
if($system_v['error_count'] != 0){
    echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'notice' => 1]);
    exit;
}
switch($_POST['request_mode']) {
    case "get_attachment":
        $filepath = './cdn/board/files/';
        $filepath .= $proc_data['board_attachment'];
        $filesize = filesize($filepath);
        header("Pragma: public");
        header("Expires: 0");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$file_name\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: $filesize");
        readfile($filepath);
        break;
    case "check_param_valid":
        echo json_encode(['success' => 1, 'description' => '파일 다운로드가 유효한 세션입니다.']);
        break;
}
exit;
?>