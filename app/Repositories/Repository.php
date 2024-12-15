<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class Repository
{
    protected function getInsertId()
    {
        try {
            $result = DB::getPdo()->lastInsertId();
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'getInsertId'), 400);
        }
        return $result;
    }

    protected function setInsert(string $query, $value)
    {
        try {
            $result = DB::insert($query, $value);
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'setInsert', compact('query', 'value')), 400);
        }
        return $result;
    }

    protected function setUpdate(string $query, $value)
    {
        try {
            $result = DB::update($query, $value);
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'setUpdate', compact('query', 'value')), 400);
        }
        return $result;
    }

    protected function setDelete(string $query, $value)
    {
        try {
            $result = DB::delete($query, $value);
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'setDelete', compact('query', 'value')), 400);
        }
        return $result;
    }

    protected function getSelect(string $query, $value)
    {
        try {
            $result = DB::select($query, $value)[0] ?? [];
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'getSelect', compact('query', 'value')), 400);
        }
        return $result;
    }

    protected function getSelectAll(string $query, $value)
    {
        try {
            $result = DB::select($query, $value) ?? [];
        } catch (QueryException $e) {
            throw new \Exception($this->_queryException($e, 'getSelectAll', compact('query', 'value')), 400);
        }
        return $result;
    }

    private function _queryException($e, string $method, array $aQuery = [])
    {
        $strMsg = __('messages.Bad Request');
        if (in_array(request()->ip(), config('globalvar.admin.ip'))) {
            $strMsg = "Code {$e->getCode()}, Message: {$e->getMessage()}" . PHP_EOL; // PHP_EOL : 줄바꿈
            $strMsg .= "Method: $method" . PHP_EOL;
            $strMsg .= "Queries: " . PHP_EOL;
            foreach ($aQuery as $key => $value) {
                if (is_array($value)) {
                    $strMsg .= "$key: " . print_r($value, true) . PHP_EOL;
                } else {
                    $strMsg .= "$key: $value" . PHP_EOL;
                }
            }
        }
        return $strMsg;
    }
}
