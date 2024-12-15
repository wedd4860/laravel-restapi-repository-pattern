<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Models\Triumph\Members;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StageAuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $email)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            $aValidated = $validator->validated();
            $aMember = Members::where('email', $email)->first();
            $aMember->makeVisible(['auth_code']); // hidden_field를 표시
            if (!$aMember) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aMember
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
