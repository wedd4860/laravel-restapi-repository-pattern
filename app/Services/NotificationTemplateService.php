<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class NotificationTemplateService
{
    public function __construct()
    {
    }

    private function getTemplate(string $type, array $aBind = []): array
    {
        // 알림 유형별 템플릿 정의
        $templates = [
            'join_the_team' => [
                'title' => __('messages.[notifications.title] join_the_team'), //새로운 팀원이 가입했습니다!
                'body' => __('messages.[notifications.body] join_the_team'), //{{팀원 이름}} 님이 방금 팀에 가입했습니다. 토너먼트를 위한 준비를 시작하세요!
                'action_title' => __('messages.[notifications.action_title] join_the_team'), //멤버 확인
            ],
            'approved_to_join_the_team' => [
                'title' => __('messages.[notifications.title] approved_to_join_the_team'), //환영합니다! 팀원이 되었습니다.
                'body' => __('messages.[notifications.body] approved_to_join_the_team'), //{{팀 이름}}팀 가입 요청을 승인했습니다. 이제 팀과 함께 토너먼트에 참여할 수 있습니다!
                'action_title' => __('messages.[notifications.action_title] approved_to_join_the_team'), //팀 확인하기
            ],
            'refusal_to_join_the_team' => [
                'title' => __('messages.[notifications.title] refusal_to_join_the_team'), //팀 가입이 거절되었습니다.
                'body' => __('messages.[notifications.body] refusal_to_join_the_team'), //{{[name]}} 팀에 대한 가입 요청이 거절되었습니다. 새로운 팀에 도전해보세요!
                'action_title' => __('messages.[notifications.action_title] refusal_to_join_the_team'), //팀 목록 보기
            ],
            'team_kickoff' => [
                'title' => __('messages.[notifications.title] team_kickoff'), //팀에서 제외되었습니다.
                'body' => __('messages.[notifications.body] team_kickoff'), //아쉽지만, 팀장이 당신을 {{[name]}} 팀에서 제외했습니다. 다른 팀에 가입해보세요!
                'action_title' => __('messages.[notifications.action_title] team_kickoff'), //새로운 팀 찾기
            ],
            'create_tournament_bracket' => [
                'title' => __('messages.[notifications.title] create_tournament_bracket'), //토너먼트가 시작되었습니다!
                'body' => __('messages.[notifications.body] create_tournament_bracket'), //{{[name]}}의 대진표가 완료되었으며, 곧 경기가 시작됩니다.
                'action_title' => __('messages.[notifications.action_title] create_tournament_bracket'), //토너먼트 확인하기
            ],
            'move_to_the_game_room' => [
                'title' => __('messages.[notifications.title] move_to_the_game_room'), //주최자가 호출되었습니다!
                'body' => __('messages.[notifications.body] move_to_the_game_room'), //{{[name]}}이 주최자를 호출했습니다. 현재 게임 방으로 이동하여 상황을 확인해 주세요.
                'action_title' => __('messages.[notifications.action_title] move_to_the_game_room'), //게임 방으로 이동
            ],
            'opponent_accepted_match' => [
                'title' => __('messages.[notifications.title] opponent_accepted_match'), //상대가 경기를 수락했습니다!
                'body' => __('messages.[notifications.body] opponent_accepted_match'), //{{[name]}}이 경기를 수락했습니다. 곧 경기가 시작될 예정입니다.
                'action_title' => __('messages.[notifications.action_title] opponent_accepted_match'), //게임 방으로 이동
            ],
            'opponent_declined_match' => [
                'title' => __('messages.[notifications.title] opponent_declined_match'), //상대가 경기를 거절했습니다.
                'body' => __('messages.[notifications.body] opponent_declined_match'), //{{[name]}}이 경기를 거절했습니다.
                'action_title' => __('messages.[notifications.action_title] opponent_declined_match'), //게임 방으로 이동
            ],
            'match_started' => [
                'title' => __('messages.[notifications.title] match_started'), //경기가 시작되었습니다!
                'body' => __('messages.[notifications.body] match_started'), //경기가 이제 시작됩니다. 모두 최선을 다해 싸워주세요!
                'action_title' => __('messages.[notifications.action_title] match_started'), //게임 방으로 이동
            ],
            'match_ended' => [
                'title' => __('messages.[notifications.title] match_ended'), //경기가 종료되었습니다!
                'body' => __('messages.[notifications.body] match_ended'), //경기가 끝났습니다. 결과를 기다려주세요.
                'action_title' => __('messages.[notifications.action_title] match_ended'), //게임 방으로 이동
            ],
            'match_results_entered' => [
                'title' => __('messages.[notifications.title] match_results_entered'), //경기 결과가 입력되었습니다.
                'body' => __('messages.[notifications.body] match_results_entered'), //{{[name]}}의 결과 입력이 완료되었습니다. 결과를 검토하고 승인해 주세요.
                'action_title' => __('messages.[notifications.action_title] match_results_entered'), //경기 결과 확인하기
            ],
            'match_judgment_completed' => [
                'title' => __('messages.[notifications.title] match_judgment_completed'), //경기 판정이 완료되었습니다!
                'body' => __('messages.[notifications.body] match_judgment_completed'), //최종 결과를 확인해보세요!
                'action_title' => __('messages.[notifications.action_title] match_judgment_completed'), //게임 방으로 이동
            ],
            '30minutes_before_match_starts' => [
                'title' => __('messages.[notifications.title] 30minutes_before_match_starts'), //경기 시작 30분 전입니다.
                'body' => __('messages.[notifications.body] 30minutes_before_match_starts'), //30분 뒤 {{[name]}} 경기가 시작될 예정입니다.
                'action_title' => __('messages.[notifications.action_title] 30minutes_before_match_starts'), //이벤트 확인하기
            ],
            '1hour_before_match_starts' => [
                'title' => __('messages.[notifications.title] 1hour_before_match_starts'), //경기 시작 1시간 전입니다.
                'body' => __('messages.[notifications.body] 1hour_before_match_starts'), //2시간 뒤 {{[name]}} 경기가 시작될 예정입니다.
                'action_title' => __('messages.[notifications.action_title] 1hour_before_match_starts'), //이벤트 확인하기
            ],
            '2hour_before_match_starts' => [
                'title' => __('messages.[notifications.title] 2hour_before_match_starts'), //경기 시작 2시간 전입니다.
                'body' => __('messages.[notifications.body] 2hour_before_match_starts'), //2시간 뒤 {{[name]}} 경기가 시작될 예정입니다.
                'action_title' => __('messages.[notifications.action_title] 2hour_before_match_starts'), //이벤트 확인하기
            ],
            'match_results_submit' => [
                'title' => __('messages.[notifications.title] match_results_submit'), //경기 결과 입력 완료
                'body' => __('messages.[notifications.body] match_results_submit'), //참가자들이 경기 결과를 등록했습니다. 결과를 확인하고 판정을 완료해 주세요.
                'action_title' => __('messages.[notifications.action_title] match_results_submit'), //경기 결과 확인하기.
            ],
        ];
        $template = $templates[$type] ?? ['title' => 'New notification', 'body' => 'New notification'];

        // 템플릿 내의 치환 값들을 실제 값으로 변환
        foreach ($aBind as $key => $value) {
            $template['title'] = str_replace($key, $value, $template['title']);
            $template['body'] = str_replace($key, $value, $template['body']);
        }

        return $template;
    }

    public function createTemplate(array $params)
    {
        // 1. 언어설정
        $strLang = $params['lang'] ?? 'en';
        if (in_array($strLang, ['ko', 'kr'])) {
            $strLang = 'kr';
        } elseif (in_array($strLang, ['jp'])) {
            $strLang = 'jp';
        } elseif (in_array($strLang, ['zh-cn'])) {
            $strLang = 'zh-cn';
        } elseif (in_array($strLang, ['en'])) {
            $strLang = 'en';
        }
        app()->setLocale($strLang);

        return $this->getTemplate(Arr::get($params, 'template'), Arr::get($params, 'bind', []));
    }

    public function getParseDynamoDBData(array $rawData): array
    {
        $parsedData = [];

        foreach ($rawData as $key => $value) {
            // 각 데이터의 타입 키(S, N 등)와 실제 값을 추출
            $type = array_keys($value)[0]; // 타입 키 추출 (예: 'N', 'S')
            $parsedData[$key] = $value[$type]; // 타입 키의 실제 값 추출
        }

        return $parsedData;
    }
}
