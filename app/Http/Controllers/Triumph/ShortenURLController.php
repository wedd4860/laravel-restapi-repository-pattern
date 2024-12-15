<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Library\Util;
use App\Models\Triumph\Events;
use App\Models\Triumph\Teams;
use App\Models\Triumph\Urls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\UrlsRepository;

class ShortenURLController extends Controller
{
    protected $urlsRepository;

    public function __construct(UrlsRepository $urlsRepository)
    {
        $this->urlsRepository = $urlsRepository;
    }

    public function index(Request $request, $shortUrl)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aShortenUrl = Urls::where('shorten_url', $shortUrl)->first();
            if (!$aShortenUrl) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            if ($aShortenUrl->content_type === 1) { // 1. 팀
                $aOgData = Teams::where('team_id', $aShortenUrl->content_id)->first();
                if (!$aOgData) {
                    throw new \Exception(__('messages.Bad Request'), 400);
                }
                $sOgImage = $aOgData->image_url;
                $sOgTitle = $aOgData->name;
                $sOgDescription = null;
            } elseif ($aShortenUrl->content_type === 2) { // 2. 이벤트
                $aOgData = Events::where('event_id', $aShortenUrl->content_id)->first();
                if (!$aOgData) {
                    throw new \Exception(__('messages.Bad Request'), 400);
                }
                $sOgImage = $aOgData->image_url;
                $sOgTitle = $aOgData->title;
                $sOgDescription = $aOgData->description;
            } else {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            $aShortenUrl->increment('redirect_count');

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'original_url' => $aShortenUrl['original_url'],
                    'og_image' => $sOgImage,
                    'og_title' => $sOgTitle,
                    'og_description' => $sOgDescription,
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'original_url' => 'required|url|max:2083',
                'content_name' => 'required|string|max:15',
                'content_id' => 'required|numeric',
                'expired_dt' => 'nullable|date_format:Y-m-d H:i:s',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            // url 데이터 검증
            if (!Str::containsAll($aValidated['original_url'], [$aValidated['content_name'], $aValidated['content_id']], ignoreCase: true)) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            if ($aValidated['content_name'] == 'team') {
                $iContentType = 1;
            } elseif ($aValidated['content_name'] == 'event') {
                $iContentType = 2;
            } else {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            $aShortenUrl = Urls::where('content_type', $iContentType)->where('content_id', $aValidated['content_id'])->first();
            if ($aShortenUrl) {
                // 해당 컨텐츠의 surl이 존재할 떄
                $aReturn = [
                    'shorten_url' => $aShortenUrl['shorten_url']
                ];
            } else {
                // 해당 컨텐츠의 surl이 존재하지 않을 때
                $iShortenUrlKey = 1;
                $strShortenUrlKey = null;

                while ($iShortenUrlKey) {
                    $strFirstUrlKey = chr(rand(65, 90));
                    $strAfterUrlKey = str_shuffle(Str::random(9));
                    $strShortenUrlKey = $strFirstUrlKey . $strAfterUrlKey;
                    $iShortenUrlKey = Urls::where('shorten_url', $strShortenUrlKey)->count();
                }

                $iResult = $this->urlsRepository->insertShortenUrl([
                    'original_url' => $aValidated['original_url'],
                    'shorten_url' => $strShortenUrlKey,
                    'content_type' => $iContentType,
                    'content_id' => $aValidated['content_id'],
                    'expired_dt' => $aValidated['expired_dt'],
                ]);

                if (!$iResult) {
                    throw new \Exception(__('messages.Bad Request'), 400);
                }

                $aReturn = [
                    'shorten_url' => $strShortenUrlKey
                ];
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aReturn
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }
}
