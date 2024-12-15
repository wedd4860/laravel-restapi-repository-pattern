<?php

use App\Http\Controllers;
use App\Http\Controllers\Triumph;
use App\Http\Controllers\Triumph\Moderator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 인증 통합서비스
Route::group(['middleware' => ['auth:sanctum']], function () {
    // 이미지 업로드
    Route::group(['prefix' => 'file'], function () {
        Route::get('/presigned-url', [Triumph\FileUploadController::class, 'getPresignedUrl']); //get s3 임시 업로드 url
        Route::post('/upload', [Triumph\FileUploadController::class, 'insertUpload']); //get s3 업로드, 개발 미정
    });

    // 알림서비스
    Route::group(['prefix' => 'notification'], function () {
        Route::group(['prefix' => 'me'], function () {
            Route::get('/', [Triumph\NotificationMeController::class, 'show']); // 내 알림 조회
            Route::patch('/read', [Triumph\NotificationMeController::class, 'update']); //알림 한개 업데이트
            Route::patch('/read/all', [Triumph\NotificationMeController::class, 'updateAll']); //알림 전체 업데이트
        });

        Route::group(['prefix' => 'tokens'], function () {
            Route::get('/me/check', [Triumph\NotificationTokenController::class, 'check']); //토큰 확인
            Route::post('/me', [Triumph\NotificationTokenController::class, 'store']); //토큰 추가
            Route::delete('/me/{tokenId?}', [Triumph\NotificationTokenController::class, 'destroy'])
            ->where('tokenId', '[A-Za-z0-9:_-]{140,200}?'); //토큰삭제
        });

        Route::prefix('bracket')->group(function () {
            Route::post('/{bracketId}/call', [Triumph\NotificationCallController::class, 'store'])->whereNumber('bracketId'); // 주최자 호출
        });
    });

    // 내정보, 로그아웃
    Route::get('/logout', [Controllers\AuthController::class, 'logout']); //로그아웃

    // 맴버, 정보변경
    Route::group(['prefix' => 'me'], function () {
        Route::get('/', [Controllers\AuthController::class, 'me']); //내 정보 보기
        Route::put('/', [Controllers\AuthController::class, 'update']); // 내 정보수정 수정
        Route::patch('/password', [Controllers\AuthController::class, 'updatePassword']); // /api/temp/password 임시비밀번호 발송
        Route::delete('/', [Controllers\AuthController::class, 'destroy']); // 회원 탈퇴
    });

    // 게임 프로필 연동
    Route::group(['prefix' => 'game'], function () {
        Route::get('/', [Triumph\GameProfileController::class, 'index']); // 연동 가능한 게임 리스트 /api/game
        Route::get('/me', [Triumph\GameProfileController::class, 'me']); // 나의 연동된 게임 프로필 리스트 /api/game/name
        Route::post('/', [Triumph\GameProfileController::class, 'store']); // 게임 프로필 추가 /api/game

        Route::put('/{platformGameMemberId}', [Triumph\GameProfileController::class, 'update'])
            ->where('platformGameMemberId', '[0-9]+'); // 게임 프로필 수정 /api/{platformGameMemberId}
        Route::delete('/{platformGameMemberId}', [Triumph\GameProfileController::class, 'destroy'])
            ->where('platformGameMemberId', '[0-9]+'); // 게임 프로필 삭제 /api/{platformGameMemberId}
    });

    // 채팅
    Route::group(['prefix' => 'chat'], function () {
        Route::get('/{bracketId}', [Triumph\MessagesController::class, 'index']);
        Route::post('/{bracketId}/{accessType}', [Triumph\MessagesController::class, 'sendMessage'])
            ->where('accessType', 'member|manager|admin|system');
    });

    // 이벤트, 브라켓, 엔트리, 셋트
    Route::group(['prefix' => 'event'], function () {
        // 이벤트
        Route::get('/me', [Triumph\EventController::class, 'me']); //리스트
        Route::post('/', [Triumph\EventController::class, 'store']); //이벤트 저장
        Route::put('/{eventId}', [Triumph\EventController::class, 'update'])
            ->where('eventId', '[0-9]+'); // 이벤트 수정
        Route::delete('/{eventId}', [Triumph\EventController::class, 'destroy'])
            ->where('eventId', '[0-9]+'); // 이벤트 삭제

        Route::post('/{eventId}/edit', [Triumph\EventController::class, 'permission'])
            ->where('eventId', '[0-9]+'); // 이벤트 수정 권한 확인

        // 참가자
        Route::get('/{eventId}/part/list', [Triumph\ParticipantController::class, 'index'])
            ->where('eventId', '[0-9]+'); // 참가자(팀) 목록 전체
        Route::get('/{eventId}/part/me', [Triumph\ParticipantController::class, 'me'])
            ->where('eventId', '[0-9]+'); // 해당 이벤트의 내 참여 정보
        Route::get('/{eventId}/part/team/{teamId}', [Triumph\ParticipantController::class, 'participableMember'])
            ->where('eventId', '[0-9]+')->where('teamId', '[0-9]+'); // 해당 이벤트에 참여 가능한 팀 멤버 리스트

        Route::put('/{eventId}/part/sync', [Triumph\ParticipantController::class, 'sync'])
            ->where('eventId', '[0-9]+'); // 해당 이벤트의 내 참여 정보를 프로필 데이터와 동기화

        // 실제 유저 엔트리
        Route::post('/{eventId}/part/person', [Triumph\ParticipantController::class, 'storePerson'])
            ->where('eventId', '[0-9]+'); // 개인 엔트리 등록
        Route::post('/{eventId}/part/team', [Triumph\ParticipantController::class, 'storeTeam'])
            ->where('eventId', '[0-9]+'); // 팀 엔트리 등록
        Route::delete('/{eventId}/part/{entrantId}', [Triumph\ParticipantController::class, 'delete'])
            ->where('eventId', '[0-9]+')->where('entrantId', '[0-9]+'); // 엔트리 등록 취소

        // 더미 엔트리
        Route::post('/{eventId}/part/dummy/person', [Triumph\ParticipantController::class, 'storeDummyPerson'])
            ->where('eventId', '[0-9]+'); // 더미 개인 엔트리 등록
        Route::post('/{eventId}/part/dummy/team', [Triumph\ParticipantController::class, 'storeDummyTeam'])
            ->where('eventId', '[0-9]+'); // 더미 팀 엔트리 등록
        Route::delete('/{eventId}/part/dummy/{participantId}', [Triumph\ParticipantController::class, 'deleteDummy'])
            ->where('eventId', '[0-9]+')->where('participantId', '[0-9]+'); // 개인전,팀전 더미 삭제

        // 참가 확정|취소
        Route::put('/{eventId}/part/{type}', [Triumph\ParticipantController::class, 'update'])
            ->where('eventId', '[0-9]+')->where('type', 'in|out'); // 체크 인|아웃

        // 브라켓
        Route::post('/{eventId}/bracket', [Triumph\BracketController::class, 'matchPointUpdate'])
            ->where('eventId', '[0-9]+'); // 브라켓 수정
        Route::put('/{eventId}/bracket/{bracketId}', [Triumph\BracketController::class, 'update'])
            ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); // 브라켓 개별 수정

        // 브라켓 엔트리
        Route::post('/{eventId}/bracket/entry', [Triumph\BracketEntryController::class, 'store'])
            ->where('eventId', '[0-9]+'); //브라켓 엔트리 생성
        Route::put('/{eventId}/bracket/entry/{type}', [Triumph\BracketEntryController::class, 'update'])
            ->where('eventId', '[0-9]+')->where('type', 'score|status'); //브라켓 엔트리 업데이트

        // 브라켓 셋트
        Route::get('/{eventId}/bracket/{bracketId}/set', [Triumph\BracketSetController::class, 'index'])
            ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); //브라켓 ID에 해당하는 셋트 리스트
        Route::post('/{eventId}/bracket/{bracketId}/set', [Triumph\BracketSetController::class, 'store'])
            ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); //브라켓 셋트 생성
    });

    // moderator 기능, 주최자만 가능
    Route::group(['prefix' => 'moderator'], function () {
        Route::put('/event/{eventId}/bracket/{bracketId}/entry', [Moderator\BracketEntryController::class, 'update'])
            ->where('event/eventId', '[0-9]+')->where('bracketId', '[0-9]+'); //브라켓 엔트리 업데이트
        Route::post('/event/{eventId}/bracket/{bracketId}/set', [Moderator\BracketSetController::class, 'store'])
            ->where('event/eventId', '[0-9]+')->where('bracketId', '[0-9]+'); //브라켓 셋트 생성

        Route::put('/event/{eventId}/bracket/{bracketId}/contingency/entry', [Moderator\BracketEntryController::class, 'contingencyUpdate'])
            ->where('event/eventId', '[0-9]+')->where('bracketId', '[0-9]+'); //브라켓 엔트리 강제 업데이트
    });

    //팀 전체
    Route::group(['prefix' => 'team'], function () {
        //팀
        Route::get('/me', [Triumph\TeamController::class, 'me']); // /api/team/me 가입된 팀 리스트
        Route::post('/', [Triumph\TeamController::class, 'store']); // /api/team 팀 생성

        //팀 관리
        Route::get('/{teamId}', [Triumph\TeamController::class, 'show'])->where('teamId', '[0-9]+'); // /api/team/{teamId} 팀 정보
        Route::put('/{teamId}/{type}', [Triumph\TeamController::class, 'update'])
            ->where('teamId', '[0-9]+')->where('type', 'name|image'); // 팀 정보 수정 {이름|이미지URL}
        Route::delete('/{teamId}', [Triumph\TeamController::class, 'delete'])->where('teamId', '[0-9]+'); // /api/team/{teamId} 팀 삭제

        // 팀 멤버
        Route::get('/{teamId}/member/list', [Triumph\TeamController::class, 'memberList'])->where('teamId', '[0-9]+'); // /api/team/{teamId}/member/list 팀원 리스트
        Route::post('/{teamId}/member', [Triumph\TeamController::class, 'memberStore'])->where('teamId', '[0-9]+'); // /api/team/{teamId}/member 팀원 가입 신청
        Route::put('/{teamId}/member/{memberId}/{type}', [Triumph\TeamController::class, 'memberUpdate'])
            ->where('teamId', '[0-9]+')->where('memberId', '[0-9]+')->where('type', 'approval|refuse'); // /api/team/{teamId}/member/{memberId}/{type} 팀원 승인|거절
        Route::delete('/{teamId}/member/{memberId}/{type}', [Triumph\TeamController::class, 'memberDelete'])
            ->where('teamId', '[0-9]+')->where('memberId', '[0-9]+')->where('type', 'kick|withdrawal'); // /api/team/{teamId}/member/{memberId}/{type} 팀원 강퇴|탈퇴
    });

    // 단축 URL
    Route::group(['prefix' => 'url'], function () {
        Route::post('/', [Triumph\ShortenURLController::class, 'store']); // shorten URL 생성
    });
});

//서명 라우트
Route::group(['middleware' => ['signed']], function () {
    Route::get('/invitations/{memberId}/{service}', [Controllers\AuthController::class, 'invitation'])->name('invitations');
});

// 회원가입, 인증코드확인, 패스워드수정
Route::group(['prefix' => 'member'], function () {
    Route::post('/', [Controllers\AuthController::class, 'register']); // /api/member 맴버 등록
    Route::post('/verify', [Controllers\AuthController::class, 'verify']); // /api/member/verify 인증코드 확인
    Route::put('/password', [Controllers\AuthController::class, 'insertPassword']); // /api/member/password 최초 패스워드 입력
});

// 로그인
Route::post('/login', [Controllers\AuthController::class, 'login']); // /api/login 로그인

// 임시 관련
Route::group(['prefix' => 'temp'], function () {
    Route::post('/password', [Controllers\AuthController::class, 'tempPassword']); // /api/temp/password 임시비밀번호 발송
});

// 팀
Route::group(['prefix' => 'team'], function () {
    Route::get('/', [Triumph\TeamController::class, 'index']); // /api/team 전체 팀 리스트

    Route::get('/{teamId}/info', [Triumph\TeamController::class, 'show'])
        ->where('teamId', '[0-9]+'); // /api/team/{teamId}/info 비로그인 유저 팀 정보
});

// 이벤트, 참가자
Route::group(['prefix' => 'event'], function () {
    // 이벤트
    Route::get('/', [Triumph\EventController::class, 'index']); //리스트
    Route::get('/{eventId}', [Triumph\EventController::class, 'show'])->where('eventId', '[0-9]+'); // 디테일
    Route::get('/{eventId}/ranker', [Triumph\EventController::class, 'rankerList'])->where('eventId', '[0-9]+'); // 종료된 이벤트 상위랭커 정보

    // 참가자
    Route::get('/{eventId}/part/list/checkin', [Triumph\ParticipantController::class, 'checkInList'])
        ->where('eventId', '[0-9]+'); // 체크인 참가자(팀) 목록

    // 브라켓
    Route::get('/{eventId}/bracket', [Triumph\BracketController::class, 'index']); // 브라켓 리스트 - 토너먼트용
    Route::get('/{eventId}/bracket/{bracketId}/participant', [Triumph\BracketController::class, 'participantsList'])
        ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); // 브라켓 참여 인원 리스트
    Route::get('/{eventId}/bracket/{bracketId}/status', [Triumph\BracketController::class, 'bracketStatus'])
        ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); // 브라켓 진행 상태
    Route::get('/{eventId}/bracket/{bracketId}/chats', [Triumph\BracketController::class, 'show'])
        ->where('eventId', '[0-9]+')->where('bracketId', '[0-9]+'); // 브라켓 채팅 리스트

    Route::get('/game/{gameId}/recommended/trending', [Triumph\EventRecommendedController::class, 'index'])
        ->whereNumber('gameId'); // /event/games/{gameId}/recommended/trending 가중치 기반 게임별 대회 검색
    Route::get('/recommended/trending', [Triumph\EventRecommendedController::class, 'index']); // /recommended/trending 가중치 기반 게임별 대회 검색
});

// 단축 URL
Route::group(['prefix' => 'url'], function () {
    Route::get('/{shortUrl}', [Triumph\ShortenURLController::class, 'index'])
        ->where('shortUrl', '^[A-Z][a-zA-Z0-9]{9}$'); // shorten URL 원본 가져오기
});

// 서버 시간
Route::get('/time', [Triumph\TimeController::class, 'index']); // 서버 시간

Route::group(['prefix' => 'games'], function () {
    Route::get('/list', [Triumph\GamesController::class, 'all']); // /api/games/list 전체 게임 목록

    Route::get('/', [Triumph\GamesController::class, 'index']); // /games 리스트
    Route::get('/{gameId}', [Triumph\GamesController::class, 'show'])
        ->whereNumber('gameId'); // /games/{gameId} 리스트
    Route::get('/{gameId}/event', [Triumph\GameEventSearchController::class, 'index'])
        ->whereNumber('gameId'); // /games/{gameId}/event 게임별 대회 검색
    Route::get('/{gameId}/event/recommended', [Triumph\GameEventRecommendedController::class, 'index'])
        ->whereNumber('gameId'); // /games/{gameId}/event/recommended 게임별 대회 검색
});
