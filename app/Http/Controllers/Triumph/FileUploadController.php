<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Aws\S3\S3Client;
use Aws\Sdk;

class FileUploadController extends Controller
{
    private function _getPresignedUrl(Request $request)
    {
        $aJsonData = [
            'status' => 'error',
            'code' => 400,
            'message' => __('messages.Bad Request'),
            'data' => []
        ];
        return response()->json($aJsonData, 400);
        // 하단의 방법은 라라벨 8버전용 입니다.
        // 강제방법
        $sdk = new Sdk([
            'region' => config('globalvar.env.AWS_DEFAULT_REGION'),
            'version' => 'latest'
        ]);
        $client = $sdk->createS3();
        $expiry = '+10 minutes';
        $cmd = $client->getCommand('PutObject', [
            'Bucket' => config('globalvar.env.AWS_BUCKET'),
            'Key' => 'cdn/_Triumph/test6.jpg',
            'ACL' => 'public-read',
        ]);
        $request = $client->createPresignedRequest($cmd, $expiry);
        $presignedUrl = (string)$request->getUri();
        return response()->json(['url' => $presignedUrl], 201);
    }

    public function getPresignedUrl(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|in:jpg,jpeg,png,gif,bmp',
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 401);
            }

            $aValidated = $validator->validated();
            $aContentType = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
            ];
            $aValidated['type'] = Arr::get($aValidated, 'type', 'jpg');

            $strNow = Carbon::now();
            $strYear = $strNow->year;
            $strMonth = str_pad($strNow->month, 2, '0', STR_PAD_LEFT);
            $strFile = Str::of(Str::uuid())->replace('-', '') . '.' . $aValidated['type'];
            $strfilePath = "_Triumph/{$strYear}/{$strMonth}/{$strFile}"; // 경로 및 파일 이름

            // 프리사인드 URL 생성
            // $sdk = new Sdk([
            //     'region' => env('AWS_DEFAULT_REGION'),
            //     'version' => 'latest'
            // ]);
            // $client = $sdk->createS3();
            // $objCommand = $client->getCommand('PutObject', [
            //     'Bucket' => env('AWS_BUCKET'),
            //     'Key' => 'cdn/' . $strfilePath,
            //     'ACL' => 'public-read',
            // ]);
            $disk = Storage::disk('s3');
            $client = $disk->getClient();
            $objCommand = $client->getCommand('PutObject', [
                'Bucket' => config('globalvar.env.AWS_BUCKET'),
                'Key' => $strfilePath,
                'ContentType' => $aContentType[$aValidated['type']],
                'ACL' => 'public-read',
            ]);

            $s3Request = $client->createPresignedRequest($objCommand, '+10 minutes'); // URL 유효 시간 설정 (예: 20분)
            $strPresignedUrl = (string) $s3Request->getUri();
            $strFileUrl = config('globalvar.env.AWS_CDN_URL') . '/' . $strfilePath;
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'presigned_url' => $strPresignedUrl,
                    'cdn_url' => $strFileUrl,
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            // 실패 시 반환
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function insertUpload(Request $request)
    {
        $aJsonData = [
            'status' => 'error',
            'code' => 400,
            'message' => __('messages.Bad Request'),
            'data' => []
        ];
        return response()->json($aJsonData, 400);
        // 하단의 방법은 s3에 직접 업로드 하는 방식입니다.

        // 파일을 요청에서 가져옵니다. 여기서 'file'은 폼에서 전송된 파일 필드명입니다.
        $file = $request->file('file');

        // 파일이 있는지 확인하고 S3에 업로드합니다.
        if ($file) {
            $filePath = '_Triumph/' . $file->getClientOriginalName(); // 경로와 파일명 설정
            // S3에 파일을 업로드합니다.
            // Storage::disk('s3')->put($filePath, file_get_contents($file));
            // S3에 파일을 업로드하고 'public-read' ACL 권한 부여
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            // 업로드된 파일의 URL을 가져옵니다.
            $url = Storage::disk('s3')->url($filePath);

            return response()->json(['url' => $url]);
        }

        // 파일이 없는 경우 오류 응답을 반환합니다.
        return response()->json(['error' => 'File not found'], 400);
    }
}
