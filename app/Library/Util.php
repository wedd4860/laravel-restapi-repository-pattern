<?php

namespace App\Library;

class Util
{
    public function __construct()
    {
    }

    /**
     * 현재 시간을 밀리초까지 포함하여 반환합니다.
     *
     * @return string 밀리초까지 포함된 현재 시간을 "Y-m-d H:i:s.v" 형식의 문자열로 반환합니다.
     */
    public static function getMillisecond()
    {
        $microtime = microtime(true); // 현재 시간을 마이크로초 단위로 얻기
        $milliseconds = sprintf('%03d', ($microtime - floor($microtime)) * 1000); // 밀리초 부분만 추출
        $dateWithMilliseconds = date('Y-m-d H:i:s', $microtime) . '.' . $milliseconds; // 초 단위 시간에 밀리초 추가
        return $dateWithMilliseconds;
    }

    /**
     * 주어진 날짜를 ISO 8601 형식으로 변환합니다.
     *
     * @param string $date 변환할 날짜 문자열.
     * @return string ISO 8601 형식의 날짜 문자열.
     */
    public static function getISO8601($date)
    {
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format(\DateTime::ATOM); // ISO 8601 형식으로 변환
        } catch (\Exception $e) {
            return false;
        }
    }
	

    /**
     * 주어진 타임스템프를 ISO 8601 형식으로 변환합니다.
     *
     * @param string $timestamp 변환할 날짜 타임스템프.
     * @return string ISO 8601 형식의 날짜 문자열.
     */
    public static function getISO8601FromTimestamp($timestamp)
    {
        try {
            $dateTime = new \DateTime();
			$dateTime->setTimestamp($timestamp); // 타임스탬프 설정
            return $dateTime->format(\DateTime::ATOM); // ISO 8601 형식으로 변환
        } catch (\Exception $e) {
            return false;
        }
    }

    // dynamodb 포맷팅
    public static function getDynamoDBFormat($attributes)
    {
        $formatted = [];
        foreach ($attributes as $key => $value) {
            $formatted[$key] = is_numeric($value) ? ['N' => (string)$value] : ['S' => (string)$value];
        }
        return $formatted;
    }
    
    public function __destruct()
    {
    }
}
