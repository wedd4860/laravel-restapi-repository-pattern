<?php

namespace App\Services;

use App\Models\Triumph\Urls;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\UrlsRepository;

class ShortenURLService
{
    public function getShortenUrl($array)
    {
        $validator = Validator::make($array, [
            'content_name' => 'required|string|max:15',
            'content_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return null;
        }
        $aValidated = $validator->validated();

        $iContentType = $this->getContentType($aValidated['content_name']);
        if (!$iContentType) {
            return null;
        }

        $aShortenUrl = Urls::select('shorten_url')->where('content_type', $iContentType)->where('content_id', $aValidated['content_id'])->first();
        $strShortenUrl = $aShortenUrl ? $aShortenUrl['shorten_url'] : null;

        return $strShortenUrl;
    }

    public function createShortenUrl($array)
    {
        $validator = Validator::make($array, [
            'original_url' => 'required|string|max:2083',
            'content_name' => 'required|string|max:15',
            'content_id' => 'required|numeric',
            'expired_dt' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        if ($validator->fails()) {
            return false;
        }
        $aValidated = $validator->validated();

        // url 데이터 검증
        if (!Str::containsAll($aValidated['original_url'], [$aValidated['content_name'], $aValidated['content_id']], ignoreCase: true)) {
            return false;
        }

        $iContentType = $this->getContentType($aValidated['content_name']);
        if (!$iContentType) {
            return false;
        }

        $iShortenUrlKey = 1;
        $strShortenUrlKey = null;

        while ($iShortenUrlKey) {
            $strFirstUrlKey = chr(rand(65, 90));
            $strAfterUrlKey = str_shuffle(Str::random(9));
            $strShortenUrlKey = $strFirstUrlKey . $strAfterUrlKey;
            $iShortenUrlKey = Urls::where('shorten_url', $strShortenUrlKey)->count();
        }

        $urlsRepository = new UrlsRepository();
        $iResult = $urlsRepository->insertShortenUrl([
            'original_url' => $aValidated['original_url'],
            'shorten_url' => $strShortenUrlKey,
            'content_type' => $iContentType,
            'content_id' => $aValidated['content_id'],
            'expired_dt' => $aValidated['expired_dt'],
        ]);

        if (!$iResult) {
            return false;
        }

        return true;
    }

    public function getContentType($contentName)
    {
        if ($contentName == 'team') {
            return 1;
        } elseif ($contentName == 'event') {
            return 2;
        } else {
            return null;
        }
    }
}
