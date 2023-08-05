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
if(!isset($_SESSION['secure_token']) || !isset($_POST['token']) || !preg_match("/^([a-z0-9]){64}$/", trim($_POST['token'])) || $_SESSION['secure_token'] != $_POST['token']){
    unset($_SESSION['secure_token']);
    echo json_encode(['error' => 1, 'description' => '503 Direct Access Denied.']);
    exit;
}
//올바른 세션, 모드 데이터 값이 존재하는지 확인
if(!isset($_POST['service_bridge_mode'])){
    unset($_SESSION['secure_token']);
    echo json_encode(['error' => 1, 'description' => '503 Wrong Access (1)']);
    exit;
}

//제공된 page acl이 괜찮은가 확인.
switch($_POST['service_bridge_mode']){
    case "board_login":
    case "board_register":
    case "board_logout":
        if($_SESSION['page_acl'] != "authorize"){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (2)']);
            exit;
        }
        break;
    case "leave_comment":
    case "revise_comment":
        if($_SESSION['page_acl'] != "comment"){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (2)']);
            exit;
        }
        break;
    case "leave_post":
    case "revise_post":
        if($_SESSION['page_acl'] != "post"){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (2)']);
            exit;
        }
        break;
    case "account_settings":
        if($_SESSION['page_acl'] != "account"){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (2)']);
            exit;
        }
        break;
}

//로그인 된 세션이 필요한 경우
switch($_POST['service_bridge_mode']){
    case "board_logout":
    case "leave_comment":
    case "revise_comment":
    case "leave_post":
    case "revise_post":
    case "account_settings":
        if($_SESSION['login']['valid'] == 0){
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (3)']);
            exit;
        }
        break;
}

//로그인 된 세션이 필요없는 경우
switch($_POST['service_bridge_mode']){
    case "board_login":
    case "board_register":
        if($_SESSION['login']['valid'] == 1){
            if(isset($_SESSION['secure_token'])){
                unset($_SESSION['secure_token']);
            }
            echo json_encode(['error' => 1, 'description' => '이미 로그인 된 계정입니다. 해당 기능을 사용할 수 없습니다.']);
            exit;
        }
        break;
}

//너무 잦은 form validation fail시 해당 세션에 freeze를 추가.
if(isset($_SESSION['form_limit_time'])){
    if(time() > $_SESSION['form_limit_time']){
        unset($_SESSION['form_limit_time']);
    }else{
        sleep(2);
    }
}

//개별 데이터베이스 및 데이터 형식 확인 & 프로세스.
$system_v['error_msg'] = "";
$system_v['error_count'] = 0;
switch($_POST['service_bridge_mode']){
    case "board_login":
        if(isset($_POST['form_id']) && preg_match("/^([0-9a-zA-Z]){4,20}$/", trim($_POST['form_id']))) {
            $system_v['form_id'] = trim($_POST['form_id']);
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>ID의 형식은 영문, 숫자 4 ~ 20자리 입니다.";
        }
        if(isset($_POST['form_pw']) && preg_match("/^([^<>\n\t\r\f]){8,32}$/", trim($_POST['form_pw']))){
            $system_v['form_pw'] =  hash("sha256", 'sAlT?0730abnrt12er9*zxpot'.trim($_POST['form_pw']).'p0ad3bsdj21asdf3gdfjamxz#!');
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>비밀번호의 형식은 개행문자 제외 8 ~ 32자리 입니다.";
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $sql_statement_prepared = "SELECT acc_number, acc_id, acc_onelogin, acc_group from board_account where acc_id = ? and acc_pw = ?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("ss",$system_v['form_id'],$system_v['form_pw']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if($count_row == 1){
            $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
            if($proc_data['acc_group'] == '2'){
                $system_v['error_msg'] = "로그인 할 수 없음 : 해당 계정은 차단되었습니다.";
                //duplicated source below
                if(!isset($_SESSION['form_request_limit'])){
                    $_SESSION['form_request_limit'] = 1;
                }else{
                    if(!isset($_SESSION['form_limit_time'])){
                        $_SESSION['form_request_limit'] += 1;
                    }
                }
                if($_SESSION['form_request_limit'] > 10){
                    if(!isset($_SESSION['form_limit_time'])){
                        $_SESSION['form_limit_time'] = time()+7200;
                    }
                    unset($_SESSION['form_request_limit']);
                    unset($_SESSION['secure_token']);
                    echo json_encode(['error' => 1, 'description' => $system_v['error_msg']]);
                    break;
                }
                //end
                echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
                break;
            }
            $_SESSION['login']['number'] = $proc_data['acc_number'];
            $_SESSION['login']['id'] = $proc_data['acc_id'];
            $_SESSION['user_ip'] = get_user_ip();
            $_SESSION['login']['onelogin'] = $proc_data['acc_onelogin'];
            $_SESSION['login']['group'] = $proc_data['acc_group'];
            $_SESSION['login']['valid'] = 1;
        }else if($count_row > 1){
            $system_v['error_msg'] = "심각한 오류 : 중복된 계정 정보가 발견되었습니다. 로그인 할 수 없습니다. 관리자에게 해당 오류를 보고하십시오.";
            unset($_SESSION['secure_token']);
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg']]);
            break;
        }else{
            $system_v['error_msg'] = "ID 또는 비밀번호가 틀렸거나, 없는 계정입니다.";
            //duplicated source below
            if(!isset($_SESSION['form_request_limit'])){
                $_SESSION['form_request_limit'] = 1;
            }else{
                if(!isset($_SESSION['form_limit_time'])){
                    $_SESSION['form_request_limit'] += 1;
                }
            }
            if($_SESSION['form_request_limit'] > 10){
                if(!isset($_SESSION['form_limit_time'])){
                    $_SESSION['form_limit_time'] = time()+7200;
                }
                unset($_SESSION['form_request_limit']);
                unset($_SESSION['secure_token']);
                echo json_encode(['error' => 1, 'description' => $system_v['error_msg']]);
                break;
            }
            //end
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $sql->close();
        if(isset($_SESSION['secure_token'])){
            unset($_SESSION['secure_token']);
        }
        echo json_encode(['success' => 1, 'description' => '성공적으로 로그인되었습니다.', 'redirect' => './?mode=board']);
        break;
    case "board_register":
        if(isset($_POST['form_id']) && preg_match("/^([0-9a-zA-Z]){4,20}$/", trim($_POST['form_id']))) {
            $system_v['form_id'] = trim($_POST['form_id']);
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>ID의 형식은 영문, 숫자 4 ~ 20자리 입니다.";
        }
        if(isset($_POST['form_pw']) && preg_match("/^([^<>\n\t\r\f]){8,32}$/", trim($_POST['form_pw']))){
            $system_v['form_pw'] =  hash("sha256", 'sAlT?0730abnrt12er9*zxpot'.trim($_POST['form_pw']).'p0ad3bsdj21asdf3gdfjamxz#!');
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>비밀번호의 형식은 개행문자 제외 8 ~ 32자리 입니다.";
        }
        if(!isset($_POST['form_pw_re']) || trim($_POST['form_pw_re']) != trim($_POST['form_pw'])){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>비밀번호 재입력 칸의 값과 비밀번호의 값이 일치하지 않습니다.";
        }
        if(isset($_POST['form_email']) && preg_match("/^(([a-zA-Z0-9]{1,12})([\.{1}])?([a-zA-Z0-9]{1,12})\@(((gmail|naver|icloud|hanmail)([\.])com)|(daum([\.])net)))$/", trim($_POST['form_email']))){
            $system_v['form_email'] = trim($_POST['form_email']);
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>이메일의 형식은 (유효한 문자 최대 25자리)@(허용되는 이메일 도메인) 입니다.";
        }
        if(!isset($_POST['form_check_terms']) || $_POST['form_check_terms'] != "on"){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>게시판의 개인정보처리방침 및 약관에 동의하지 않았습니다.";
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        //error_count를 데이터 형식과 sql 정제 후 확인으로 나눔.
        $sql_statement_prepared = "SELECT acc_number from board_account where acc_id = ? or acc_email = ?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("ss",$system_v['form_id'],$system_v['form_email']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if($count_row >= 1){
            $system_v['error_msg'] .= "<br>이미 존재하는 ID 또는 이메일로, 사용하실 수 없습니다.";
            $system_v['error_count'] += 1;
        }
        if($system_v['error_count'] != 0){
            //duplicated sources below
            if(!isset($_SESSION['form_request_limit'])){
                $_SESSION['form_request_limit'] = 1;
            }else{
                if(!isset($_SESSION['form_limit_time'])){
                    $_SESSION['form_request_limit'] += 1;
                }
            }
            if($_SESSION['form_request_limit'] > 10){
                if(!isset($_SESSION['form_limit_time'])){
                    $_SESSION['form_limit_time'] = time()+7200;
                }
                unset($_SESSION['form_request_limit']);
                unset($_SESSION['secure_token']);
                echo json_encode(['error' => 1, 'description' => $system_v['error_msg']]);
                break;
            }
            //end
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $system_v['registertime']= date("Y-m-d H:i:s", time());
        $system_v['acc_group'] = '0';
        $system_v['acc_onelogin'] = 1;
        $sql_statement_prepared = "INSERT INTO board_account (acc_id, acc_pw, acc_email, acc_onelogin, acc_group, registertime) VALUES (?, ?, ?, ?, ?, ?)";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("ssssss",$system_v['form_id'],$system_v['form_pw'],$system_v['form_email'],$system_v['acc_onelogin'],$system_v['acc_group'],$system_v['registertime']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $sql->close();
        if(isset($_SESSION['secure_token'])){
            unset($_SESSION['secure_token']);
        }
        echo json_encode(['success' => 1, 'description' => '회원가입이 완료되었습니다. 로그인 페이지로 넘어갑니다.', 'redirect' => './?mode=authorize', 'notice' => 1]);
        break;
    case "board_logout":
        $lg_e = 0;
        $lg_r = time();
        if(isset($_SESSION['form_request_limit'])){
            $lg_e = $_SESSION['form_request_limit'];
        }
        if(isset($_SESSION['form_limit_time'])){
            $lg_r = $_SESSION['form_limit_time'];
        }
        session_destroy();
        session_start();
        if(!isset($_SESSION['form_request_limit'])){
            $_SESSION['form_request_limit'] = $lg_e;
        }
        if(!isset($_SESSION['form_limit_time'])){
            $_SESSION['form_limit_time'] = $lg_r;
        }
        //secure_token이 unset 되어있으므로, okay.
        echo json_encode(['success' => 1, 'description' => '성공적으로 로그아웃 되었습니다.', 'redirect' => './?mode=board', 'notice' => 1]);
        break;
    case "revise_post":
    case "leave_post":
        //TODO: freeze 기능을 해당 기능에는 적용하지 않았음. 나중에 추가해야함.
        if(isset($_POST['form_header']) && preg_match("/^([^\n\t\r\f])*$/", trim($_POST['form_header']))) {
            $system_v['form_header'] = trim($_POST['form_header']);
            $bad_words = bad_words_check($system_v['form_header']);
            if($bad_words[0] === true){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>제목에 비속어 또는 특정 인물 또는 단체 등을 비하하는 단어, 부적절한 단어가 포함되어 있습니다";
                $system_v['error_msg'] .= "<br>필터링된 문자열(또는 문자열의 시작) : ".$bad_words[1];
            }else if(mb_strlen($system_v['form_header']) < 3 || mb_strlen($system_v['form_header']) > 100){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>글 제목은 2 ~ 100자리를 입력해야 합니다.";
            }
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>글 제목은 개행 문자 없이 입력해야 합니다.";
        }
        if(isset($_POST['form_contents'])){
            //개행문자를 </ br>로 치환
            $system_v['form_contents'] = nl2br(trim($_POST['form_contents']));
            if(mb_strlen($system_v['form_contents']) > 1 && mb_strlen($system_v['form_contents']) < 6000){
                //xss 차단
                $system_v['form_contents'] = htmlspecialchars($system_v['form_contents']);
                $bad_words = bad_words_check($system_v['form_contents']);
                if($bad_words[0] === true){
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>본문에 비속어 또는 특정 인물 또는 단체 등을 비하하는 단어, 부적절한 단어가 포함되어 있습니다";
                    $system_v['error_msg'] .= "<br>필터링된 문자열(또는 문자열의 시작) : ".$bad_words[1];
                }
            }else{
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>본문을 2 ~ 6000자 사이로 입력하세요.";
            }
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>폼 오류. 현재 기술적 문제로 인해 제출란의 일부 데이터가 누락되었습니다. 관리자에게 해당 오류를 문의하십시오.";
        }
        if(!isset($_POST['form_check_notice']) || $_POST['form_check_notice'] != "on"){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>글 쓰기 유의사항에 대해 확인하지 않았습니다.";
        }
        if($_POST['service_bridge_mode'] == "revise_post"){
            if(!isset($_POST['revise_post_number']) || !preg_match("/^([0-9]){1,20}$/", trim($_POST['revise_post_number']))){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>알 수 없는 게시물의 수정을 요청하였습니다.";
            }
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        //파일 업로드를 이용한 인젝션 및 트릭 공격에 대해 차단
        //소스를 바꿔서 다중 파일 업로드가 되도록 한 경우를 방지.
        if(is_array($_FILES['form_attachment']['name'])){
            $system_v['error_msg'] .= "<br>다중 파일 업로드는 허용되지 않습니다.";
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        if(empty($_FILES['form_attachment']['name']) || !is_uploaded_file($_FILES['form_attachment']['tmp_name'])){
            $system_v['form_attachment'] = '0';
            if($_POST['service_bridge_mode'] == "revise_post"){
                if (isset($_POST['form_check_file_revise']) && $_POST['form_check_file_revise'] == "on") {
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>첨부파일 수정 버튼을 눌렀으나, 첨부파일을 업로드하지 않았습니다.";
                }
            }
        }else {
            $system_v['form_attachment'] = '1';
            if ($_POST['service_bridge_mode'] == "revise_post") {
                if (!isset($_POST['form_check_file_revise']) || $_POST['form_check_file_revise'] != "on") {
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>첨부파일 수정 버튼을 누르지 않고, 첨부 파일을 업로드 하였습니다.";
                }
            }
            if ($_FILES['form_attachment']['error'] > 0) {
                echo '오류 발생 : ';
                switch ($_FILES['event_img_01']['error']) {
                    case 1:
                        $system_v['error_msg'] .= 'upload_max_filesize 초과';
                        break;
                    case 2:
                        $system_v['error_msg'] .= 'max_file_size 초과';
                        break;
                    case 3:
                        $system_v['error_msg'] .= '파일이 부분만 업로드됐습니다.';
                        break;
                    case 4:
                        $system_v['error_msg'] .= '파일을 선택해 주세요.';
                        break;
                    case 6:
                        $system_v['error_msg'] .= '임시 폴더가 존재하지 않습니다. 서버 오류';
                        break;
                    case 7:
                        $system_v['error_msg'] .= '임시 폴더에 파일을 쓸 수 없습니다. 퍼미션 오류';
                        break;
                    case 8:
                        $system_v['error_msg'] .= '확장에 의해 파일 업로드가 중지되었습니다.';
                        break;
                }
            }
            $system_v['form_attachment_name'] = $_FILES['form_attachment']['name'];
            //UNIX, WINDOWS에서 허용되지 않는 파일 이름 형식을 거름
            if (strlen($system_v['form_attachment_name']) !== strcspn($system_v['form_attachment_name'], "\0\/:;*?\"'<>|")) {
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>파일 이름에 허용되지 않는 문자(\0 \ / : ; * ? \ " \' < > |)가 포함되어 있습니다.';
            }
            //확장자 자체가 파일 이름인 경우를 거름
            if (strlen($system_v['form_attachment_name']) === strcspn($system_v['form_attachment_name'], ".")) {
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>업로드한 파일은 파일 확장자가 없는 파일입니다.';
            }
            //파일 이름이 32자를 초과하는 경우를 거름
            if (strlen($system_v['form_attachment_name']) > 32) {
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>첨부파일의 이름은 32자리를 넘길 수 없습니다.';
            }
            //파일 크기 제한.
            $system_v['form_attachment_size'] = filesize($_FILES['form_attachment']['tmp_name']) / 1024;
            if ($system_v['form_attachment_size'] > 2048) {
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>파일 크기가 2MB를 초과합니다.';
            }
            //확장자 확인. php 핸들러로의 파일이 감지, 서버가 오인하는 경우를 방지.
            $a = array('php', 'phps', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'xml', 'html', 'xhtml', 'htaccess', 'inc');
            $exts = explode('.', strtolower($system_v['form_attachment_name']));
            foreach ($a as $v) {
                if (in_array($v, $exts)) {
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= '<br>Invalid Extensions : <b>.' . $v . '</b>(이)가 포함되어 있어 해당 파일을 업로드 할 수 없습니다.';
                }
            }
            //사진 확장자와 zip 확장자를 분리하여 파일 평가.
            $system_v['form_attachment_ext'] = ext_extract($system_v['form_attachment_name']);
            $a = array('jpg', 'png', 'gif', 'bmp');
            $system_v['ext_invalid'] = 0;
            $system_v['ext_format'] = null;
            //이미지 확장자 체크
            foreach ($a as $v) {
                if ($system_v['form_attachment_ext'] != $v) {
                    $system_v['ext_invalid'] += 1;
                } else {
                    $system_v['ext_format'] = 'image';
                }
            }
            //zip 확장자 체크
            if ($system_v['ext_invalid'] != 0) {
                $a = array('zip');
                foreach ($a as $v) {
                    if ($system_v['form_attachment_ext'] != $v) {
                        $system_v['ext_not_file'] += 1;
                    } else {
                        $system_v['ext_format'] = 'zip';
                    }
                }
            }
            //파일 확장자 및 파일의 형태를 조사함.
            if ($system_v['ext_format'] == 'image') {
                //resource_check에 명시한 이미지 정보 확인 함수.
                //헤더와 형태만 이미지 파일인 척하는 웹 셸 같은것들을 거름.
                $chk_image_file = chk_image_file($_FILES['form_attachment']['tmp_name']);
                if ($chk_image_file != 'true') {
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= '<br>유효하지 않은 이미지 파일입니다 : ' . $chk_image_file;
                }
            } else if ($system_v['ext_format'] == 'zip') {
                //헤더와 형태만 zip 압축 파일인 척하는 웹 셸 같은 것들을 거름.
                $chk_zip = new ZipArchive;
                $res = $chk_zip->open($_FILES['form_attachment']['tmp_name']);
                if ($res !== true) {
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= '<br>업로드한 압축파일은 손상되었거나, 올바르지 않은 압축파일 입니다.';
                }
            } else {
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>허용된 파일 확장자가 아닙니다.';
            }
        }
        if ($system_v['error_count'] != 0) {
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        if($_POST['service_bridge_mode'] == "revise_post"){
            //현재 수정하려는 게시물이 요청을 보낸자가 쓴게 맞는지 확인
            $system_v['revise_post_number'] = trim($_POST['revise_post_number']);
            $sql_statement_prepared = "SELECT board_number,board_relatedid, board_tag, board_blind, board_attachment from lee_board where board_number=? order by board_number";
            $sql->prepare($sql_statement_prepared);
            $sql->bind_param("i",$system_v['revise_post_number']);
            $sql->execute();
            $db_result = $sql->get_result();
            if($sql->error){
                error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
            }
            $count_row = $db_result->num_rows;
            if($count_row == 0){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>요청한 게시물 정보가 없어 수정할 수 없습니다.';
            }else if($count_row != 1){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>요청한 게시물은 현재 duplicated 상태로 시스템 문제가 있어 수정할 수 없습니다.';
            }
            $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
            if($proc_data['board_blind'] == 1){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>해당 글은 블라인드 처리되었으므로, 수정할 수 없습니다.';
            }
            if($proc_data['board_tag'] == 2){
                if($_SESSION['login']['group'] != 1){
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= '<br>해당 글을 수정할 권한이 없습니다.';
                }
            }
            if($_SESSION['login']['number'] != $proc_data['board_relatedid']){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= '<br>요청한 게시물에 대해 수정할 권한이 없습니다.';
            }
            if($system_v['error_count'] != 0){
                echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
                break;
            }
            if(isset($_POST['form_post_delete']) && $_POST['form_post_delete'] == "on") {
                //게시물 삭제 기능
                //첨부파일 삭제
                $upload_dir = "./cdn/board/files/";
                $uploaded_file_dir = $upload_dir.$proc_data['board_attachment'];
                if($proc_data['board_attachment'] != '0') {
                    if(!unlink($uploaded_file_dir)) {
                        $cant_delete_attachment = 1;
                    }
                }
                //lee_board 게시물 삭제
                $sql_statement_prepared = "DELETE FROM lee_board WHERE board_number=?";
                $sql->prepare($sql_statement_prepared);
                $sql->bind_param("s", $system_v['revise_post_number']);
                $sql->execute();
                $db_result = $sql->get_result();
                if($sql->error){
                    error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                }
                //lee_board 게시물과 연관된 댓글 삭제
                $sql_statement_prepared = "DELETE FROM lee_board_comment WHERE comment_related_number=?";
                $sql->prepare($sql_statement_prepared);
                $sql->bind_param("s", $system_v['revise_post_number']);
                $sql->execute();
                $db_result = $sql->get_result();
                if($sql->error){
                    error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                }
                if(isset($_SESSION['secure_token'])){
                    unset($_SESSION['secure_token']);
                }
                if($cant_delete_attachment == 1){
                    echo json_encode(['success' => 1, 'description' => '해당 게시물과 이에 종속된 데이터들을 삭제하였습니다.\n하지만, 게시물의 첨부파일은 삭제 시도하였으나, 기타 사유로 삭제하지 못했습니다.', 'notice' => 1]);
                    break;
                }
                echo json_encode(['success' => 1, 'description' => '해당 게시물과 이에 종속된 데이터들을 모두 삭제하였습니다.', 'notice' => 1]);
                break;
            }
            if($system_v['form_attachment'] == '0') {
                $default_query_options = 0;
                $system_v['registertime'] = date("Y-m-d H:i:s", time());
                $sql_statement_prepared = "UPDATE lee_board SET board_header=?, board_contents=?, board_revised=? WHERE board_number=?";
                $sql->prepare($sql_statement_prepared);
                $sql->bind_param("ssss",$system_v['form_header'], $system_v['form_contents'], $system_v['registertime'], $system_v['revise_post_number']);
                $sql->execute();
                $db_result = $sql->get_result();
                if($sql->error){
                    error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                }
                $sql->close();
                if(isset($_SESSION['secure_token'])){
                    unset($_SESSION['secure_token']);
                }
                echo json_encode(['success' => 1, 'description' => '올바르게 글을 업데이트 했습니다.', 'redirect' => './?mode=view_post&view='.$system_v['revise_post_number'], 'notice' => 1]);
            }else{
                $system_v['form_attachment_name'] = sha1(mt_rand(1, 1000000000).get_user_ip().'lee_board_09z4nxt845m10i8dr'.time()).$_FILES['form_attachment']['name'];
                //파일 저장 디렉토리로 업로드
                $upload_dir = "./cdn/board/files/";
                $uploaded_file_dir = $upload_dir.$proc_data['board_attachment'];
                $upload_dir .= $system_v['form_attachment_name'];
                $cant_delete_attachment = 0;
                if($proc_data['board_attachment'] != '0') {
                    if(!unlink($uploaded_file_dir)) {
                        $cant_delete_attachment = 1;
                    }
                }
                move_uploaded_file($_FILES['form_attachment']['tmp_name'], $upload_dir);
                $default_query_options = 0;
                $system_v['registertime'] = date("Y-m-d H:i:s", time());
                $sql_statement_prepared = "UPDATE lee_board SET board_header=?, board_contents=?, board_attachment=?, board_revised=? WHERE board_number=?";
                $sql->prepare($sql_statement_prepared);
                $sql->bind_param("sssss",$system_v['form_header'], $system_v['form_contents'], $system_v['form_attachment_name'], $system_v['registertime'], $system_v['revise_post_number']);
                $sql->execute();
                $db_result = $sql->get_result();
                if($sql->error){
                    error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                }
                $sql->close();
                if(isset($_SESSION['secure_token'])){
                    unset($_SESSION['secure_token']);
                }
                if($cant_delete_attachment != 0){
                    echo json_encode(['success' => 1, 'description' => '올바르게 글을 수정했으나, 이전 게시글의 첨부파일을 삭제하지 못했습니다. 이미 서버에서 처리되었거나, 특정 이유로 삭제된 것이 이유일 수 있습니다.', 'redirect' => './?mode=view_post&view='.$system_v['revise_post_number'], 'notice' => 1]);
                }else{
                    echo json_encode(['success' => 1, 'description' => '올바르게 글을 수정했습니다.', 'redirect' => './?mode=view_post&view='.$system_v['revise_post_number'], 'notice' => 1]);
                }
            }
            break;
        }
        if($system_v['form_attachment'] == '0'){
            $default_query_options = 0;
            $system_v['registertime'] = date("Y-m-d H:i:s", time());
            $sql_statement_prepared = "INSERT INTO lee_board (board_relatedid, board_tag, board_blind, board_header, board_contents, board_attachment, board_revised, board_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $sql->prepare($sql_statement_prepared);
            $sql->bind_param("ssssssss",$_SESSION['login']['number'],$default_query_options,$default_query_options,$system_v['form_header'],$system_v['form_contents'],$system_v['form_attachment'],$system_v['registertime'],$system_v['registertime']);
            $sql->execute();
            $db_result = $sql->get_result();
            if($sql->error){
                error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
            }
            $last_id = $sql->insert_id;
            $sql->close();
            if(isset($_SESSION['secure_token'])){
                unset($_SESSION['secure_token']);
            }
            echo json_encode(['success' => 1, 'description' => '올바르게 글을 작성했습니다.', 'redirect' => './?mode=view_post&view='.$last_id, 'notice' => 1]);
        }else{
            $system_v['form_attachment_name'] = sha1(mt_rand(1, 1000000000).get_user_ip().'lee_board_09z4nxt845m10i8dr'.time()).$_FILES['form_attachment']['name'];
            //파일 저장 디렉토리로 업로드
            $upload_dir = "./cdn/board/files/";
            $upload_dir .= $system_v['form_attachment_name'];
            $default_query_options = 0;
            move_uploaded_file( $_FILES['form_attachment']['tmp_name'], $upload_dir);
            $system_v['registertime'] = date("Y-m-d H:i:s", time());
            $sql_statement_prepared = "INSERT INTO lee_board (board_relatedid, board_tag, board_blind, board_header, board_contents, board_attachment, board_revised, board_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $sql->prepare($sql_statement_prepared);
            $sql->bind_param("ssssssss",$_SESSION['login']['number'],$default_query_options,$default_query_options,$system_v['form_header'],$system_v['form_contents'],$system_v['form_attachment_name'],$system_v['registertime'],$system_v['registertime']);
            $sql->execute();
            $db_result = $sql->get_result();
            if($sql->error){
                @unlink($upload_dir."/".$system_v['form_attachment_name']);
                error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
            }
            $last_id = $sql->insert_id;
            $sql->close();
            if(isset($_SESSION['secure_token'])){
                unset($_SESSION['secure_token']);
            }
            echo json_encode(['success' => 1, 'description' => '올바르게 글을 작성했습니다.', 'redirect' => './?mode=view_post&view='.$last_id, 'notice' => 1]);
        }
        break;
    case "leave_comment":
        if(isset($_POST['form_contents'])){
            //개행문자를 </ br>로 치환
            $system_v['form_contents'] = nl2br(trim($_POST['form_contents']));
            if(mb_strlen($system_v['form_contents']) > 1 && mb_strlen($system_v['form_contents']) < 1000){
                //xss 차단
                $system_v['form_contents'] = htmlspecialchars($system_v['form_contents']);
                $bad_words = bad_words_check($system_v['form_contents']);
                if($bad_words[0] === true){
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>댓글에 비속어 또는 특정 인물 또는 단체 등을 비하하는 단어, 부적절한 단어가 포함되어 있습니다";
                    $system_v['error_msg'] .= "<br>필터링된 문자열(또는 문자열의 시작) : ".$bad_words[1];
                }
            }else{
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>댓글을 2 ~ 1000자 사이로 입력하세요.";
            }
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>폼 오류. 현재 기술적 문제로 인해 제출란의 일부 데이터가 누락되었습니다. 관리자에게 해당 오류를 문의하십시오.";
        }
        $system_v['post_number'] = trim($_POST['post_number']);
        if(!preg_match("/^([0-9]){1,20}$/", $system_v['post_number'])){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>게시물 번호 형식이 아닙니다.";
        }
        $sql_statement_prepared = "SELECT board_number, board_relatedid, board_tag, board_blind from lee_board where board_number=? order by board_number";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("i",$system_v['post_number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if($count_row == 0){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>게시물 정보가 없어 댓글을 달 수 없습니다.';
        }else if($count_row != 1){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 게시물은 현재 duplicated 상태로 시스템 문제가 있어 댓글을 달 수 없습니다.';
        }
        $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
        if($proc_data['board_blind'] == 1){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 글은 블라인드 처리되었으므로, 댓글을 달 수 없습니다.';
        }
        if($proc_data['board_tag'] == 2){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 글에 댓글을 달 수 없습니다.';
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $system_v['registertime'] = date("Y-m-d H:i:s", time());
        $default_query_options = 0;
        $sql_statement_prepared = "INSERT INTO lee_board_comment (comment_relatedid, comment_related_number, comment_blind, comment_contents, comment_revised, comment_time) VALUES (?, ?, ?, ?, ?, ?)";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("ssssss", $_SESSION['login']['number'], $system_v['post_number'], $default_query_options, $system_v['form_contents'], $system_v['registertime'], $system_v['registertime']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $sql->close();
        if(isset($_SESSION['secure_token'])){
            unset($_SESSION['secure_token']);
        }
        echo json_encode(['success' => 1, 'description' => '올바르게 댓글을 작성했습니다.', 'redirect' => './?mode=view_post&view='.$system_v['post_number'], 'notice' => 1]);
        break;
    case "revise_comment":
        //leave_comment와 겹침.
        if(isset($_POST['form_contents'])){
            //개행문자를 </ br>로 치환
            $system_v['form_contents'] = nl2br(trim($_POST['form_contents']));
            if(mb_strlen($system_v['form_contents']) > 1 && mb_strlen($system_v['form_contents']) < 1000){
                //xss 차단
                $system_v['form_contents'] = htmlspecialchars($system_v['form_contents']);
                $bad_words = bad_words_check($system_v['form_contents']);
                if($bad_words[0] === true){
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>댓글에 비속어 또는 특정 인물 또는 단체 등을 비하하는 단어, 부적절한 단어가 포함되어 있습니다";
                    $system_v['error_msg'] .= "<br>필터링된 문자열(또는 문자열의 시작) : ".$bad_words[1];
                }
            }else{
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>댓글을 2 ~ 1000자 사이로 입력하세요.";
            }
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>폼 오류. 현재 기술적 문제로 인해 제출란의 일부 데이터가 누락되었습니다. 관리자에게 해당 오류를 문의하십시오.";
        }
        $system_v['comment_number'] = trim($_POST['comment_number']);
        if(!preg_match("/^([0-9]){1,20}$/", $system_v['comment_number'])){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>게시물 번호 형식이 아닙니다.";
        }
        $sql_statement_prepared = "SELECT comment_number, comment_relatedid, comment_related_number, comment_blind from lee_board_comment where comment_number=? order by comment_number";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("i",$system_v['comment_number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if($count_row == 0){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>수정하려는 댓글은 이미 삭제되었거나, 존재하지 않습니다.';
        }else if($count_row != 1){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 댓글은 현재 duplicated 상태로 시스템 문제가 있어 수정할 수 없습니다.';
        }
        $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
        if($proc_data['comment_blind'] == 1){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 댓글은 블라인드 처리되었으므로, 수정할 수 없습니다.';
        }
        if($proc_data['comment_relatedid'] != $_SESSION['login']['number']){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= '<br>해당 댓글은 본인이 작성하지 않았습니다.';
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $post_number = $proc_data['comment_related_number'];
        if(isset($_POST['form_comment_delete']) && $_POST['form_comment_delete'] == "on") {
            $sql_statement_prepared = "DELETE FROM lee_board_comment WHERE comment_number=?";
            $sql->prepare($sql_statement_prepared);
            $sql->bind_param("i", $system_v['comment_number']);
            $sql->execute();
            $db_result = $sql->get_result();
            if($sql->error){
                error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
            }
            if(isset($_SESSION['secure_token'])){
                unset($_SESSION['secure_token']);
            }
            echo json_encode(['success' => 1, 'description' => '올바르게 해당 댓글을 삭제 했습니다.', 'redirect' => './?mode=view_post&view='.$post_number, 'notice' => 1]);
            break;
        }
        $default_query_options = 0;
        $system_v['registertime'] = date("Y-m-d H:i:s", time());
        $sql_statement_prepared = "UPDATE lee_board_comment SET comment_contents=?, comment_revised=? WHERE comment_number=?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("sss",$system_v['form_contents'], $system_v['registertime'], $system_v['comment_number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $sql->close();
        if(isset($_SESSION['secure_token'])){
            unset($_SESSION['secure_token']);
        }
        echo json_encode(['success' => 1, 'description' => '올바르게 댓글을 업데이트 했습니다.', 'redirect' => './?mode=view_post&view='.$post_number, 'notice' => 1]);
        break;
    case "account_settings":
        $system_v['error_count'] = 0;
        $system_v['error_msg'] = "";
        $system_v['form_pw'] = null;
        $system_v['form_email_change'] = null;
        $system_v['form_change_pw'] = null;
        $system_v['form_check_all_logout'] = null;
        if(!isset($_POST['form_check_be_down']) || $_POST['form_check_be_down'] != "on"){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>계정 정보 변경 동의를 하지 않았습니다.";
        }
        if(!isset($_POST['form_pw']) || !preg_match("/^([^<>\n\t\r\f]){8,32}$/", trim($_POST['form_pw']))){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>현재 비밀번호의 형식이 잘못되었습니다.";
        }else{
            $system_v['form_pw'] =  hash("sha256", 'sAlT?0730abnrt12er9*zxpot'.trim($_POST['form_pw']).'p0ad3bsdj21asdf3gdfjamxz#!');
        }
        //모든 기기에서 로그아웃.
        if(isset($_POST['form_check_all_logout']) && $_POST['form_check_all_logout'] == "on"){
            $system_v['form_check_all_logout'] = 1;
        }else{
            $system_v['form_check_all_logout'] = 0;
        }
        //비밀번호 변경
        if(isset($_POST['form_check_change_pw']) && $_POST['form_check_change_pw'] == "on"){
            if(!isset($_POST['form_change_pw']) || !preg_match("/^([^<>\n\t\r\f]){8,32}$/", trim($_POST['form_change_pw']))){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>변경할 비밀번호의 형식이 잘못되었습니다.";
            }else{
                if(trim($_POST['form_change_pw']) == trim($_POST['form_change_pw_re'])){
                    $system_v['form_check_all_logout'] = 1;
                    $system_v['form_change_pw'] = hash("sha256", 'sAlT?0730abnrt12er9*zxpot'.trim($_POST['form_change_pw']).'p0ad3bsdj21asdf3gdfjamxz#!');
                }else{
                    $system_v['error_count'] += 1;
                    $system_v['error_msg'] .= "<br>변경할 비밀번호와 변경할 비밀번호 재입력란의 값이 일치하지 않습니다.";
                }
            }
        }else{
            if(!empty($_POST['form_change_pw']) || !empty($_POST['form_change_pw_re'])){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>비밀번호 변경을 체크하지 않고, 바꿀 비밀번호 또는 비밀번호 재입력란에 값을 입력했습니다.";
            }
            //비밀번호 변경이 택하지 않았더라면, 기존 비밀번호로 설정
            $system_v['form_change_pw'] = $system_v['form_pw'];
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $sql_statement_prepared = "SELECT acc_number, acc_pw, acc_email, acc_onelogin from board_account where acc_number = ?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("i",$_SESSION['login']['number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $count_row = $db_result->num_rows;
        if($count_row == 1) {
            $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
        }else{
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>세션에 저장된 사용자 정보를 불러오는 도중에 충돌 문제가 발생하였습니다.";
        }
        if($proc_data['acc_pw'] != $system_v['form_pw']){
            $system_v['error_count'] += 1;
            $system_v['error_msg'] .= "<br>현재 비밀번호가 일치하지 않습니다.";
        }
        //이메일 설정
        if(isset($_POST['form_check_change_email']) && $_POST['form_check_change_email'] == "on"){
            if(isset($_POST['form_email_change']) && preg_match("/^(([a-zA-Z0-9]{1,12})([\.{1}])?([a-zA-Z0-9]{1,12})\@(((gmail|naver|icloud|hanmail)([\.])com)|(daum([\.])net)))$/", trim($_POST['form_email_change']))){
                $system_v['form_email_change'] = trim($_POST['form_email_change']);
            }else{
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>이메일의 형식은 (유효한 문자 최대 25자리)@(허용되는 이메일 도메인) 입니다.";
            }
        }else{
            if(!empty($_POST['form_email_change'])){
                $system_v['error_count'] += 1;
                $system_v['error_msg'] .= "<br>이메일 변경을 체크하지 않고, 이메일 변경란에 값을 입력했습니다.";
            }else{
                //이메일 변경을 택하지 않았더라면, 기존 이메일로 설정.
                $system_v['form_email_change'] = $proc_data['acc_email'];
            }
        }
        //모든 기기에서 로그아웃.
        if($system_v['form_check_all_logout'] == 1){
            $system_v['form_check_all_logout'] = $proc_data['acc_onelogin'] + 1;
        }else{
            //택하지 않았더라면 기존 값으로
            $system_v['form_check_all_logout'] = $proc_data['acc_onelogin'];
        }
        if($system_v['error_count'] != 0){
            echo json_encode(['error' => 1, 'description' => $system_v['error_msg'], 'goahead' => 1]);
            break;
        }
        $default_query_options = 0;
        $sql_statement_prepared = "UPDATE board_account SET acc_pw=?, acc_email=?, acc_onelogin=? WHERE acc_number=?";
        $sql->prepare($sql_statement_prepared);
        $sql->bind_param("ssss",$system_v['form_change_pw'], $system_v['form_email_change'], $system_v['form_check_all_logout'], $_SESSION['login']['number']);
        $sql->execute();
        $db_result = $sql->get_result();
        if($sql->error){
            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
        }
        $sql->close();
        if(isset($_SESSION['secure_token'])){
            unset($_SESSION['secure_token']);
        }
        echo json_encode(['success' => 1, 'description' => '사용자 정보를 업데이트 하였습니다.', 'redirect' => './?mode=account_settings', 'notice' => 1]);
        break;
    default:
        if($_SESSION['login']['valid'] == 0){
            echo json_encode(['error' => 1, 'description' => '503 Wrong Access (4)']);
            exit;
        }
        break;
}
?>
