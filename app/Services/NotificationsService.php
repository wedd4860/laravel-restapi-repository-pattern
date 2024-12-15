<?php

namespace App\Services;

use App\Library\Util;
use App\Repositories\Triumph\NotificationsRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

//단독사용 금지해주세요.
class NotificationsService
{
    protected $repository;
    protected $tableName;
    protected $profile;

    public function __construct()
    {
        // 이 서비스에서만 DynamoDBClient를 생성
        $client = new DynamoDbClient([
            'region' => config('services.notification.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.notification.key'),
                'secret' => config('services.notification.secret'),
            ],
        ]);
        $this->tableName = 'notifications-' . config('globalvar.env.DYNAMODB_STAGE');
        // 레포지토리에 DynamoDBClient를 주입
        $this->repository = new NotificationsRepository($client);
        $this->profile = config('globalvar.notification.profile');
    }

    // 데이터 추가
    public function createNotification(array $aValidated, array $aTemplate)
    {
        $aNotification = [
            'notification_id' => (string) Str::uuid(),  // 고유 알림 ID 생성
            'member_id' => $aValidated['member_id'],
            'type' => $aValidated['type'],
            'tag' => $aValidated['tag'],
            'link' => $aValidated['link'] ?? '/',
            'action_title' => $aTemplate['action_title'],
            'title' => $aTemplate['title'],
            'body' => $aTemplate['body'],
            'scheduled_time' => $aValidated['scheduled_time'] ?? Carbon::now()->timestamp,
            'profile_img' => $aValidated['profile_img'] ?? $this->profile,
            'status' => 'pending',
            'read_status' => 'unread',
            'created_at' => Carbon::now()->timestamp,
        ];
        $timeScheduledTime = Carbon::createFromTimestamp($aNotification['scheduled_time']);
        $aNotification['expire_at'] = $timeScheduledTime->addWeeks(2)->timestamp; // TTL
        $result = $this->repository->putItem($this->tableName, Util::getDynamoDBFormat($aNotification));
        return $result;
    }

    // 개별 데이터 조회
    public function getNotifications(array $aValidated): array
    {
        $aNotification = [
            'TableName' => $this->tableName,
            'IndexName' => 'memberScheduledIndex', // GSI(Global Secondary Index)
            'ProjectionExpression' => 'title, body, profile_img, action_title, member_id, #statusAttr, notification_id, created_at, read_status, #typeAttr, #linkAttr, #tagAttr', // 셀렉트값들 고르기
            'KeyConditionExpression' => 'member_id = :member_id',
            'ExpressionAttributeValues' => [
                ':member_id' => ['N' => strval($aValidated['member_id'])],
                // ':read_status' => ['S' => 'unread'], // unread 필터
            ],
            'ExpressionAttributeNames' => [
                '#linkAttr' => 'link',
                '#tagAttr' => 'tag',
                '#typeAttr' => 'type', // 예약어 대체 이름 설정
                '#statusAttr' => 'status'
            ],
            // 'FilterExpression' => 'read_status = :read_status', // 필터 조건 추가
            'Limit' => $aValidated['limit'],
            'ScanIndexForward' => false, // true 오름차순, false 내림차순
        ];
        if (Arr::get($aValidated, 'read_status')) {
            $aNotification['ExpressionAttributeValues'][':read_status'] = ['S' => 'unread'];
            $aNotification['FilterExpression'] = 'read_status = :read_status';
        } elseif ($aValidated['lastEvaluatedKey']) {
            // 마지막 조회된 키가 있을 경우 파라미터에 추가
            $aNotification['ExclusiveStartKey'] = $aValidated['lastEvaluatedKey'];
        }
        $result = $this->repository->query($aNotification);
        return [
            'data' => $this->getFormatItems($result['Items']) ?? [],
            'count' => $result['Count'] ?? 0,
            'last_evaluated_key' => $result['LastEvaluatedKey'] ?? null,
        ];
    }

    // 업데이트 배치
    public function updateBatchRead(array $params)
    {
        $validator = Validator::make($params, [
            'notification_id' => 'required|array',
            'notification_id.*' => 'string',
            'member_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            throw new \Exception(__('messages.Bad Request'), 400);
        }
        $aValidated = $validator->validated();
        $aUpdateStatement = [];
        foreach ($aValidated['notification_id'] as $notification_id) {
            $aUpdateStatement[] = [
                'Statement' => "UPDATE \"{$this->tableName}\" SET \"read_status\" = ? WHERE \"notification_id\" = ? AND \"member_id\" = ?",
                'Parameters' => [
                    ['S' => 'read'],
                    ['S' => $notification_id],
                    ['N' => (string)$aValidated['member_id']],  // (정수형을 문자열로 변환)
                ],
                'ReturnValuesOnConditionCheckFailure' => 'ALL_OLD' // 조건 실패 시 이전 값 반환
            ];
        }

        $aChunk = array_chunk($aUpdateStatement, 25);
        foreach ($aChunk as $chunk) {
            $this->repository->batchExecuteStatement([
                'Statements' => $chunk
            ]);
        }
        return [];
    }

    private function getFormatItems(array $items)
    {
        return array_map(function ($item) {
            foreach ($item as $key => $value) {
                if ($key == 'created_at') {
                    $item[$key] = ['N' => Util::getISO8601FromTimestamp($value['N'])];
                }
            }
            return $item;
        }, $items);
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
