console.log("%c잠깐만요!\n혹시 다른 사람이 여기에 어떤 텍스트나 문구를 붙여넣으라고 했다면, 절대로 하지 마세요.","background:black; color:white");
$('.ui.top.menu .ui.dropdown')
    .dropdown({
        clearable: true
    })
;
$('.ui.checkbox')
    .checkbox()
;
let Popup_Window_History = null;
function chk_popup_window_is_opened(link, width, height, title) {
    let specs = "width=" + width;
    specs += ",height=" + height;
    // 팝업창 열려 있는지 확인
    if(Popup_Window_History == null) {
        Popup_Window_History = window.open(link, title, specs);
    } else {
        if(Popup_Window_History.closed) {
            Popup_Window_History = window.open(link, title, specs);
        }else{
            Popup_Window_History.alert("이미 열려있는 팝업 창이 존재합니다.\n창을 닫고 해당 작업을 재실행 해주세요.");
        }
    }
    Popup_Window_History.focus();
}
let search_box_data = document.getElementById("board_search_box");
search_box_data.addEventListener("keypress", function(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        document.getElementById("board_search_btn").click();
    }
});
$('#board_search_btn').click(function() {
    let regex = /^([^<>\n\t\r\f]){3,32}$/;
    if(regex.test(search_box_data.value)) {
        location.href = "./?mode=search&value="+search_box_data.value;
    }else{
        alert("개행, 태그 문자없이 3 ~ 32자리 검색 값을 입력해주세요");
        return false;
    }
});
let service_bridge_object_name = {
    "board_login":["token", "form_id", "form_pw"],
    "board_logout":["token"],
    "board_register":["token", "form_id", "form_pw", "form_email", "form_check_terms"]
};
let message_contents = {
    "error": {"connection_error" : "[오류] 데이터 전송 중에 네트워크 문제가 발생했습니다.", "specialized_error" : "[오류] ", "unknown_error" : "[오류] 알 수 없는 오류가 발생했습니다. 해당 요청을 다시 서버로 전송하거나, 페이지를 새로고침 하십시오"},
    "warning": {"no_answer" : "[경고] 해당 요청을 통해 서버로 부터 받은 응답이 없습니다.", "specialized_answer" : "[경고] ", "no_request_token_answer" : "[경고] 보안 토큰이 만료되었습니다. 해당 페이지에서 데이터를 재요청하려면 새로고침 하십시오."},
    "info": {"no_data" : "데이터가 없습니다.", "specialized_answer" : "[알림] "}
}

function xhr_data_process(data, $section) {
    $section.empty();
    if(typeof(data.goahead) == "undefined" || data.goahead !== 1) {
        $section.show();
        $section.append(message_contents.warning.no_request_token_answer + "<br>");
    }
    if(typeof(data.error) !== "undefined"){
        $section.show();
        if(typeof(data.description) == "undefined"){
            $section.append(message_contents.error.unknown_error);
        }else{
            $section.append(message_contents.error.specialized_error + data.description);
        }
        return false;
    }
    if(typeof(data.success) !== "undefined"){
        $section.hide();
        if(typeof(data.redirect) !== "undefined"){
            if(typeof(data.notice) !== "undefined"){
                alert(data.description);
            }
            location.href = data.redirect;
        }else{
            if(typeof(data.notice) !== "undefined"){
                alert(data.description);
            }
            location.href = "./?mode=board";
        }
        return false;
    }
}
function service_authorize_submit(){
    let e = $("#authorize_form").serialize();
    let $section = $("#service_error_message");
    $.ajax({
        cache : false,
        url : "./service_bridge.php",
        type : 'POST',
        dataType: 'json',
        async: 'false',
        data : e,
        success : function (data) {
            xhr_data_process(data, $section);
            return false;
        },
        error : function() {
            $section.empty();
            $section.show();
            $section.append(message_contents.error.connection_error);
            return false;
        }
    });
}
let button_post_delete = 1;
$('#button_post_delete').click(function() {
    if(button_post_delete === 1){
        alert("글 삭제하기 버튼을 누르셨습니다.");
        let $section = $("#request_service_bridge_button");
        $section.empty();
        $section.append("글 삭제하기");
        button_post_delete = 0;
    }else{
        let $section = $("#request_service_bridge_button");
        $section.empty();
        $section.append("글 수정하기");
        button_post_delete = 1;
    }
});
function service_comment_revise(n){
    $("#revise_c_"+n).toggle();
}
function service_revise_comment_submit(n){
    let form = $('#revise_c_form_'+n)[0];
    let $section = $("#service_error_message_revise_comment_"+n);
    let e = new FormData(form);
    let v = $( 'meta[name=csrf-token]' ).attr( 'content' );
    e.append('token', v);
    $.ajax({
        cache : false,
        url : "./service_bridge.php",
        type : 'POST',
        dataType: 'json',
        async: 'false',
        data : e,
        contentType : false,
        processData : false,
        success : function (data) {
            xhr_data_process(data, $section);
            return false;
        },
        error : function() {
            $section.empty();
            $section.show();
            $section.append(message_contents.error.connection_error);
            return false;
        }
    });
}
function service_post_submit(){
    let form = $('#post_form')[0];
    let $section = $("#service_error_message");
    let e = new FormData(form);
    $.ajax({
        cache : false,
        url : "./service_bridge.php",
        type : 'POST',
        dataType: 'json',
        async: 'false',
        data : e,
        contentType : false,
        processData : false,
        success : function(data) {
            xhr_data_process(data, $section);
            return false;
        },
        error : function() {
            $section.empty();
            $section.show();
            $section.append(message_contents.error.connection_error);
            return false;
        }
    });
}
function service_comment_submit(){
    let form = $('#leave_comment_form')[0];
    let $section = $("#service_error_message_leave_comment");
    let e = new FormData(form);
    let v = $( 'meta[name=csrf-token]' ).attr( 'content' );
    e.append('token', v);
    $.ajax({
        cache : false,
        url : "./service_bridge.php",
        type : 'POST',
        dataType: 'json',
        async: 'false',
        data : e,
        contentType : false,
        processData : false,
        success : function (data) {
            xhr_data_process(data, $section);
            return false;
        },
        error : function() {
            $section.empty();
            $section.show();
            $section.append(message_contents.error.connection_error);
            return false;
        }
    });
}
function board_attachment_download(filename,tkn){
    $.ajax({
        cache : false,
        url : "./board_attachment_download.php?file="+filename+"&access_token="+tkn,
        type : 'POST',
        dataType: 'json',
        data : {"request_mode" : "check_param_valid"},
        success : function(data) {
            if(typeof(data.error) !== "undefined"){
                if(typeof(data.notice) !== "undefined"){
                    alert(data.description);
                }else{
                    alert("첨부파일 다운로드가 유효하지 않습니다. 페이지를 새로고침 해보세요.");
                }
                return false;
            }
            $.ajax({
                url:"./board_attachment_download.php?file="+filename+"&access_token="+tkn,
                cache : false,
                xhrFields:{
                    responseType: 'blob'
                },
                type : 'POST',
                data : {"request_mode" : "get_attachment"},
                success: function(data){
                    const blob = new Blob([data], {type: 'application/octet-stream'})
                    let downloadLink = document.createElement("a");
                    downloadLink.href = URL.createObjectURL(blob);
                    downloadLink.download = filename;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                    window.URL.revokeObjectURL(downloadLink.href);
                },
                error:function(){
                    alert(message_contents.error.connection_error);
                    return false;
                }
            });
        },
        error : function() {
            alert(message_contents.error.connection_error);
            return false;
        }
    });
}
