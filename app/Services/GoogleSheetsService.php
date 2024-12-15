<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GoogleSheetsService
{
    protected $client;
    protected $service;
    protected $documentId;
    protected $sheetId;
    protected $sheetNumber;
    protected $sheetName;

    public function __construct(array $config)
    {
        $this->client = $this->initClient();
        $this->service = new Sheets($this->client);
        $this->documentId = config('services.google_spreadsheet.tp_okr');
        $this->sheetNumber = Arr::get($config, 'sheet_number', 0);
        $this->sheetName = $this->getSheetTitle();
        $this->sheetId = $this->getSheetId();
    }

    protected function initClient()
    {
        $client = new Client();
        $client->setApplicationName('구글 스프레드시트 작성기');
        $client->setScopes(Sheets::SPREADSHEETS);
        $client->setAuthConfig(storage_path('app/credentials.json'));
        return $client;
    }

    private function getSheets()
    {
        $spreadsheet = $this->service->spreadsheets->get($this->documentId);
        return $spreadsheet->getSheets();
    }

    protected function getSheetTitle()
    {
        $aSheet = $this->getSheets();
        return $aSheet[$this->sheetNumber]?->getProperties()->getTitle();
    }

    protected function getSheetId()
    {
        $aSheet = $this->getSheets();
        return $aSheet[$this->sheetNumber]?->getProperties()->getSheetId();
    }

    protected function getRange(array $aTitle, array $aRow): string
    {
        // 열, 행 갯수계산
        $cntTitle = count($aTitle);
        $cntRow = count($aRow) + 1; // 타이틀 행 포함

        $endColumn = $this->getColumnName($cntTitle);

        // 범위 문자열 생성 (예: "Sheet1!A1:D4")
        return "{$this->sheetName}!A1:{$endColumn}{$cntRow}";
    }

    protected function getColumnName(int $columnIndex): string
    {
        // 열 인덱스를 열 문자로 변환 (1 => A, 26 => Z, 27 => AA 등)
        $columnName = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr($columnIndex % 26 + 65) . $columnName;
            $columnIndex = intdiv($columnIndex, 26);
        }
        return $letter;
    }

    public function applyTitleBoldFormat(int $columnCount)
    {
        $requests = [
            [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $this->sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $columnCount
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => [
                                'bold' => true
                            ]
                        ]
                    ],
                    'fields' => 'userEnteredFormat.textFormat.bold'
                ]
            ]
        ];
        $this->executeBatchUpdate($requests);
    }

    protected function executeBatchUpdate(array $requests)
    {
        // BatchUpdate 요청을 준비
        $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => $requests // 포맷, 스타일
        ]);

        // Google Sheets에 BatchUpdate 요청
        $this->service->spreadsheets->batchUpdate(
            $this->documentId,      // 스프레드시트 ID
            $batchUpdateRequest      // 포맷 요청을 담은 BatchUpdateSpreadsheetRequest 객체
        );
    }

    public function applyBorders(int $rowCount, int $columnCount)
    {
        $requests = [
            [
                'updateBorders' => [
                    'range' => [
                        'sheetId' => $this->sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => $rowCount,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $columnCount
                    ],
                    'top' => ['style' => 'SOLID'],
                    'bottom' => ['style' => 'SOLID'],
                    'left' => ['style' => 'SOLID'],
                    'right' => ['style' => 'SOLID'],
                    'innerHorizontal' => ['style' => 'SOLID'],
                    'innerVertical' => ['style' => 'SOLID']
                ]
            ]
        ];

        $this->executeBatchUpdate($requests);
    }

    public function writeData(array $aTitle, array $aDataRow)
    {
        // 주어진 타이틀과 데이터 행을 기반으로 동적으로 시트 범위를 설정
        $range = $this->getRange($aTitle, $aDataRow);
        // 타이틀 행을 데이터의 첫 번째 행으로 추가
        $values = array_merge([$aTitle], $aDataRow);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $values // 2차원 배열 형식으로 전달
        ]);

        // 데이터 입력 방식
        $params = [
            'valueInputOption' => 'RAW' // 'RAW'는 데이터 그대로 입력, 'USER_ENTERED'는 사용자 입력 방식 적용
        ];

        // 지정된 범위($range)에 데이터($body)를 전달하여 업데이트
        $this->service->spreadsheets_values->update(
            $this->documentId,
            $range,
            $body,
            $params
        );
    }

    public function writeAndFormatSheet(array $aTitle, array $aData)
    {
        $this->getRange($aTitle, $aData);

        // 데이터 쓰기
        $this->writeData($aTitle, $aData);
        // 포맷 적용
        $this->applyTitleBoldFormat(count($aTitle));
        $this->applyBorders(count($aData) + 1, count($aTitle));
    }
}
