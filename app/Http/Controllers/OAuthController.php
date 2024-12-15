<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AuthController;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\MembersRepository;
use App\Services\TokenService;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class OAuthController extends Controller
{
    protected $membersRepository;
    protected $tokenService;

    public function __construct(MembersRepository $membersRepository, TokenService $tokenService)
    {
        $this->membersRepository = $membersRepository;
        $this->tokenService = $tokenService;
    }

    public function index()
    {
        return view('temp-login');
    }

    public function redirectToProvider(Request $request, $providerId)
    {
        $validator = Validator::make($request->all(), [
            'service' => 'required|in:triumph',
            'device' => 'required|in:mobile,web,android,ios',
        ]);
        $aValidated = $validator->validated();
        $request->session()->put('service', $aValidated['service']);
        $request->session()->put('device', $aValidated['device']);

        return Socialite::driver($providerId)->redirect();
    }

    public function handleProviderCallback(Request $request, $providerId)
    {
        try {
            $user = Socialite::driver($providerId)->user();
            header('Cross-Origin-Opener-Policy: ' . config('globalvar.env.CROSS_ORIGIN_POLICY_URLS'));
            if ($providerId == 'steam') {
                exit;
            }
            $strService = $request->session()->pull('service');
            $strDevice = $request->session()->pull('device');
            $aUserInfo = [
                'email' => '',
                'nickname' => '',
                'device' => '',
                'service' => '',
                'token' => '',
                'image_url' => '',
                'oauth_type' => '',
                'oauth_id' => '',
            ];
            if ($providerId == 'google') {
                $aUserInfo = [
                    'email' => $user->email,
                    'nickname' => $user->nickname,
                    'service' => $strService,
                    'device' => $strDevice,
                    'token' => $user->token,
                    'image_url' => $user->avatar,
                    'oauth_type' => $providerId,
                    'oauth_id' => $user->id,
                ];
            } else if ($providerId == 'facebook') {
                $aUserInfo = [
                    'email' => $user->email,
                    'nickname' => $user->name,
                    'service' => $strService,
                    'device' => $strDevice,
                    'token' => $user->token,
                    'image_url' => $user->avatar,
                    'oauth_type' => $providerId,
                    'oauth_id' => $user->id,
                ];
            }

            $aMember = $this->membersRepository->getMemberEmail([
                'email' => $aUserInfo['email'],
                'service' => $aUserInfo['service']
            ]);
            if ($aMember) {
                if ($aMember->oauth_type == $providerId) {
                    $iMemberId = $aMember->member_id;
                    //로그인처리
                    $this->membersRepository->updateMemberDate([
                        'email' => $aUserInfo['email'],
                        'service' => $aUserInfo['service']
                    ]);
                } else {
                    //일반회원으로 가입한 회원
                    return view('login.already');
                }
            } else {
                //신규가입
                $iMemberId = $this->membersRepository->insertOAuthMember($aUserInfo);
            }
            $aUserInfo['member_id'] = $iMemberId;
            $aUserInfo['site_token'] = $this->tokenService->getPlainTextToken($aUserInfo['email'], $aUserInfo['service'], $aUserInfo['device']);
            return view('login.callback', $aUserInfo);
        } catch (\Exception $e) {
            return view('status.404');
        }
    }
}
