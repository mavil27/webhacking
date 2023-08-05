<?php
if(main_index_files != 1024){
    echo "503 : Direct Access is Denied!";
    exit;
}

$sql_conn = new mysqli("localhost", "Mavil27", "Thdbf014@@", "board_lee");
$sql_conn->set_charset("utf8");
$sql = $sql_conn->stmt_init();
if(mysqli_connect_errno()) {
    error_exception(2, "데이터베이스 서버로 접속할 수 없음.");
}

function get_user_ip(){
    if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])){
        $is_proxy_service_cf = 1;
        $ip_addr = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }else{
        $is_proxy_service_cf = 0;
        if (getenv('HTTP_CLIENT_IP')) {
            $e = getenv('HTTP_CLIENT_IP');
        } else if(getenv('HTTP_X_FORWARDED_FOR')) {
            $e = getenv('HTTP_X_FORWARDED_FOR');
        } else if(getenv('HTTP_X_FORWARDED')) {
            $e = getenv('HTTP_X_FORWARDED');
        } else if(getenv('HTTP_FORWARDED_FOR')) {
            $e = getenv('HTTP_FORWARDED_FOR');
        } else if(getenv('HTTP_FORWARDED')) {
            $e = getenv('HTTP_FORWARDED');
        } else if(getenv('REMOTE_ADDR')) {
            $e = getenv('REMOTE_ADDR');
        } else {
            $e = '0';
        }
        $ip_addr = $e;
    }
    return array($ip_addr, $is_proxy_service_cf);
}

function is_valid_sess_tkn(){
    //토큰 엑세스 검사 과정이 필요한 경우를 위하여
    if(isset($_SESSION['login']['valid']) && $_SESSION['login']['valid'] == 1) {
        if(isset($_GET['secure_token']) && isset($_POST['secure_token'])){
            return false;
        }else if(isset($_GET['secure_token']) && isset($_SESSION['secure_token'])){
            if($_GET['secure_token'] != $_SESSION['secure_token']){
                return false;
            }else{
                return true;
            }
        }else if(isset($_POST['secure_token']) && isset($_SESSION['secure_token'])){
            if($_POST['secure_token'] != $_SESSION['secure_token']){
                return false;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }
    return false;
}

function bad_words_check($data){
    //비속어 정규식 updated on 23 07 30. 일치하는 항목이 있을 경우, 비속어.
    $regex = "/^.*(씨([ \n이ㅣ]*|)발|시([ \n이ㅣ]*|)발|엠([ \n]*|)창|LGBT|농([ \n]*|)ㅋ|섹([ \n]*|)스|싶할|씹([ \n]*|)할|십([ \n]*|)할|할([ \n]*|)카([ \n]*|)스|HALKAS|벼([ \n어엉ㅕㅓㅇ]*|)(ㅅ|시|신)|병([ \nㅇ]*|)신|븅([ \n]*|)신|ㅄ|ㅂ([ \n]*|)ㅅ|f([ \n]*|)u([ u\n]*|)c([ \n]*|)k|s([ \n]*|)e([ e\n]*|)x|디(?=씨|시|시인사이드|씨인사이드)|DC(?!([ \n]*|)코믹스|([ \n]*|)Comic)|신안| 라도|라도 |절라도|노무현|무현|응디|흔(?=들어라|드르라|드러라)|미([ \n]*|)(친|틴|췬)|호로([ \n]*|)(새|자|쉑)|이기야|쌍도|똥푸산|푸산|쓰까|게이|레즈|로([ \n]*|)리|후타나리|오토코노코|쇼타|거유|빈유|미유|맘마통|우유통|보([ \n]*|)지|갈([ \n]*|)보|자([ \n]*|)지|쥬([ \n]*|)지|뷰([ \n]*|)지|ㅈㄴ|시잇팔|싯([ \n]*|)팔|(씹|십)([ \n]*|)(?=새|련|년)|좆([ \n]*|)(?=됐|됬|됨|됌|같|집|소|대|되|간|나)|이대(?=남|녀)|ㅈ([ \n]*|)(?=같|간|됨|됌|됐|됬|집|되|소|대|나)|입갤|파딱|주딱|예아|안될거(?= 뭐 있노|뭐있노| 뭐있노)|딱(?= 좋노|좋노|좋다| 좋다)|지([ \n]*|)잡|설([ \n]*|)잡|슴가|근첩|일베|페미|폐미|꼴([ \n]*|)(?=린|림|려|페)|틀([ \n]*|)(?=니앙|리앙|베)|근하|존나|좆나|봊나|느([ \n]*|)(그|금)([ \n]*|)(애|마)|한([ \n]*|)(?=남|녀)|dcinside|ilbe|clien\.|hitomi\.|포르노|퍼리|도끼([ \n]*|)(?= 자국|자국)|개([ \n]*|)(?=보지|자지|새(?=꺄|끼|야)|쌍도|년|뇬|놈|노무)|썅([ \n]*|)(?=년|놈)|상([ \n]*|)(?=놈아|년아)|쌍(?=년|놈)|틀([ \n]*|)(?=니 냄새|니냄새|내|딱)|문([ \n]*|)(?=죄인|재앙|크예거|병신|통|재인|제인)|노([ \n]*|)(?=쨩|짱|괴)|(부끄럽|좋|됐|됬|렸|었|있)노|그립읍니다|홍어(?= 새끼|새끼| )|통([ \n]*|)(?=구이|통따리)|머구|팡주|폭동이야|슨상님|이태원([ \n]*|)(?=쥐포|압사|육포)|짓털|지털|쑤컹|자위|클리|쪼([ \n]*|)(?=임|인|여)|응기잇|오고곡|%기잇|%71|헤으|(오래된|오래된 )생각(?= 이다|이다)|어린이(?=사랑| 사랑)|강([ \n]*|)간|Rape|레이프|인육|페([ \n]*|)도|pedo(?=pelia|)|creampie|p([ \n]*|)o([ \n]*|)r([ \n]*|)n|비아그라|시알리스|viagra|최음|마약|코카인|자궁|거근|육봉|정병|피([ \n]*|)(?=떡|냄|싸)|([-*^$= (){}[\]])P([-*^$= (){}[\]])|허버|드릉([ \n]*|)드릉|(찐|은|왕)([ \n]*|)따|농후|조([ \n]*|)(?=센|선([ \n]*|)(?=놈|년|새끼|징))|정신([ \n]*|)병|따([ \n]*|)(?=먹|묵)|창([ \n]*|)(?=부|녀|여|남)|오([ \n]*|)피([ \n]*|)(?!스|지지))(.*)+$/sim";
    if(preg_match_all($regex, $data, $match)){
        return array(true, $match[1][0]);
    }else{
        return array(false, "nodata");
    }
}

function ext_extract($filename){
    if(strlen($filename) > 255)
    {
        return false;
    }
    $basename = trim(basename($filename));
    $resource = explode(".", $basename);
    $i = count($resource)-1;
    $resource[$i] = trim($resource[$i]);
    if($resource[$i] === "")
    {
        while($i > 0)
        {
            $i--;
            $resource[$i] = trim($resource[$i]);
            if(!empty($resource[$i]))
            {
                return strtolower($resource[$i]);
            }
        }
        return false;
    }
    elseif(!empty($resource[$i]))
    {
        return strtolower($resource[$i]);
    }
    else
    {
        return false;
    }
}

function chk_image_file($filename)
{
    $isimage = null;
    $chk = 'true';
    $imginfo = @getimagesize($filename);
    switch( $imginfo['mime'] )
    {
        case 'image/gif':
            if(!$isimage = @imagecreatefromgif($filename))
                $chk = 'invalid gif';
            break;

        case 'image/jpeg':
            if(!$isimage = @imagecreatefromjpeg($filename))
                $chk = 'invalid jpg';
            break;

        case 'image/png':
            if(!$isimage = @imagecreatefrompng($filename))
                $chk = 'invalid png';
            break;

        case 'image/bmp':
            if(!$isimage = @imagecreatefromwbmp($filename))
                $chk = 'invalid bmp';
            break;
        default:
            $chk = "invalid image";
    }
    return $chk;
}

function mk_sess_tkn(){
    return hash("sha256", mt_rand(1, 1000000000).get_user_ip().'lee_board_09z4nxt845m10i8dr'.time());
}

function error_exception($error_level, $statement)
{
    if($error_level != 2){
        switch($error_level){
            case 0:
                error_log("Alert : $statement [time : ".time()."]");
                break;
            case 1:
                error_log("Warning : $statement [time : ".time()."]");
                set_error_handler("Warning : $statement [time : ".time()."]", E_USER_WARNING);
                exit;
            default:
                break;
        }
    }else{
        error_log("Error : $statement [time : ".time()."]");
        set_error_handler("Error : $statement [time : ".time()."]", E_USER_ERROR);
        exit;
    }
}
