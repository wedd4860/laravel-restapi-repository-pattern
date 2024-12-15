<?php

namespace App\Library;

class Injection
{
    function __construct()
    {
    }

    public static function numeric(&$text, $type = 'numeric', $min = 0, $max = 0)
    {
        if (!is_numeric($text)) {
            $text = '';
            return;
        }

        if ($min != 0 && $text < $min) {
            $text = '';
            return;
        }
        if ($max != 0 && $text > $max) {
            $text = '';
            return;
        }

        $text = $text + 0;
        $bCheck = false;
        switch ($type) {
            case 'int':
                $bCheck = is_int($text);
                break;
            case 'float':
                $bCheck = is_float($text);
                break;
            case 'numeric':
                $bCheck = true;
                break;
        }

        if (!$bCheck) {
            $text = '';
            return;
        }
    }

    public static function string(&$text, $length = 255, $regexp = '')
    {
        $text_len = strlen($text);
        if ($length != 0 && $text_len > $length) {
            $text = '';
            return;
        }

        if ($regexp == 'base64') {
            if (($text_len % 4) != 0) {
                $text = '';
                return;
            }
            $regexp = '/[a-z0-9+=\/]+/i';
        } else if ($regexp == 'ip') {
            $regexp = '/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/';
        } else if ($regexp == 'escape') {
            $text = Injection::escape($text);
        } else if ($regexp == 'phone') {
            $regexp = '/[0-9]{2,4}\-?[0-9]{3,4}\-?[0-9]{3,4}/';
        } else if ($regexp == 'ymd') {
            $regexp = '/[0-9]{4}-?(0[1-9]|1[0-2])-?(0[1-9]|[1-2][0-9]|3[0-1])/';
        } else if ($regexp == 'media_code') {
            $regexp = '/.+\_[0-9]+\_[0-9]+/';
        } else if ($regexp == 'ym') {
            $regexp = '/[0-9]{4}-?(0[1-9]|1[0-2])/';
        } else if ($regexp === 'domain') {
            $regexp = '/[^\n\-]([a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}[^\d][a-zA-Z\d]{1,63}/';
        } else if ($regexp === 'domain_port') {
            $regexp = '/[^\n\-]([a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}[^\d][a-zA-Z\d]{1,63}(\:[\d]+)/';
        } else if ($regexp === 'AspToken') {
            $regexp = '/[a-z0-9+=\/]+/i';
        } else if ($regexp == 'card') {
            $regexp = '/[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}/i';
        }

        if ($regexp) {
            $match = null;
            $matched = preg_match($regexp, $text, $match);
            if ($matched == 0 || ($matched > 0 && strcmp($text, $match[0]) !== 0)) {
                $text = '';
                return false;
            }
        }

        return true;
    }

    public static function enum(&$text)
    {
        if (!$text) {
            $text = '';
            return;
        }

        $num = func_num_args();

        if ($num == 1) {
            $text = '';
            return;
        }

        $aEnum = [];
        if ($num == 2 && is_array(func_get_arg(1))) {
            $aEnum = func_get_arg(1);
        } else {
            for ($i = 1; $i < $num; $i++) {
                $aEnum[] = func_get_arg($i);
            }
        }

        $bResult = false;
        foreach ($aEnum as $enum) {
            if (strcmp($text, $enum) === 0) {
                $bResult = true;
                break;
            }
        }

        if (!$bResult) {
            $text = '';
            return;
        }
    }

    public static function enumArray(&$array)
    {
        $return = [
            'valid' => [],
            'invalid' => []
        ];

        if (!$array || !is_array($array) || count($array) == 0) {
            $array = [];
            return $return;
        }

        $num = func_num_args();

        if ($num == 1) {
            $return['invalid'] = $array;
            $array = [];
            return $return;
        }

        $aEnum = [];
        if ($num == 2 && is_array(func_get_arg(1))) {
            $aEnum = func_get_arg(1);
        } else {
            for ($i = 1; $i < $num; $i++) {
                $aEnum[] = func_get_arg($i);
            }
        }

        $arrayY = [];
        $arrayN = [];
        foreach ($array as $item) {
            if (!$item) continue;
            if (in_array($item, $aEnum)) $arrayY[] = $item;
            else $arrayN[] = $item;
        }
        $array = $arrayY;

        return [
            'valid' => $arrayY,
            'invalid' => $arrayN
        ];
    }

    public static function cleanArrayKeys(&$array)
    {
        if (!$array) {
            $array = [];
            return;
        }

        $num = func_num_args();

        if ($num == 1) {
            $array = [];
            return;
        }

        $checkKey = [];
        if ($num == 2 && is_array(func_get_arg(1))) {
            $checkKey = func_get_arg(1);
        } else {
            for ($i = 1; $i < $num; $i++) {
                $checkKey[] = func_get_arg($i);
            }
        }

        foreach ($array as $key => $val) {
            if (!in_array($key, $checkKey)) unset($array[$key]);
        }
    }

    public static function cleanArray(&$array)
    {
        if (!isset($array)) return;
        if (!is_array($array)) {
            return;
        }

        $keys = array_keys($array);
        foreach ($keys as $key) {
            $data = $array[$key];

            if (is_array($data)) {
                Injection::cleanArray($array[$key]);
                continue;
            }

            $bBlock = false;
            if (stripos($data, "union") !== false && stripos($data, "select") !== false) $bBlock = true;
            else if (stripos($data, "insert into") !== false) $bBlock = true;
            else if (stripos($data, "delete from") !== false) $bBlock = true;

            if ($bBlock) $array[$key] = '';
        }
    }

    public static function isSqlInjection($str)
    {
        $bBlock = false;
        if (!$str) {
            return $bBlock;
        }

        if (stripos($str, "union") !== false && stripos($str, "select") !== false) {
            $bBlock = true;
        } else if (stripos($str, "insert into") !== false) {
            $bBlock = true;
        } else if (stripos($str, "delete from") !== false) {
            $bBlock = true;
        }
        return $bBlock;
    }

    public static function avoidCrack($str)
    {
        $str = preg_replace("/<\?/i", "&lt;?", $str);
        $str = preg_replace("/\?>/i", "?&gt;", $str);
        $str = preg_replace("/<script.*<\/script>/i", "", $str);
        $str = preg_replace("/<script.*>/i", "", $str);
        $str = preg_replace("/<\/script.*>/i", "", $str);
        $str = preg_replace("/<iframe.*>/i", "", $str);
        $str = preg_replace("/<param.*>/i", "", $str);
        $str = preg_replace("/<plaintext.*>/i", "", $str);
        $str = preg_replace("/<xml.*>/i", "", $str);
        $str = preg_replace("/<base.*>/i", "", $str);
        $str = preg_replace("/<meta.*>/i", "", $str);
        $str = preg_replace("/<applet.*>/i", "", $str);
        $str = preg_replace("/c\|\/con\/con\//i", "", $str);
        //&lt; &gt; 추가
        $str = preg_replace("/&lt;script.*&lt;\/script&gt;/i", "", $str);
        $str = preg_replace("/&lt;script.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;script.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;\/script.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;iframe.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;param.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;plaintext.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;xml.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;base.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;meta.*&gt;/i", "", $str);
        $str = preg_replace("/&lt;applet.*&gt;/i", "", $str);
        return $str;
    }

    public static function isEmpty($str = null)
    {
        if (!$str) return true;

        if (is_object($str) || is_array($str)) {
            if (count((array)$str) == 0) return true;
        }

        $str = str_replace(["\t", "\n", "\r", '<br>', '<br/>'], '', $str);
        $str = trim($str);
        if ($str == '') return true;

        return false;
    }

    public static function isSpace($string)
    {
        $spaceUni = [
            '\x{0020}', # SPACE
            '\x{00a0}', # NO-BREAK SPACE
            '\x{2002}', # EN SPACE
            '\x{2003}', # EM SPACE
            '\x{2004}', # THREE-PER-EM SPACE
            '\x{2005}', # FOUR-PER-EM SPACE
            '\x{2006}', # SIX-PER-EM SPACE
            '\x{2007}', # FIGURE SPACE
            '\x{2008}', # PUNCTUATION SPACE
            '\x{2009}', # THIN SPACE
            '\x{200a}', # HAIR SPACE
            '\x{200b}', # ZERO WIDTH SPACE
            '\x{3000}', # IDEOGRAPHIC SPACE
            '\x{feff}', # ZERO WIDTH NO-BREAK SPACE
            '\x{0009}', # CHARACTER TABULATION
            '\x{3164}', # 한글채움문자
            '\x{2800}',
            '\x{200d}',
            '\x{115f}', # 한글초성채움문자
            '\x{1160}', # 한글중성채움문자
        ];
        $regexp = '/[' . implode('', $spaceUni) . ']/u';
        $matched = preg_match($regexp, $string);

        if ($matched === 1) {
            return true;
        }

        return false;
    }

    public static function cleanEscape($string)
    {
        if (!$string) return '';
        if (is_array($string)) return array_map(__METHOD__, $string);
        if (Injection::isEscape($string)) return '';
        return $string;
    }

    public static function isEscape($string)
    {
        if (!$string) return false;
        if (is_array($string)) {
            $result = array_map(__METHOD__, $string);
            if (in_array(true, array_values($result))) return true;
            return false;
        }
        $isChk = false;
        # 탈출 시도가 있으면 체크
        if (strlen($string) != strlen(Injection::escape($string))) $isChk = true;
        # 주석 구문이 있으면 체크
        if (!$isChk && stripos($string, '--') !== false) $isChk = true;
        if (!$isChk && stripos($string, '/*') !== false) $isChk = true;
        if (!$isChk && stripos($string, '*/') !== false) $isChk = true;
        if (!$isChk && stripos($string, '#') !== false) $isChk = true;
        if (!$isChk) return false;

        # 쿼리있는지 체크
        $regexp = '/(SELECT|UNION|ALL|UPDATE|SET|INSERT|INTO|DELETE|FROM|WHERE|\*|OUTFILE)/i';
        $match = null;
        $matched = preg_match_all($regexp, $string, $match);
        if ($matched > 0 && $match && count($match[0]) > 0) {
            $string2 = implode('', $match[0]);
            $match2 = array_unique($match[0]);
            # select union
            if (stripos($string2, 'UNIONSELECT') !== false || stripos($string2, 'UNIONALLSELECT') !== false) return true;
            if (stripos($string2, 'SELECTFROMWHERE') !== false || stripos($string2, 'SELECT*FROM') !== false) return true;
            # insert
            if (stripos($string2, 'INSERTINTO') !== false) return true;
            # delete
            if (stripos($string2, 'DELETEFROM') !== false) return true;
            # update
            if (stripos($string2, 'UPDATESET') !== false) return true;
            # 기타체크
            if (stripos($string2, 'INTOOUTFILE') !== false) return true;
            #if(count($match2) >= 3) return true;
        }

        # 함수 체크
        if (preg_match('/IF\([^\,]+\,[^\,]+\,[^\)]+\)/i', $string)) return true;
        if (preg_match('/(IFNULL|NULLIF)\([^\,]+\,[^\)]+\)/i', $string)) return true;
        if (preg_match('/UNIX_TIMESTAMP\(\)/i', $string)) return true;
        if (preg_match('/sleep\([0-9]+\)/i', $string)) return true;

        # 기타 체크
        if (preg_match('/[\'\"]\s*OR\s*(1|TRUE)/i', $string)) return true;

        # 쿼리 패턴 감지
        $regexp = '/(OR|\=|\+|\-\-|\#|TRUE|FALSE|\;|\/\*|\*\/|(0x[0-9]{2,2})|([\"\']{1,1}[a-z]{1,1}[\"\']{1,1})|([a-z\_]{2,}\([^\)]{0,15}\))|([0-9\+\-]+\=[0-9\+\-]+))/i';
        $match = null;
        $matched = preg_match_all($regexp, $string, $match);
        if ($matched > 0 && $match && count($match[0]) > 0) {
            $string2 = str_replace(' ', '', $string);
            $string3 = implode('', $match[0]);
            $strLen2 = intval(strlen($string2) * 0.7);
            if (strlen($string3) > $strLen2) return true;
        }

        # 금지 단어
        if (preg_match('/(INFORMATION_SCHEMA|TABLE_SCHEMA|TABLE_NAME|COLUMN_NAME|\/etc\/passwd|get_host_address|nslookup)/i', $string)) return true;

        return false;
    }

    public static function isBadSpecialChar($str)
    {
        if (!preg_match('/[^a-z A-Zㄱ-ㅎ0-9\x{AC00}-\x{d79f}#\&\\+\-%█▀▄▁▂▃▅▆▁〔〕〈〉《》「」『』【】ღლ◕◔‿♋๑ஐзεω╬✙✚ꀆ㉠☮☯☹☺☻☼☿♀♁♂㉡❶❷❸❹❺❻❼❽❾❿㉢❢❣❤❥❦❧㉣✦✧✪✿❀㉤㉥㉦㉧㉨㉩㉪㉫㉬㉭㉮㉯㉰㉱㉲㉳㉴㉵㉶㉷㉸㉹㉺㉻㈎㈏㈐㈑㈒㈓㈔㈕㈖㈗㈘㈙㈚㈛ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨⓩ⒜⒝⒞⒟⒠⒡⒢⒣⒤⒥⒦⒧⒨⒩⒪⒫⒬⒭⒮⒯⒰⒱⒲⒳⒴⒵①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑴⑵⑶⑷⑸⑹⑺⑻⑼⑽⑾⑿⒀⒁⒂ⅰⅱⅲⅳⅴⅵⅶⅷⅸⅹⅠⅡⅢⅣⅤⅥⅦⅧⅨⅩÐØ＃＆＊＠§※☆★○●◎◇◆□■△▲▽▼→←↑↓↔〓◁◀▷▶♤♠♡♥♧♣⊙◈▣◐◑▒▤▥▨▧▦▩♨☏☎☜☞¶†‡↕↗↙↖↘♭♩♪♬㉿㈜№㏇™㏂㏘℡®㉾ªº@=\/\\\:;,\.\'\"\^`~\_|\!\/\?\*$#<>()\[\]\{\}]/u', $str)) {
            return false;
        } else {
            return true;
        }
    }

    public static function escape($text)
    {
        if (is_array($text))
            return array_map(__METHOD__, $text);

        if (!empty($text) && is_string($text)) {
            return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $text);
        }

        return $text;
    }

    public static function cleanCode(string | null $text, string $addClean = ''): string | null
    {
        if (!$text) {
            return $text;
        }
        // php 문자열 제거
        $text = str_replace("<?", "", $text);
        $text = str_replace("?>", "", $text);
        // html 테그 제거
        if ($addClean == 'html') {
            $text = strip_tags($text);
        }
        // 이스케이프 제거
        $text = Injection::cleanEscape($text);
        // 실행 테그 제거
        $text = Injection::avoidCrack($text);
        // sql 인젝션 체크
        if (Injection::isSqlInjection($text)) {
            $text = '';
        }
        return $text;
    }

    function __destruct()
    {
    }
}
