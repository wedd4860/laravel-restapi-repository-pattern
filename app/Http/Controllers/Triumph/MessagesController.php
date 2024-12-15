<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\Chat;
use App\Events\triumph\MemberMessage;
use App\Events\triumph\ManagerMessage;
use App\Events\triumph\SystemMessage;
use App\Events\triumph\AdminMessage;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\ChatsRepository;
use App\Repositories\Triumph\BracketsRepository;

class MessagesController extends Controller
{
    protected $chatsRepository;
    protected $bracketsRepository;

    public function __construct(ChatsRepository $chatsRepository, BracketsRepository $bracketsRepository)
    {
        $this->chatsRepository = $chatsRepository;
        $this->bracketsRepository = $bracketsRepository;
    }
    public function index(Request $request, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'sometimes|numeric',
                'perPage' => 'sometimes|numeric',
                'type' => 'sometimes|in:0,1,2,3',
            ]);
            if ($validator->fails()) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            // todo 이유저가 브라켓에 속해있는 유저인지 확인이 필요함
            $aMemberInfo = $request->user();
            $aBracketInfo = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId
            ]);
            if (!$aBracketInfo) {
                throw new \Exception(__('messages.Bracket information not found.'), 404); //브라켓 정보를 찾을 수 없습니다.
            }
            if ($aBracketInfo->status > 1) {
                throw new \Exception(__('messages.The judgment is complete, so the chat room is not available'), 403); //판정이 완료되어 채팅방을 사용할 수 없습니다.
            }
            if ($aValidated['type'] == '') {
                $aEvent = $this->chatsRepository->getChatsBracketIdPage([
                    'bracket_id' => $bracketId,
                    'page' => $aValidated['page'] == '' ? 1 : $aValidated['page'],
                    'perPage' => 20,
                ]);
            } else {
                $aEvent = $this->chatsRepository->getChatsBracketIdPageType([
                    'bracket_id' => $bracketId,
                    'page' => $aValidated['page'] == '' ? 1 : $aValidated['page'],
                    'perPage' => 20,
                    'type' => $aValidated['type'],
                ]);
            }
            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => $aEvent
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            // 실패 시 반환
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function sendMessage(Request $request, $bracketId, $accessType)
    {
        //추가검증 필요
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string',
                'messageType' => 'required|in:0,1,2,3',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMessageType = [
                'member' => '0',
                'system' => '1',
                'manager' => '2',
                'admin' => '3',
            ];
            if ($aValidated['messageType'] != $aMessageType[$accessType]) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aMemberInfo = $request->user();
            $aBracketInfo = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId
            ]);
            if (!$aBracketInfo) {
                throw new \Exception(__('messages.Bracket information not found.'), 404); //브라켓 정보를 찾을 수 없습니다.
            }
            if ($aBracketInfo->status > 1) {
                // 이미 판정이 완료되었습니다. 채팅방을 종료합니다.
                throw new \Exception(__('messages.The judgment is complete, so the chat room is not available'), 403); //판정이 완료되어 채팅방을 사용할 수 없습니다.
            }
            $result = $this->chatsRepository->insertMessage([
                'event_id' => $aBracketInfo->event_id,
                'bracket_id' => $bracketId,
                'member_id' => $aMemberInfo->member_id,
                'message_type' => $aValidated['messageType'],
                'message' => $aValidated['message']
            ]);
            if (!$result) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            if ($accessType == 'member') {
                broadcast(new MemberMessage(
                    $bracketId,
                    $aMemberInfo['member_id'],
                    $aValidated['message']
                )); // ->toOthers()
            } else if ($accessType == 'system') {
                broadcast(new SystemMessage(
                    $bracketId,
                    0,
                    $aValidated['message']
                )); // ->toOthers()
            } else if ($accessType == 'manager') {
                broadcast(new ManagerMessage(
                    $bracketId,
                    $aMemberInfo['member_id'],
                    $aValidated['message']
                )); // ->toOthers()
            } else if ($accessType == 'admin') {
                broadcast(new AdminMessage(
                    $bracketId,
                    $aMemberInfo['member_id'],
                    $aValidated['message']
                )); // ->toOthers()
            } else {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            $aJsonData = [
                "status" => "success",
                "code" => 201,
                "message" => __('messages.Request successful'),
                "data" => [],
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            // 실패 시 반환
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }
}
