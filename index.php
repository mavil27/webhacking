<?php
//ini_set( "display_errors", 1 );
//php.ini에서 extension=mysqli 활성화, short tag 활성화
session_start();
//세션을 시작
const main_index_files = 1024;
//phpresources의 리소스 파일의 경우 해당 상수의 존재 여부를 토대로, direct directory 접속을 차단함.
$system_v = array();
$sql = "";

include_once "./phpresources/resource_check.php";
include_once  "./phpresources/is_valid_user.php";

//initialize main variables
$system_v['view'] = null;
$system_v['mode'] = null;
$system_v['header_name'] = null;
$system_v['page'] = 0;
$system_v['token'] = mk_sess_tkn();
//service_bridge로 내용을 보낼 여지가 있는 페이지에 acl 권한을 생성. post, account, comment, authorize
$_SESSION['page_acl'] = "none";

//board_attachment download 엑세스 토큰이 처음 페이지 접속시 있을 때 삭제.
if (isset($_SESSION['board_attachment_token'])) {
    unset($_SESSION['board_attachment_token']);
}
if (isset($_SESSION['board_attachment_number'])) {
    unset($_SESSION['board_attachment_number']);
}

if (isset($_GET['mode'])) {
    switch ($_GET['mode']) {
            //view_my_posts, view_post, board, notice는 모두 system_board_resource가 include되어 해당 개체에서 처리됨.
            //write_post는 system_file_upload_resource와 system_post_resource에서 처리됨.
            //account_settings는 system_account_resource에서 처리됨.
            //search는 system_search_resource에서 처리됨.
            //authorize는 system_account_resource에서 처리됨.
            //전체 phpresources는 system_main의 클래스 종속 함수와 함께 처리되며, system_administrator_management의 경우 서버와 동일 IP에서 계정 접속 시 접근할 수 있음.
        case "view_my_posts":
            $system_v['mode'] = "view_my_posts";
            $system_v['view'] = "view_my_posts";
            $system_v['header_name'] = "내가 쓴 글 보기";
            break;
        case "write_post":
            if (isset($_GET['view']) && preg_match("/^([0-9]){1,20}$/", $_GET['view'])) {
                $system_v['view'] = $_GET['view'];
            }
            $_SESSION['page_acl'] = "post";
            $system_v['mode'] = "write_post";
            $system_v['header_name'] = "글 쓰기";
            break;
        case "account_settings":
            $_SESSION['page_acl'] = "account";
            $system_v['mode'] = "account_settings";
            $system_v['header_name'] = "계정 설정";
            break;
        case "search":
            $system_v['mode'] = "search";
            if (isset($_GET['value']) && preg_match("/^([^<>\n\t\r\f]){3,32}$/", $_GET['value'])) {
                $system_v['view'] = $_GET['value'];
                //purify 필수.
            }
            $system_v['header_name'] = "검색";
            break;
        case "user_info":
            if (isset($_GET['view']) && preg_match("/^([0-9]{1,20})\|([0-9a-zA-Z]{4,20})$/", $_GET['view'])) {
                $system_v['view'] = $_GET['view'];
            }
            $system_v['mode'] = "user_info";
            $system_v['header_name'] = "유저 정보";
            break;
        case "view_post":
            $_SESSION['page_acl'] = "comment";
            $system_v['mode'] = "view_post";
            if (isset($_GET['view']) && preg_match("/^([0-9]){1,20}$/", $_GET['view'])) {
                $system_v['view'] = $_GET['view'];
            }
            $system_v['header_name'] = "게시판 열람";
            break;
        case "authorize":
            $_SESSION['page_acl'] = "authorize";
            $system_v['mode'] = "authorize";
            if (isset($_GET['signup'])) {
                $system_v['view'] = "signup";
                $system_v['header_name'] = "회원가입";
            } else if (isset($_GET['logout'])) {
                $system_v['view'] = "logout";
                $system_v['header_name'] = "로그아웃";
            } else {
                $system_v['view'] = "view";
                $system_v['header_name'] = "로그인";
            }
            break;
        case "board":
        default:
            $system_v['mode'] = "board";
            $system_v['header_name'] = "게시판";
            if (isset($_GET['view']) && $_GET['view'] == "notice") {
                $system_v['mode'] = "notice";
                $system_v['header_name'] = "공지사항";
            }
            break;
    }
} else {
    //index 기본은 board 보기.
    $system_v['mode'] = "board";
    $system_v['header_name'] = "게시판";
}
if (isset($_GET['page']) && preg_match("/^([0-9]){1,20}$/", $_GET['page'])) {
    $system_v['page'] = $_GET['page'];
}
$system_v['end_page'] = $system_v['page'] + 20;
$_SESSION['secure_token'] = $system_v['token'];
?>
<!doctype html>
<html lang="ko-KR">

<head>
    <meta lang="kr">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?= $system_v['token'] ?>">
    <title>게시판</title>
    <link rel="stylesheet" href="./cdn/css/semantic.min.css?230727" />
    <script type="text/javascript" defer src="./cdn/js/jquery.js"></script>
    <script type="text/javascript" defer src="./cdn/js/semantic.min.js"></script>
    <script type="text/javascript" defer src="./cdn/js/main_07bch1.js?6"></script>
</head>

<body>
    <?php
    ?>
    <div class="ui menu">
        <a class="active item" href="./">
            게시판 by Lee.
        </a>
        <a class="item">
            <text data-tooltip="<?= $system_v['login']['id'] ?>" data-position="bottom left">사용자 정보</text>
        </a>
        <a class="item" href="<?= $system_v['login']['authorize_link'] ?>">
            <text><?= $system_v['login']['authorize_phrase'] ?></text>
        </a>
    </div>
    <div class="ui container" style="padding-bottom:40px">
        <div class="ui top attached menu">
            <div class="ui dropdown icon item">
                <i class="align left icon"></i>
                <div class="menu">
                    <a href="./?mode=board" style="color:black">
                        <div class="item">
                            전체 글 보기
                        </div>
                    </a>
                    <a href="./?mode=view_my_posts" style="color:black">
                        <div class="item">
                            내가 쓴 글 보기
                        </div>
                    </a>
                    <a href="./?mode=write_post" style="color:black">
                        <div class="item">
                            글 쓰기
                        </div>
                    </a>
                    <div class="divider"></div>
                    <div class="header">
                        <i class="wrench icon"></i>게시판 정보
                    </div>
                    <a href="./?mode=board&view=notice" style="color:black">
                        <div class="item">
                            공지사항
                        </div>
                    </a>
                    <a href="./?mode=account_settings" style="color:black">
                        <div class="item">
                            계정 설정
                        </div>
                    </a>
                </div>
            </div>
            <div class="right menu">
                <div class="ui right aligned category search item">
                    <div class="ui transparent icon input">
                        <input class="prompt" type="text" id="board_search_box" placeholder="게시물 제목, 내용 검색...">
                        <i class="search link icon" id="board_search_btn"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="ui bottom attached segment" id="segment_data">
            <h1 class="ui center aligned header"><?= $system_v['header_name'] ?></h1>
            <?php
            switch ($system_v['mode']) {
                case "user_info":
                    if ($_SESSION['login']['valid'] == 0) {
                        echo "<div class='ui warning message'>해당 기능(유저 정보 확인)을 사용할 수 없음 : <a href='./?mode=authorize'>로그인</a>해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    $info_data = explode('|', trim($system_v['view']));
                    $sql_statement_prepared = "SELECT a.acc_number,a.acc_id,a.registertime,a.acc_group,(select count(b.comment_number) from lee_board_comment b where b.comment_relatedid = a.acc_number)count_comment, (select count(c.board_number) from lee_board c where c.board_relatedid = a.acc_number)count_user_post from (select acc_number, acc_id, registertime, acc_group from board_account where acc_number=? order by acc_number) a";
                    $sql->prepare($sql_statement_prepared);
                    $sql->bind_param("i", $info_data[0]);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $count_row = $db_result->num_rows;
                    if ($count_row == 0) {
                        echo "<div class='ui error message'>존재하지 않는 유저 정보</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                    } else if ($count_row != 1) {
                        echo "<div class='ui error message'>심각한 오류 : Duplicated Account found [{$count_row}]. 관리자에게 해당 오류를 보고하십시오</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                    } else {
                        $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
                        if ($proc_data['acc_id'] != $info_data[1]) {
                            echo "<div class='ui error message'>존재하지 않는 유저 정보</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        $check_ban = ($proc_data['acc_group'] == 2) ? "<div class='ui error message'>이 계정은 차단되었습니다.</div>" : "";
                        $check_admin = ($proc_data['acc_group'] == 1) ? "<div class='ui success message'>이 계정은 특수 권한을 가지고 있습니다.</div>" : "";
                        echo "<div class='ui info message'><div class='ui header'>유저 ID : {$proc_data['acc_id']}</div><br><li>해당 유저의 가입 시간 : {$proc_data['registertime']}</li><li>해당 유저의 글 쓴 횟수 : {$proc_data['count_user_post']}</li><li>해당 유저의 댓글 쓴 횟수 : {$proc_data['count_comment']}</li></div>{$check_admin}{$check_ban}<a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                    }
                    break;
                case "write_post":
                    if ($_SESSION['login']['valid'] == 0) {
                        echo "<div class='ui warning message'>해당 기능(글 쓰기)을 사용할 수 없음 : <a href='./?mode=authorize'>로그인</a>해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    if ($system_v['view'] == null) {
                        echo "
                    <div class=\"column\">
                    <div class='ui info message'>
                    <div class='ui header'>글 쓰기 전, 유의사항</div><br>
                    <li>(1)타인 또는 특정 단체를 비방하는 글, (2)욕설이 포함된 글, (3)정치, 광고 및 홍보 글을 게재하거나, (4)동일한 게시물을 짧은 기간동안 연속으로 게재한 경우, 이용약관에 따라 차단될 수 있습니다.</li>
                    <li>CSAM(아동 성착취물)이 포함된 파일을 첨부할 경우, 관련 모니터링을 통해 당국 수사기관에 고발합니다.</li>
                    </div>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='post_form'  name='leave_post' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"leave_post\" />
                    <div class=\"field\">
                    <label>글 제목</label>
                        <input type=\"text\" name=\"form_header\" placeholder=\"개행문자 제외 2 ~ 100자리\">
                    </div>
                    <div class='field'>
                    <label>첨부 파일</label>
                    <input type=\"file\" name=\"form_attachment\">
                    </div>
                    <div class='ui info message'>
                    <div class='ui header'>파일 업로드 전 확인 사항</div>
                    파일은 최대 2MB 하나만 첨부할 수 있으며, 허용되는 확장자는 다음과 같습니다.
                    <li>사진 확장자 : .jpg, .png, .gif, .bmp</li>
                    <li>압축 확장자 : .zip</li>
                    </div>
                    <div class=\"field\">
                        <label>본문</label>
                        <textarea name=\"form_contents\" id='leave_post_contents' placeholder='본문을 입력하세요 : 최대 6000자'></textarea>
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_notice\" tabindex=\"0\" class=\"hidden\">
                        <label>본인은 글 쓰기 유의사항을 확인하였습니다</label>
                    </div>
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_post_submit(); return false;'>글 쓰기</div>
                    </div>
                    <div class=\"ui error message\" id='error_message'>
                    </div>
                    </form>
                    <div class=\"ui message\">
                         <div class='ui header'>글을 수정하고 싶다면,</div><br><a href=\"./?mode=view_my_posts\">내가 쓴 글 보기</a>에 들어가서 본인이 수정하고 싶은 글을 클릭 후, 수정 버튼을 누르세요.<br>
                    </div>
                    <a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>
                        </div>
                ";
                    } else {
                        //글 수정 부분
                        $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_contents,a.board_attachment,a.board_revised,a.board_time, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_contents, board_attachment, board_revised, board_time from lee_board where board_number=? order by board_number) a";
                        $sql->prepare($sql_statement_prepared);
                        $sql->bind_param("i", $system_v['view']);
                        $sql->execute();
                        $db_result = $sql->get_result();
                        if ($sql->error) {
                            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                        }
                        $count_row = $db_result->num_rows;
                        if ($count_row == 0) {
                            echo "<div class='ui error message'>존재하지 않는 게시물 번호</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        } else if ($count_row != 1) {
                            echo "<div class='ui error message'>겹치는 게시물 [{$count_row}] : 관리자에게 해당 오류를 보고하십시오</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
                        if ($system_v['login']['number'] != $proc_data['board_relatedid']) {
                            echo "<div class='ui error message'>이 글을 수정할 권한이 없습니다.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        if ($proc_data['board_blind'] == 1) {
                            echo "<div class='ui warning message'>블라인드 처리된 게시물로, 본인이 수정할 수 없습니다.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                            //본인이면, 수정할 수 있는 란이 원래 표기되나, 블라인드 처리된 경우에 한해 수정 불가.
                        }
                        $board_attachment = ($proc_data['board_attachment'] == 0) ? "아니요" : "예";
                        $board_contents = str_replace("&lt;br /&gt;", "", $proc_data['board_contents']);
                        echo "
                    <div class=\"column\">
                    <div class='ui info message'>
                    <div class='ui header'>글 수정 전, 유의사항</div><br>
                    <li>(1)타인 또는 특정 단체를 비방하는 글, (2)욕설이 포함된 글, (3)정치, 광고 및 홍보 글을 게재하거나, (4)동일한 게시물을 짧은 기간동안 연속으로 게재한 경우, 이용약관에 따라 차단될 수 있습니다.</li>
                    <li>CSAM(아동 성착취물)이 포함된 파일을 첨부할 경우, 관련 모니터링을 통해 당국 수사기관에 고발합니다.</li>
                    <li>첨부 파일을 수정하려면, 첨부파일 수정 체크박스를 체킹하고 첨부 파일을 업로드해야 합니다.</li>
                    </div>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='post_form'  name='revise_post' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"revise_post\" />
                    <input type=\"hidden\" name=\"revise_post_number\" value=\"{$system_v['view']}\" />
                    <div class=\"field\">
                    <label>글 제목</label>
                        <input type=\"text\" value='{$proc_data['board_header']}' name=\"form_header\" placeholder=\"개행문자 제외 2 ~ 100자리\">
                    </div>
                    <div class='ui message'>이전에 올린 첨부파일이 존재합니까? : {$board_attachment}</div>
                    <div class='field'>
                    <label>첨부 파일</label>
                    <input type=\"file\" name=\"form_attachment\">
                    </div>
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_file_revise\" tabindex=\"0\" class=\"hidden\">
                        <label>첨부파일 수정</label>
                    </div>
                    <div class='ui info message'>
                    <div class='ui header'>파일 업로드 전 확인 사항</div>
                    파일은 최대 2MB 하나만 첨부할 수 있으며, 허용되는 확장자는 다음과 같습니다.
                    <li>사진 확장자 : .jpg, .png, .gif, .bmp</li>
                    <li>압축 확장자 : .zip</li>
                    </div>
                    <div class=\"field\">
                    <div class=\"field\">
                        <label>본문 (개행 또는 띄어쓰기가 약간 어긋날 수 있음)</label>
                        <textarea name=\"form_contents\" id='leave_post_contents' placeholder='본문을 입력하세요 : 최대 6000자'>
                        {$board_contents}
                        </textarea>
                    </div>
                    <div class='ui checkbox' id='button_post_delete'>
                    <input type=\"checkbox\" name=\"form_post_delete\" tabindex=\"0\" class=\"hidden\">
                    <label style='color:darkred'><b class='ui red label'>글 삭제하기</b></label>
                    </div>
                    <div class='ui inverted message'>
                    <div class='ui header' style='color:red'>삭제하기 버튼을 누르기 전에..</div>
                    글을 삭제하면, 첨부하였던 파일과 더불어 댓글 및 본문 내용을 <b>복구할 수 없습니다!</b><br>
                    단, 삭제하려면 form 제출에 있어서 어떠한 오류도 발현해서는 안됩니다.
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_notice\" tabindex=\"0\" class=\"hidden\">
                        <label>본인은 글 쓰기 유의사항을 확인하였습니다</label>
                    </div>
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_post_submit(); return false;'>글 수정하기</div>
                    </div>
                    <div class=\"ui error message\" id='error_message'>
                    </div>
                    </form>
                    <div class=\"ui message\">             
                         <div class='ui header'>수정 전, 이 글 다시 확인하기</div><br><a href=\"./?mode=view_post&view={$proc_data['board_number']}\" class='ui classic label' onclick='chk_popup_window_is_opened(this.href, 800, 650, \"게시물 보기\"); return false;'>확인하기</a>
                    </div>
                    <a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>
                        </div>
                    ";
                    }
                    break;
                case "account_settings":
                    if ($_SESSION['login']['valid'] == 0) {
                        echo "<div class='ui warning message'>해당 기능(계정 설정)을 사용할 수 없음 : <a href='./?mode=authorize'>로그인</a>해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    $sql_statement_prepared = "SELECT acc_number, acc_id, acc_email from board_account where acc_number = ?";
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
                        echo "<div class='ui error message'>정보를 가져올 수 없음.</div>";
                        break;
                    }
                    //TODO:계정설정
                    echo "
                    <div class=\"ui left aligned aligned grid\">
                    <div class=\"column\">
                    <div class='ui info message'>
                        <div class='ui header'>계정 설정 전, 유의사항</div><br>
                        <li>설정을 적용하기전, 기존 비밀번호를 입력하여야 합니다.</li>
                        <li>항목별 체크박스를 체킹해야 해당 항목이 적용되며, 모든 기기에서 로그아웃 시 이를 수행하고 있는 기기에서도 로그아웃 됩니다.</li>
                        <li>비밀번호 변경 시, 자동으로 모든 기기에서 로그아웃 됩니다.</li>
                    </div>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='authorize_form'  name='board_account_settings' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"account_settings\" />
                    <div class=\"field\">
                    <label><b style='color:red'>*</b>현재 비밀번호 입력</label>
                        <input type=\"password\" name=\"form_pw\" placeholder=\"입력한 비밀번호 재입력\">
                    </div>
                    <div class='ui segment'>
                    <div class=\"field\">
                    <label>바꿀 비밀번호 입력</label>
                        <input type=\"password\" name=\"form_change_pw\" placeholder=\"개행문자 제외, 8 ~ 32자리\">
                    </div>
                    <div class=\"field\">
                    <label>바꿀 비밀번호 재입력</label>
                        <input type=\"password\" name=\"form_change_pw_re\" placeholder=\"바꿀 비밀번호 재입력\">
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_change_pw\" tabindex=\"0\" class=\"hidden\">
                        <label>비밀번호를 변경</label>
                    </div>
                    </div>
                    </div>
                    <div class='ui segment'>
                    <div class='ui message'>현재 계정의 이메일 주소 : {$proc_data['acc_email']}</div>
                    <div class=\"field\">
                    <label>바꿀 이메일</label>
                        <input type=\"text\" name=\"form_email_change\" placeholder=\"허용되는 이메일 도메인 주소만 입력\">
                    </div>
                    <div class=\"ui info message\" id='error_message'>
                    <div class='ui header'>허용되는 이메일 도메인</div>
                    다음 이메일 도메인으로 끝나는 이메일만 입력하실 수 있습니다.
                    <li>gmail.com</li><li>naver.com</li><li>hanmail.com</li><li>icloud.com</li><li>daum.net</li>
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_change_email\" tabindex=\"0\" class=\"hidden\">
                        <label>이메일을 변경</label>
                    </div>
                    </div>
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_all_logout\" tabindex=\"0\" class=\"hidden\">
                        <label>모든 기기에서 로그아웃</label>
                    </div>
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_be_down\" tabindex=\"0\" class=\"hidden\">
                        <label>계정설정을 진행하여 정보가 변경됨에 동의합니다.</label>
                    </div>
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_authorize_submit(); return false;'>해당 설정을 적용</div>
                    </div>
                    <div class=\"ui error message\" id='error_message'>
                    </div>
                    </form>
                        </div>
                    </div>
                    ";
                    break;
                case "search":
                    if ($_SESSION['login']['valid'] == 0) {
                        echo "<div class='ui warning message'>해당 기능(검색)을 사용할 수 없음 : <a href='./?mode=authorize'>로그인</a>해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    } else if ($system_v['view'] == null) {
                        echo "<div class=\"ui divided items\">";
                        echo "<div class=\"ui warning message\"><div class='ui header'>검색 오류</div><br><li>개행 문자, 태그 없이 3 ~ 32자리의 문자열을 입력하였는지 확인하십시오.</li></div>";
                        echo "</div>";
                        break;
                    }
                    echo "<div class=\"ui divided items\">";
                    $param1 = "%{$system_v['view']}%";
                    $sql_statement_prepared = "SELECT count(board_number) from lee_board where (board_header LIKE ? or board_contents LIKE ?) and board_blind='0'";
                    $sql->prepare($sql_statement_prepared);
                    $sql->bind_param("ss", $param1, $param1);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
                    echo "<div class=\"ui info message\"><div class='ui header'>검색 : {$system_v['view']}</div><br><li>총 {$proc_data['count(board_number)']}개의 게시물이 검색되었습니다.</li><li>검색하고자 한 부분이 게시물 내용에 있다면, 해당 부분부터 미리보기로 보실 수 있습니다.</li></div>";
                    //블라인드된 게시물은 검색결과에 노출되지 않음.
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_contents,a.board_time,(select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_contents, board_time from lee_board WHERE (board_header LIKE ? or board_contents LIKE ?) and board_blind='0' order by board_number desc limit {$system_v['page']}, 20) a";
                    $sql->prepare($sql_statement_prepared);
                    $sql->bind_param("ss", $param1, $param1);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $count_row = $db_result->num_rows;
                    $board_blind = "";
                    if ($count_row >= 1) {
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            $board_tag = ($proc_data['board_tag'] == 0) ? "게시판" : (($proc_data['board_tag'] == 1) ? "게시판(고정)" : "공지사항");
                            $tag_href = ($proc_data['board_tag'] == 0) ? "./?mode=board" : (($proc_data['board_tag'] == 1) ? "./?mode=board" : "./?mode=board&view=notice");
                            if ($contents_preview = mb_stristr($proc_data['board_contents'], $system_v['view'])) {
                                $a = array('&lt;br /&gt;', '&lt;', '&gt;', '&quot;', '&#039;', '&amp;');
                                foreach ($a as $v) {
                                    $contents_preview = str_replace($v, " ", $contents_preview);
                                }
                                if (mb_strlen($contents_preview) > 30) {
                                    $contents_preview = mb_substr($contents_preview, 0, 30);
                                } else {
                                    $contents_preview = mb_substr($contents_preview, 0, mb_strlen($contents_preview));
                                }
                                $contents_preview .= "...";
                            } else {
                                $contents_preview = $proc_data['board_contents'];
                                $a = array('&lt;br /&gt;', '&lt;', '&gt;', '&quot;', '&#039;', '&amp;');
                                foreach ($a as $v) {
                                    $contents_preview = str_replace($v, " ", $contents_preview);
                                }
                                if (mb_strlen($contents_preview) > 30) {
                                    $contents_preview = mb_substr($contents_preview, 0, 30);
                                } else {
                                    $contents_preview = mb_substr($contents_preview, 0, mb_strlen($contents_preview));
                                }
                                $contents_preview .= "...";
                            }
                            echo "<div class=\"item\"><div class=\"content\"><a class=\"header\" href='index.php?mode=view_post&view={$proc_data['board_number']}'>{$proc_data['board_header']}</a><div class='meta' style='overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'>미리보기 : {$contents_preview}</div><div class=\"meta\"><span class=\"cinema\">작성자 : <a class=\"ui label\" href='index.php?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a> 게시판 정보 : <a class=\"ui label\" href='{$tag_href}'>{$board_tag}</a> 작성 날짜 : {$proc_data['board_time']}</span></div></div></div>";
                        }
                    }
                    //sql close는 어차피 페이지 끝날 때 GC에 의해 강제되므로, 이정도 프로젝트에는 거의 쓸모없음.
                    $sql->close();
                    echo "<i style='margin-top:20px'>이 페이지는 " . ($system_v['end_page'] / 20) . " 페이지 입니다.</i>";
                    echo "<div class=\"ui circular labels\" style='margin-top:20px'><a class=\"ui label\" href='./?mode=search&value={$system_v['view']}&page=0'>처음으로</a>";
                    if ($system_v['page'] == 0) {
                        echo "<a class=\"ui label disabled\" href='#'>이전 페이지</a>";
                    } else if ($system_v['page'] <= 20) {
                        echo "<a class=\"ui label\" href='./?mode=search&value={$system_v['view']}&page=0'>이전 페이지</a>";
                    } else {
                        $page_before = $system_v['page'] - 20;
                        echo "<a class=\"ui label\" href='./?mode=search&value={$system_v['view']}&page={$page_before}'>이전 페이지</a>";
                    }
                    if ($count_row == 20) {
                        echo "<a class=\"ui label\" href='./?mode=search&value={$system_v['view']}&page={$system_v['end_page']}'>다음 페이지</a>";
                    } else {
                        echo "<a class=\"ui label disabled\" href='#'>다음 페이지</a>";
                    }
                    echo "</div></div>";
                    break;
                case "authorize":
                    if ($system_v['view'] == "signup") {
                        if ($_SESSION['login']['valid'] == 1) {
                            echo "<div class='ui warning message'>해당 기능을 사용할 수 없음 : 이미 로그인 되어 있습니다.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        echo "
                    <div class=\"ui left aligned aligned grid\">
                    <div class=\"column\">
                    <h2 class=\"ui teal image header\">
                    <div class=\"content\">
                    </div>
                    </h2>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='authorize_form'  name='board_register' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"board_register\" />
                    <div class=\"field\">
                    <label>ID</label>
                        <input type=\"text\" name=\"form_id\" placeholder=\"영문, 숫자 4 ~ 20자리\">
                    </div>
                    <div class=\"field\">
                    <label>비밀번호</label>
                        <input type=\"password\" name=\"form_pw\" placeholder=\"개행문자 제외, 8 ~ 32자리\">
                    </div>
                    <div class=\"field\">
                    <label>비밀번호 재입력</label>
                        <input type=\"password\" name=\"form_pw_re\" placeholder=\"입력한 비밀번호 재입력\">
                    </div>
                    <div class=\"field\">
                    <label>이메일</label>
                        <input type=\"text\" name=\"form_email\" placeholder=\"허용되는 이메일 도메인 주소만 입력\">
                    </div>
                    <div class=\"ui info message\" id='error_message'>
                    <div class='ui header'>허용되는 이메일 도메인</div>
                    다음 이메일 도메인으로 끝나는 이메일만 입력하실 수 있습니다.
                    <li>gmail.com</li><li>naver.com</li><li>hanmail.com</li><li>icloud.com</li><li>daum.net</li>
                    </div>
                    <div class=\"field\">
                    <div class=\"ui checkbox\">
                        <input type=\"checkbox\" name=\"form_check_terms\" tabindex=\"0\" class=\"hidden\">
                        <label>게시판의 개인정보처리방침과 약관에 동의합니다</label>
                    </div>
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_authorize_submit(); return false;'>회원 가입</div>
                    </div>
                    <div class=\"ui error message\" id='error_message'>
                    </div>
                    </form>
                    <div class=\"ui message\">
                         이미 계정이 있으십니까? <a href=\"./?mode=authorize\">로그인</a><br>
                    </div>
                        </div>
                    </div>
                    ";
                    } else if ($system_v['view'] == "logout") {
                        if ($_SESSION['login']['valid'] == 0) {
                            echo "<div class='ui warning message'><a href='./?mode=authorize'>로그인</a>을 진행해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        echo "
                    <div class=\"ui left aligned aligned grid\">
                    <div class=\"column\">
                    <h2 class=\"ui teal image header\">
                    <div class=\"content\">
                    </div>
                    </h2>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='authorize_form' name='board_logout' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"board_logout\" />
                    <div class=\"field\">
                    <label>아래 버튼을 눌러 로그아웃</label>
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_authorize_submit(); return false;'>로그아웃</div>
                    </div>
                    <div class=\"ui error message\" id='error_message'>
                    </div>
                    </form>
                        </div>
                    </div>
                    ";
                    } else {
                        if ($_SESSION['login']['valid'] == 1) {
                            echo "<div class='ui warning message'>해당 기능을 사용할 수 없음 : 이미 로그인 되어 있습니다.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                            break;
                        }
                        echo "
                    <div class=\"ui left aligned aligned grid\">
                    <div class=\"column\">
                    <h2 class=\"ui teal image header\">
                    <div class=\"content\">
                    </div>
                    </h2>
                    <div class=\"ui error message\" id='service_error_message' style='display: none'>
                    </div>
                    <form class=\"ui large form\" id='authorize_form' name='board_login' method='post'>
                    <div class=\"ui segment\">
                    <input type=\"hidden\" name=\"token\" value=\"{$system_v['token']}\" />
                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"board_login\" />
                    <div class=\"field\">
                    <label>ID</label>
                        <input type=\"text\" name=\"form_id\" placeholder=\"ID 입력\">
                    </div>
                    <div class=\"field\">
                    <label>비밀번호</label>
                        <input type=\"password\" name=\"form_pw\" placeholder=\"비밀번호 입력\">
                    </div>
                    <div class=\"ui fluid large blue submit button\" id='request_service_bridge_button' onclick='service_authorize_submit(); return false;'>로그인</div>
                    </div>
                    </form>
                    <div class=\"ui message\">
                         계정이 없으십니까? <a href=\"./?mode=authorize&signup\">회원가입</a><br>
                    </div>
                        </div>
                    </div>
                    ";
                    }
                    break;
                case "view_post":
                    if ($system_v['view'] == null) {
                        echo "<div class='ui error message'>잘못된 게시물 번호.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_contents,a.board_attachment,a.board_revised,a.board_time,(select count(b.comment_number) from lee_board_comment b where b.comment_related_number = a.board_number)count_comment, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_contents, board_attachment, board_revised, board_time from lee_board where board_number=? order by board_number) a";
                    $sql->prepare($sql_statement_prepared);
                    $sql->bind_param("i", $system_v['view']);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $count_row = $db_result->num_rows;
                    if ($count_row == 0) {
                        echo "<div class='ui error message'>존재하지 않는 게시물 번호</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    } else if ($count_row != 1) {
                        echo "<div class='ui error message'>겹치는 게시물 [{$count_row}] : 관리자에게 해당 오류를 보고하십시오</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    $proc_data = $db_result->fetch_array(MYSQLI_ASSOC);
                    if ($proc_data['board_blind'] == 1) {
                        echo "<div class='ui warning message'>블라인드 처리된 게시물 입니다.</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                        //본인이면, 수정할 수 있는 란이 원래 표기되나, 블라인드 처리된 경우에 한해 수정 불가.
                    }
                    $is_notice_board = ($proc_data['tag'] == 2) ? 1 : 0;
                    $board_revise_and_delete = "";
                    $board_contents = str_replace("&lt;br /&gt;", "<br />", $proc_data['board_contents']);
                    if ($_SESSION['login']['valid'] == 1 && ($proc_data['board_relatedid'] == $system_v['login']['number'])) {
                        $board_revise_and_delete = "<div style='padding-bottom:5px;float:right'><a href='./?mode=write_post&view={$proc_data['board_number']}' class='ui red label'>수정 / 삭제하기</a></div>";
                    }
                    $board_download_contents = "";
                    //어태치먼트 다운로드를 위한 세션 생성
                    if ($proc_data['board_attachment'] != '0') {
                        $file_name = mb_substr($proc_data['board_attachment'], 40);
                        $gen_token = mk_sess_tkn();
                        $_SESSION['board_attachment_token'] = $gen_token;
                        $board_attachment_token_encode = urlencode(base64_encode($gen_token));
                        //해당 어태치먼트가 있는 게시물 번호
                        $_SESSION['board_attachment_number'] = $proc_data['board_number'];
                        $board_download_contents = "<div style='padding-bottom:5px;float:right' onclick=\"board_attachment_download('{$file_name}', '{$board_attachment_token_encode}'); return false;\"><a class='ui green label'>첨부파일:{$file_name}</a></div>";
                    }
                    echo "
  <h3 class=\"ui left header\" style='margin-bottom:10px'> {$proc_data['board_header']}</h3>
  <div style='margin-bottom:5px'><a href='./?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}' class='ui blue label'>작성자 : {$proc_data['user_name']}</a><a href='#comment' class='ui label' style='float:right'>댓글 : {$proc_data['count_comment']}</a></div>
  <div style='padding-bottom:5px'><span class='ui basic label'>작성 : {$proc_data['board_time']}</span><span class='ui basic label'>수정 : {$proc_data['board_revised']}</span></div>
  {$board_download_contents}
  {$board_revise_and_delete}
  <div class=\"ui clearing divider\" style='margin-bottom:10px'></div>
  <p style=margin-bottom:10px;'>$board_contents</p>
  <div class=\"ui clearing divider\" style='margin-bottom:5px'></div>
  <h4 class='ui left header' id='comment'>댓글 : {$proc_data['count_comment']}개</h4>
                ";
                    if ($proc_data['count_comment'] == 0) {
                        echo "<div class='ui info message'>해당 게시물에 댓글이 없습니다.</div>";
                    } else {
                        echo "<div class=\"ui comments\">";
                        $sql_statement_prepared = "SELECT a.comment_number,a.comment_relatedid,a.comment_related_number,a.comment_blind,a.comment_contents,a.comment_revised,a.comment_time,(select c.acc_id from board_account c where a.comment_relatedid = c.acc_number)user_name from (select comment_number, comment_relatedid, comment_related_number, comment_blind, comment_contents, comment_revised, comment_time from lee_board_comment where comment_related_number=? order by comment_number asc limit {$system_v['page']}, 20) a";
                        $sql->prepare($sql_statement_prepared);
                        $sql->bind_param("i", $system_v['view']);
                        $sql->execute();
                        $db_result = $sql->get_result();
                        if ($sql->error) {
                            error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                        }
                        $count_row = $db_result->num_rows;
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            if ($proc_data['comment_blind'] == 1) {
                                echo "<div class='ui warning message'>블라인드 처리된 댓글 입니다.</div>";
                            } else {
                                $comment_revise_btn = "";
                                $comment_revise = "";
                                if ($proc_data['comment_relatedid'] == $system_v['login']['number']) {
                                    $comment_revise_btn = "<a class='ui red label' onclick='service_comment_revise({$proc_data['comment_number']});'>수정 / 삭제하기</a>";
                                    $comment_data = str_replace("&lt;br /&gt;", "", $proc_data['comment_contents']);
                                    $comment_revise = "
                                <div class='content' id='revise_c_{$proc_data['comment_number']}' style='display:none; margin-top:20px'>
                                    <form class=\"ui form\" id='revise_c_form_{$proc_data['comment_number']}'>
                                    <input type=\"hidden\" name=\"service_bridge_mode\" value=\"revise_comment\" />
                                    <input type=\"hidden\" name=\"comment_number\" value=\"{$proc_data['comment_number']}\" />
                                    <div class=\"field\">
                                        <label>댓글 수정</label>
                                        <div class=\"ui error message\" id='service_error_message_revise_comment_{$proc_data['comment_number']}' style='display: none'>
                                        </div>
                                        <textarea rows=\"4\" name='form_contents' placeholder='[1]타인 또는 단체를 비방하거나, [2]광고, 홍보성 목적이 짙거나, [3]욕설 및 혐오를 조장하는 댓글은 제재될 수 있습니다.'>{$comment_data}</textarea>
                                    </div>
                                    <div class='ui checkbox' style='margin-top:10px'>
                                        <input type=\"checkbox\" name=\"form_comment_delete\" tabindex=\"0\" class=\"hidden\">
                                        <label><b class='ui orange label'>댓글 삭제하기</b></label>
                                    </div>
                                <span class=\"ui fluid large blue submit button\" id='request_service_bridge_button' style='margin-top:20px' onclick='service_revise_comment_submit({$proc_data['comment_number']}); return false;'>댓글 수정하기</span>
                                </form>
                                </div>
                                ";
                                }
                                $comment_date = ($proc_data['comment_time'] == $proc_data['comment_revised']) ? "작성 : " . $proc_data['comment_time'] : "수정됨 : " . $proc_data['comment_revised'];
                                $comment_contents = str_replace("&lt;br /&gt;", "<br />", $proc_data['comment_contents']);
                                echo "
                                    <div class=\"comment\">
                                        <div class=\"content\" id='c_{$proc_data['comment_number']}'>
                                            <a class=\"author\" href='./?mode=user_info&view={$proc_data['comment_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a>
                                            <div class=\"metadata\">
                                            <span class=\"date\">{$comment_date}</span>
                                            </div>
                                            {$comment_revise_btn}
                                            <div class=\"text\">
                                                {$comment_contents}
                                            </div>
                                        </div>
                                        {$comment_revise}
                                    </div>
                                    <div class=\"ui clearing divider\" style='margin-bottom:5px'></div>
                        ";
                            }
                        }
                        $sql->close();
                        echo "</div>";
                        echo "<i style='margin-top:20px'>이 페이지는 " . ($system_v['end_page'] / 20) . " 페이지 입니다.</i>";
                        echo "<div class=\"ui circular labels\" style='margin-top:20px'><a class=\"ui label\" href='./?mode=view_post&view={$system_v['view']}&page=0#comment'>처음으로</a>";
                        if ($system_v['page'] == 0) {
                            echo "<a class=\"ui label disabled\" href='#'>이전 댓글</a>";
                        } else if ($system_v['page'] <= 20) {
                            echo "<a class=\"ui label\" href='./?mode=view_post&view={$system_v['view']}&page=0#comment'>이전 댓글</a>";
                        } else {
                            $page_before = $system_v['page'] - 20;
                            echo "<a class=\"ui label\" href='./?mode=view_post&view={$system_v['view']}&page={$page_before}#comment'>이전 댓글</a>";
                        }
                        if ($count_row == 20) {
                            echo "<a class=\"ui label\" href='./?mode=view_post&view={$system_v['view']}&page={$system_v['end_page']}#comment'>다음 댓글</a>";
                        } else {
                            echo "<a class=\"ui label disabled\" href='#'>다음 댓글</a>";
                        }
                        echo "</div></div>";
                    }
                    if ($system_v['login']['valid'] == 1 && $is_notice_board == 0) {
                        echo "<div class=\"ui comments\">";
                        echo "
                    <form class=\"ui form\" id='leave_comment_form'>
                    <!--댓글 부분은 수정 및 게재 이렇게 2개라, csrf 토큰을 meta tag에 있는 것으로 대체-->
                        <input type=\"hidden\" name=\"service_bridge_mode\" value=\"leave_comment\" />
                        <input type=\"hidden\" name=\"post_number\" value=\"{$system_v['view']}\" />
                        <div class=\"field\">
                        <label>댓글 작성</label>
                        <div class=\"ui error message\" id='service_error_message_leave_comment' style='display: none'>
                        </div>
                        <textarea rows=\"4\" name='form_contents' placeholder='[1]타인 또는 단체를 비방하거나, [2]광고, 홍보성 목적이 짙거나, [3]욕설 및 혐오를 조장하는 댓글은 제재될 수 있습니다.'></textarea>
                        <span class=\"ui fluid large blue submit button\" id='request_service_bridge_button' style='margin-top:20px' onclick='service_comment_submit(); return false;'>댓글 달기</span>
                    </form>
                    ";
                        echo "</div>";
                    } else if ($is_notice_board == 1) {
                        echo "<i>공지사항에는 댓글을 달 수 없습니다.</i>";
                    } else {
                        echo "<i>댓글을 달기 위해서는 <a href='./?mode=authorize'>로그인</a> 해야합니다.</i>";
                    }
                    //TODO : 댓글 작성
                    break;
                case "view_my_posts":
                    echo "<div class=\"ui divided items\">";
                    if ($_SESSION['login']['valid'] == 0) {
                        echo "<div class='ui warning message'>해당 기능(내가 쓴 글 보기)을 사용할 수 없음 : <a href='./?mode=authorize'>로그인</a>해주세요</div><a class=\"ui label\" onclick='history.back();'>이전 페이지로</a>";
                        break;
                    }
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_attachment,a.board_revised,a.board_time,(select count(b.comment_number) from lee_board_comment b where b.comment_related_number = a.board_number)count_comment, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_attachment, board_revised, board_time from lee_board where board_relatedid=? order by board_number desc limit {$system_v['page']}, 20) a";
                    $sql->prepare($sql_statement_prepared);
                    $sql->bind_param("i", $system_v['login']['number']);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $count_row = $db_result->num_rows;
                    $board_blind = "";
                    if ($count_row >= 1) {
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            $board_blind = ($proc_data['board_blind'] == 1) ? "<i class='lock icon'></i>" : "";
                            echo "<div class=\"item\"><div class=\"content\"><a class=\"header\" href='index.php?mode=view_post&view={$proc_data['board_number']}'>{$board_blind}{$proc_data['board_header']}</a><div class=\"meta\"><span class=\"cinema\">작성자 : <a class=\"ui label\" href='index.php?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a> 댓글 수 : <a class=\"ui label\">{$proc_data['count_comment']}</a> 작성 날짜 : {$proc_data['board_time']}</span></div></div></div>";
                        }
                    } else {
                        echo "<div class='ui info message'>사용자가 올린 게시물이 존재하지 않습니다. 첫 글을 작성해보세요!</div>";
                    }
                    //sql close는 어차피 페이지 끝날 때 GC에 의해 강제되므로, 이정도 프로젝트에는 거의 쓸모없음.
                    $sql->close();
                    echo "<i style='margin-top:20px'>이 페이지는 " . ($system_v['end_page'] / 20) . " 페이지 입니다.</i>";
                    echo "<div class=\"ui circular labels\" style='margin-top:20px'><a class=\"ui label\" href='./?mode=view_my_posts&page=0'>처음으로</a>";
                    if ($system_v['page'] == 0) {
                        echo "<a class=\"ui label disabled\" href='#'>이전 페이지</a>";
                    } else if ($system_v['page'] <= 20) {
                        echo "<a class=\"ui label\" href='./?mode=view_my_posts&page=0'>이전 페이지</a>";
                    } else {
                        $page_before = $system_v['page'] - 20;
                        echo "<a class=\"ui label\" href='./?mode=view_my_posts&page={$page_before}'>이전 페이지</a>";
                    }
                    if ($count_row == 20) {
                        echo "<a class=\"ui label\" href='./?mode=view_my_posts&page={$system_v['end_page']}'>다음 페이지</a>";
                    } else {
                        echo "<a class=\"ui label disabled\" href='#'>다음 페이지</a>";
                    }
                    echo "</div></div>";
                    break;
                case "board":
                    echo "<div class=\"ui divided items\">";
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_attachment,a.board_revised,a.board_time,(select count(b.comment_number) from lee_board_comment b where b.comment_related_number = a.board_number)count_comment, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_attachment, board_revised, board_time from lee_board order by board_number desc limit 0, 10) a where a.board_blind='0' and a.board_tag='1'";
                    $sql->prepare($sql_statement_prepared);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음. 0");
                    }
                    $count_row = $db_result->num_rows;
                    if ($count_row >= 1) {
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            echo "<div class=\"item\"><div class=\"content\"><a class=\"header\" href='index.php?mode=view_post&view={$proc_data['board_number']}' data-tooltip=\"고정 게시물\" data-position=\"bottom left\"><i class=\"map pin icon\"></i>{$proc_data['board_header']}</a><div class=\"meta\"><span class=\"cinema\">작성자 : <a class=\"ui label\" href='index.php?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a> 댓글 수 : <a class=\"ui label\">{$proc_data['count_comment']}</a> 작성 날짜 : {$proc_data['board_time']}</span></div></div></div>";
                        }
                    } else {
                        echo "<div class='ui info message'>이 게시판에 고정 게시물이 존재하지 않습니다.</div>";
                    }
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_attachment,a.board_revised,a.board_time,(select count(b.comment_number) from lee_board_comment b where b.comment_related_number = a.board_number)count_comment, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_attachment, board_revised, board_time from lee_board where board_blind='0' and board_tag='0' order by board_number desc limit {$system_v['page']}, 20) a";
                    $sql->prepare($sql_statement_prepared);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음. 1");
                    }
                    $count_row = $db_result->num_rows;
                    if ($count_row >= 1) {
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            echo "<div class=\"item\"><div class=\"content\"><a class=\"header\" href='index.php?mode=view_post&view={$proc_data['board_number']}'>{$proc_data['board_header']}</a><div class=\"meta\"><span class=\"cinema\">작성자 : <a class=\"ui label\" href='index.php?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a> 댓글 수 : <a class=\"ui label\">{$proc_data['count_comment']}</a> 작성 날짜 : {$proc_data['board_time']}</span></div></div></div>";
                        }
                    } else {
                        if ($system_v['page'] == 0) {
                            if ($system_v['login']['valid'] == 1) {
                                echo "<div class='ui info message'>게시물이 하나도 없습니다. 첫 글을 작성해보세요!</div>";
                            } else {
                                echo "<div class='ui info message'>게시물이 하나도 없습니다. 로그인 하여, 첫 글을 작성해보세요!</div>";
                            }
                        } else {
                            echo "<div class='ui info message'>{$system_v['page']}에서 {$system_v['end_page']}까지 조회했을 때, 존재하는 게시물이 없습니다.</div>";
                        }
                    }
                    $sql->close();
                    //초 간단 페이지네이션 파트
                    echo "<i style='margin-top:20px'>이 페이지는 " . ($system_v['end_page'] / 20) . " 페이지 입니다.</i>";
                    echo "<div class=\"ui circular labels\" style='margin-top:20px'><a class=\"ui label\" href='./?mode=board&page=0'>처음으로</a>";
                    if ($system_v['page'] == 0) {
                        echo "<a class=\"ui label disabled\" href='#'>이전 페이지</a>";
                    } else if ($system_v['page'] <= 20) {
                        echo "<a class=\"ui label\" href='./?mode=board&page=0'>이전 페이지</a>";
                    } else {
                        $page_before = $system_v['page'] - 20;
                        echo "<a class=\"ui label\" href='./?mode=board&page={$page_before}'>이전 페이지</a>";
                    }
                    if ($count_row == 20) {
                        echo "<a class=\"ui label\" href='./?mode=board&page={$system_v['end_page']}'>다음 페이지</a>";
                    } else {
                        echo "<a class=\"ui label disabled\" href='#'>다음 페이지</a>";
                    }
                    echo "</div></div>";
                    break;
                case "notice":
                    echo "<div class=\"ui divided items\">";
                    $sql_statement_prepared = "SELECT a.board_number,a.board_relatedid,a.board_tag,a.board_blind,a.board_header,a.board_attachment,a.board_revised,a.board_time, (select count(b.comment_number) from lee_board_comment b where b.comment_related_number = a.board_number)count_comment, (select c.acc_id from board_account c where a.board_relatedid = c.acc_number)user_name from (select board_number, board_relatedid, board_tag, board_blind, board_header, board_attachment, board_revised, board_time from lee_board order by board_number desc limit {$system_v['page']}, 20) a where a.board_tag='2' and a.board_blind='0'";
                    $sql->prepare($sql_statement_prepared);
                    $sql->execute();
                    $db_result = $sql->get_result();
                    if ($sql->error) {
                        error_exception(2, "데이터베이스 쿼리를 처리할 수 없음.");
                    }
                    $count_row = $db_result->num_rows;
                    if ($count_row >= 1) {
                        while ($proc_data = $db_result->fetch_array(MYSQLI_ASSOC)) {
                            echo "<div class=\"item\"><div class=\"content\"><a class=\"header\" href='index.php?mode=view_post&view={$proc_data['board_number']}'>{$proc_data['board_header']}</a><div class=\"meta\"><span class=\"cinema\">작성자 : <a class=\"ui label\" href='index.php?mode=user_info&view={$proc_data['board_relatedid']}|{$proc_data['user_name']}'>{$proc_data['user_name']}</a> 댓글 수 : <a class=\"ui label\">{$proc_data['count_comment']}</a> 작성 날짜 : {$proc_data['board_time']}</span></div></div></div>";
                        }
                    } else {
                        echo "<div class='ui info message'>관리자가 작성한 공지가 존재하지 않습니다. 좋은 일 입니다!</div>";
                    }
                    $sql->close();
                    //초 간단 페이지네이션 파트
                    echo "<i style='margin-top:20px'>이 페이지는 " . ($system_v['end_page'] / 20) . " 페이지 입니다.</i>";
                    echo "<div class=\"ui circular labels\" style='margin-top:20px'><a class=\"ui label\" href='./?mode=board&view=notice&page=0'>처음으로</a>";
                    if ($system_v['page'] == 0) {
                        echo "<a class=\"ui label disabled\" href='#'>이전 페이지</a>";
                    } else if ($system_v['page'] <= 20) {
                        echo "<a class=\"ui label\" href='./?mode=board&view=notice&page=0'>이전 페이지</a>";
                    } else {
                        $page_before = $system_v['page'] - 20;
                        echo "<a class=\"ui label\" href='./?mode=board&view=notice&page={$page_before}'>이전 페이지</a>";
                    }
                    if ($count_row == 20) {
                        echo "<a class=\"ui label\" href='./?mode=board&view=notice&page={$system_v['end_page']}'>다음 페이지</a>";
                    } else {
                        echo "<a class=\"ui label disabled\" href='#'>다음 페이지</a>";
                    }
                    echo "</div></div>";
                    break;
                default:
                    echo "<div class='ui error message'>시스템 오류 : system is halted.</div>";
            }
            ?>
        </div>
    </div>
    <div class="ui vertical footer segment">
        <div class="ui container">
            <div class="ui stackable divided equal height stackable grid">
                <div class="three wide column">
                    <h4 class="ui header">중요 고지</h4>
                    <div class="ui link list">
                        <a href="#" class="item qst-inverted-text">개인정보 처리방침</a>
                        <a href="#" class="item qst-inverted-text" style="color:">이용 약관</a>
                    </div>
                </div>
                <div class="seven wide column">
                    <p style="text-decoration:underline;color:black">(C) 2023 all rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>