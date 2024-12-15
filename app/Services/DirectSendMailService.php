<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class DirectSendMailService
{
    private $username;
    private $key;
    private $returnUrl;
    private $footerSort;

    public function __construct($footerSort = 'LEFT')
    {
        $this->username = "masangsoft"; //필수입력  directsend 발급 ID
        $this->key = "YJ2iNf20PwJSDjJ"; //필수입력  directsend 발급 api key
        $this->returnUrl = 7; // 실제 발송성공실패 여부를 받기 원하실 경우 아래 주석을 해제하신 후, 사이트에 등록한 URL 번호를 입력해주시기 바랍니다.
        $this->footerSort = $footerSort; //메일내용, 풋터(수신옵션) 정렬 LEFT - 왼쪽 정렬 / CENTER - 가운데 정렬 / RIGHT - 오른쪽 정렬
    }

    public function sendMail(array $params): array
    {
        $validator = Validator::make($params, [
            'toName' => 'required',
            'toMail' => 'required|email',
            'fromName' => 'required',
            'fromMail' => 'required|email',
            'subject' => 'required',
            'content' => 'required',
        ]);
        if ($validator->fails()) {
            return ['RETURN' => 'ERR'];
        }
        $aValidated = $validator->validated();
        $toName = $aValidated['toName'];
        $toEmail = $aValidated['toMail'];
        $fromName = $aValidated['fromName'];
        $fromEmail = $aValidated['fromMail'];
        $subject = $aValidated['subject'];
        $message = $aValidated['content'];
        $receiver = '{"name":"' . $toName . '", "email":"' . $toEmail . '", "mobile":"", "note1":"", "note2":"", "note3":"", "note4":"", "note5":""}';
        $receiver = '[' . $receiver . ']'; // JSON 데이터

        $message = urlencode($message);

        $postvars = '"subject":"' . $subject . '"';
        $postvars = $postvars . ', "body":"' . $message . '"';
        $postvars = $postvars . ', "sender":"' . $fromEmail . '"';
        $postvars = $postvars . ', "sender_name":"' . $fromName . '"';
        $postvars = $postvars . ', "username":"' . $this->username . '"';
        $postvars = $postvars . ', "receiver":' . $receiver;
        $postvars = $postvars . ', "return_url_yn":' . true;
        $postvars = $postvars . ', "return_url":"' . $this->returnUrl . '"';
        $postvars = $postvars . ', "footer_sort":"' . $this->footerSort . '"'; // 메일 내용, 풋터(수신옵션) 정렬
        $postvars = $postvars . ', "key":"' . $this->key . '"';
        $postvars = '{' . $postvars . '}'; // JSON 데이터

        $url = "https://directsend.co.kr/index.php/api_v2/mail_change_word";
        $headers = [
            "cache-control: no-cache",
            "content-type: application/json; charset=utf-8"
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status_code != 200) {
            return ['RETURN' => 'ERR'];
        }
        return ['RETURN' => 'SUC'];
        /*
        if($status_code == 200) {
        echo $response;
        } else {
        echo "Error 내용:".$response;
        }
        */
        /*
        * response의 실패
        * {"status":101, "msg":"UTF-8 인코딩이 아닙니다."}
        * 실패 코드번호, 내용
        */
        /*
        * response 성공
        * {"status":0}
        * 성공 코드번호 (성공코드는 다이렉트센드 DB서버에 정상수신됨을 뜻하며 발송성공(실패)의 결과는 발송완료 이후 확인 가능합니다.)
        *
        * 잘못된 이메일 주소가 포함된 경우
        * {"status":0, "msg":"유효하지 않는 이메일을 제외하고 발송 완료 하였습니다.", "msg_detail":"error email : test2@test2, test3@test"}
        * 성공 코드번호 (성공코드는 다이렉트센드 DB서버에 정상수신됨을 뜻하며 발송성공(실패)의 결과는 발송완료 이후 확인 가능합니다.), 내용, 잘못된 데이터
        *
        */
        /*
        status code
        0   : 정상발송 (성공코드는 다이렉트센드 DB서버에 정상수신됨을 뜻하며 발송성공(실패)의 결과는 발송완료 이후 확인 가능합니다.)
        100 : POST validation 실패
        101 : 회원정보가 일치하지 않음
        102 : Subject, Body 정보가 없습니다.
        103 : Sender 이메일이 유효하지 않습니다.
        104 : receiver 이메일이 유효하지 않습니다.
        105 : 본문에 포함되면 안되는 확장자가 있습니다.
        106 : body validation 실패
        107 : 받는사람이 없습니다.
        108 : 예약정보가 유효하지 않습니다.
        109 : return_url이 없습니다.
        110 : 첨부파일이 없습니다.
        111 : 첨부파일의 개수가 5개를 초과합니다.
        112 : 파일의 총Size가 10 MB를 넘어갑니다.
        113 : 첨부파일이 다운로드 되지 않았습니다.
        114 : utf-8 인코딩 에러 발생
        115 : 템플릿 validation 실패
        200 : 동일 예약시간으로는 200회 이상 API 호출을 할 수 없습니다.
        201 : 분당 300회 이상 API 호출을 할 수 없습니다.
        202 : 발송자명이 최대길이를 초과 하였습니다.
        205 : 잔액부족
        999 : Internal Error.
        //추가
        1404 : (마상소프트 코드) 미발송 업데이트
        1403 : (마상소프트 코드) curl false
        */
        // curl_close($ch);
        // return json_decode($response, true);
    }
}
