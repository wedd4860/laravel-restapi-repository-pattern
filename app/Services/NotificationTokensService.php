<?php

namespace App\Services;

use App\Library\Util;
use App\Repositories\Triumph\TokensRepository;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class NotificationTokensService
{
    protected $repository;
    protected $tableName;

    public function __construct()
    {
        $client = new DynamoDbClient([
            'region' => config('services.notification.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.notification.key'),
                'secret' => config('services.notification.secret'),
            ],
        ]);
        $this->tableName = 'tokens-' . config('globalvar.env.DYNAMODB_STAGE');
        // 레포지토리에 DynamoDBClient를 주입
        $this->repository = new TokensRepository($client);
    }

    // 전체 토큰 체크
    public function getTokens(array $aValidated): array
    {
        $aNotification = [
            'TableName' => $this->tableName,
            'IndexName' => 'memberIndex', // GSI(Global Secondary Index)
            'ProjectionExpression' => '#tokenAttr', // 셀렉트값들 고르기
            'KeyConditionExpression' => 'member_id = :member_id',
            'ExpressionAttributeValues' => [
                ':member_id' => ['N' => strval($aValidated['member_id'])],
            ],
            'ExpressionAttributeNames' => [
                '#tokenAttr' => 'token', // 예약어 대체 이름 설정
            ],
        ];
        $result = $this->repository->query($aNotification);
        return [
            'data' => $this->getFormatItems($result['Items']) ?? [],
        ];
    }

    // 개별 데이터 조회
    public function getToken(array $aValidated): array
    {
        $aNotification = [
            'TableName' => $this->tableName,
            // 'IndexName' => 'memberIndex', // GSI(Global Secondary Index) 검색조건이 파티션키 + GSI 조합시 해당옵션은 사용안함
            'ProjectionExpression' => '#tokenAttr, member_id, created_at', // 셀렉트값들 고르기
            'KeyConditionExpression' => 'member_id = :member_id AND #tokenAttr = :token',
            'ExpressionAttributeValues' => [
                ':token' => ['S' => $aValidated['token']],
                ':member_id' => ['N' => strval($aValidated['member_id'])],
                // ':read_status' => ['S' => 'unread'], // unread 필터
            ],
            'ExpressionAttributeNames' => [
                '#tokenAttr' => 'token', // 예약어 대체 이름 설정
            ],
            // 'FilterExpression' => 'read_status = :read_status', // 필터 조건 추가
            'Limit' => $aValidated['limit'],
            'ScanIndexForward' => false, // true 오름차순, false 내림차순
        ];
        $result = $this->repository->query($aNotification);
        return [
            'data' => $this->getFormatItems($result['Items']) ?? [],
        ];
    }

    // 토큰 생성
    public function createToken(array $params)
    {
        $validator = Validator::make($params, [
            'member_id' => 'required|numeric',
            'token' => 'required|string|min:140|max:200'
        ]);
        if ($validator->fails()) {
            return [];
        }
        $aValidated = $validator->validated();
        return $this->repository->putItem(
            $this->tableName,
            Util::getDynamoDBFormat([
                'member_id' => $aValidated['member_id'],
                'token' => $aValidated['token'],
                'created_at' => Carbon::now()->timestamp,
            ])
        );
    }

    // 토큰 업데이트
    public function updateToken(array $params)
    {
        // 사용안함
        return false;
        $validator = Validator::make($params, [
            'member_id' => 'required|numeric',
            'token' => 'required|string|min:140|max:200',
            'is_push' => 'required|in:Y,N'
        ]);
        if ($validator->fails()) {
            return [];
        }
        $aValidated = $validator->validated();

        $aToken = [
            'Key' => [
                'token' => ['S' => (string)$aValidated['token']],
                'member_id' => ['N' => (string)$aValidated['member_id'],
                ] // 파티션키와 정렬키를 포함
            ],
            'TableName' => $this->tableName,
            'UpdateExpression' => 'SET is_push = :is_push',
            // 'ExpressionAttributeNames' => [
            //     '#isPushAttr' => 'is_push',
            // ],
            'ExpressionAttributeValues' => [
                ':is_push' => ['S' => $aValidated['is_push']],
            ]
        ];
        return $this->repository->updateItem($aToken);
    }

    // 토큰 삭제
    public function removeToken(array $params)
    {
        $validator = Validator::make($params, [
            'member_id' => 'required|numeric',
            'token' => 'required|string|min:140|max:200'
        ]);
        if ($validator->fails()) {
            return [];
        }
        $aValidated = $validator->validated();
        return $this->repository->deleteItem(
            $this->tableName,
            Util::getDynamoDBFormat([
                'member_id' => $aValidated['member_id'],
                'token' => $aValidated['token'],
            ])
        );
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

    public function deleteBatchWrite(array $params)
    {
        $validator = Validator::make($params, [
            'tokens' => 'required|array',
            'tokens.*' => 'string',
            'member_id' => 'numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            throw new \Exception(__('messages.Bad Request'), 400);
        }
        $aValidated = $validator->validated();
        $aDeleteRequest = [];
        foreach ($aValidated['tokens'] as $token) {
            $aDeleteRequest[] = [
                'DeleteRequest' => [
                    'Key' => [
                        'token' => ['S' => $token],  // 파티션 키
                        'member_id' => ['N' => (string)$aValidated['member_id']],  // 정렬 키
                    ]
                ]
            ];
        }
        $chunks = array_chunk($aDeleteRequest, 25);  // 25개씩 나누기
        foreach ($chunks as $chunk) {
            $result = $this->repository->batchWriteItem([
                'RequestItems' => [
                    $this->tableName => $chunk
                ]
            ]);
        }
        return $result;
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
