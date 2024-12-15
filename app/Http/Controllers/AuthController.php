<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Triumph\CommonRequest;
use App\Http\Requests\Triumph\UpdateMemberRequest;
use App\Http\Resources\Auth\MemberResource;
use App\Http\Resources\Triumph\AuthMeResource;
use App\Models\Triumph\Events;
use App\Models\Triumph\Participants;
use App\Models\Triumph\TeamMembers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Repositories\Triumph\MembersRepository;
use App\Services\DirectSendMailService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    protected $membersRepository;
    protected $directSendMailService;
    protected $tokenService;

    public function __construct(MembersRepository $membersRepository, DirectSendMailService $directSendMailService, TokenService $tokenService)
    {
        $this->membersRepository = $membersRepository;
        $this->directSendMailService = $directSendMailService;
        $this->tokenService = $tokenService;
    }

    public function invitation(Request $request, $member_id, $service)
    {
        try {
            $validator = Validator::make(compact('member_id', 'service'), [
                'member_id' => 'required|numeric',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                // 에러 응답을 반환하거나 다른 에러 처리 작업 수행
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            $aValidated = $validator->validated();
            $aMember = $this->membersRepository->getMemberId([
                'member_id' => $aValidated['member_id'],
                'service' => $aValidated['service']
            ]);
            if (!$aMember) {
                throw new \Exception(__('messages.User information not found'), 402); //유저 정보를 찾을수 없습니다.
            }

            if ($aMember->status < 2) {
                throw new \Exception(__('messages.You are already authenticated'), 402); //이미 인증된 사용자입니다.
            }
            // 인증 완료
            $this->membersRepository->updateMemberStatusId([
                'member_id' => $aValidated['member_id'],
                'status' => 1,
                'service' => $aValidated['service'],
            ]);
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => []
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

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:members',
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                $tmpMsg = __('messages.Bad Request');
                $tmpCode = 400;
                if ($errors->has('email')) {
                    $tmpMsg = __('messages.The entered email is already registered'); //해당 이메일은 이미 등록되어 있습니다.
                    $tmpCode = 403;
                    $strEmail = $request->input('email');
                    $strService = $request->input('service');
                    if (filter_var($strEmail, FILTER_VALIDATE_EMAIL)) {
                        $aMember = $this->membersRepository->getMemberEmail([
                            'email' => $strEmail,
                            'service' => $strService
                        ]);
                        if ($aMember->status == '0') {
                            $aJsonData = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => __('messages.Please check your mailbox for the authentication code'), //메일함에서 인증코드를 확인해주세요.
                                'data' => [],
                            ];
                            return response()->json($aJsonData, $aJsonData['code']);
                        }
                    }
                }
                throw new \Exception($tmpMsg, $tmpCode);
            }
            $aValidated = $validator->validated();
            $strAuthCode = strtoupper(Str::random(4));
            $isMember = $this->membersRepository->insertMember([
                'email' => $aValidated['email'],
                'code' => $strAuthCode,
                'service' => $aValidated['service']
            ]);
            if (!$isMember) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $strEmailPath = 'email.code';
            $strSubject = 'triumph :: Email Verification Code Notification';
            if (app()->getLocale() == 'kr') {
                $strEmailPath = 'email.kr.code';
                $strSubject = 'triumph :: 인증코드 이메일 입니다.';
            } elseif (app()->getLocale() == 'en') {
                $strEmailPath = 'email.en.code';
                $strSubject = 'triumph :: Email Verification Code Notification';
            }
            $aMailInfo = [
                'toName' => $aValidated['email'], // 받는사람
                'toMail' => $aValidated['email'], // 받는사람이메일
                'fromName' => 'masangsoft', // 보낸사람
                'fromMail' => 'noreply@masangsoft.com', // 받는사람이메일
                'subject' => $strSubject, // 제목
                'content' => View::make($strEmailPath, ['code' => $strAuthCode])->render(), //메일내용
            ];
            $result = $this->directSendMailService->sendMail($aMailInfo);
            if ($result['RETURN'] != 'SUC') {
                throw new \Exception(__('messages.Failed to send the verification code email. Please resend'), 400); //인증코드 메일 전송에 실패했습니다. 재발송해주세요.
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Created successfully'),
                'data' => [],
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

    public function tempPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            // 제약사항 : 1시간에 2번 요청 금지
            $redisUserKey = $aValidated['service'] . '.member.temp.password.email.' . $aValidated['email'];
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Too many requests. Please try again after a while'), 429); //(3초)허용된 요청량보다 많은 요청을 하였습니다. 잠시후 다시 시도해 주시기 바랍니다.
            }

            $aMember = $this->membersRepository->getMemberEmail([
                'email' => $aValidated['email'],
                'service' => $aValidated['service']
            ]);
            if (!$aMember) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            if ($aMember->status != 2) {
                throw new \Exception(__('messages.User information not found'), 401); //유저 정보를 찾을수 없습니다.
            };
            if ($aMember->oauth_type) {
                throw new \Exception(__('messages.Cannot issue a temporary password for social login types'), 422); //소셜 로그인 유형에서는 임시 비밀번호를 발급할 수 없습니다.
            };
            $strPassword = $this->getCreateTempPassword();
            $aUpdateMember = $this->membersRepository->updateMemberTempPassword([
                'email' => $aValidated['email'],
                'password' => $strPassword,
                'service' => $aValidated['service'],
            ]);
            if (!$aUpdateMember) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }

            $strEmailPath = 'email.temp-password';
            $strSubject = 'triumph :: Temporary Password for Your Current Account';
            if (app()->getLocale() == 'kr') {
                $strEmailPath = 'email.kr.temp-password';
                $strSubject = 'triumph :: 임시 비밀번호 이메일 입니다.';
            } elseif (app()->getLocale() == 'en') {
                $strEmailPath = 'email.en.temp-password';
                $strSubject = 'triumph :: Temporary Password for Your Current Account';
            }
            $aMailInfo = [
                'toName' => $aValidated['email'], // 받는사람
                'toMail' => $aValidated['email'], // 받는사람이메일
                'fromName' => 'masangsoft', // 보낸사람
                'fromMail' => 'noreply@masangsoft.com', // 받는사람이메일
                'subject' => $strSubject, // 제목
                'content' => View::make($strEmailPath, ['password' => $strPassword])->render(), //메일내용
            ];
            $result = $this->directSendMailService->sendMail($aMailInfo);
            if ($result['RETURN'] != 'SUC') {
                throw new \Exception(__('messages.Failed to send temporary password email. Please resend'), 400); //임시 비밀번호 메일 전송에 실패했습니다. 재발송해주세요.
            }
            // 제약사항 :  1시간에 2번 요청 금지
            Redis::setex($redisUserKey, 3600, json_encode([
                'email' => $aValidated['email']
            ])); // 초단위
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Temporary password has been sent to your email'), //임시 비밀번호를 메일로 전송하였습니다.
                'data' => []
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

    public function verify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
                'code' => 'required|min:4|max:4',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                $tmpCode = 400;
                $tmpMsg = __('messages.Bad Request');
                if ($errors->has('code')) {
                    $tmpMsg = __('messages.Invalid authentication code'); //잘못된 인증 코드입니다.
                    $tmpCode = 401;
                }
                throw new \Exception($tmpMsg, $tmpCode);
            }

            $aValidated = $validator->validated();
            $aMember = $this->membersRepository->getMemberEmail([
                'email' => $aValidated['email'],
                'service' => $aValidated['service']
            ]);
            if (!$aMember) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            if ($aMember->status > 0) {
                throw new \Exception(__('messages.You are already an authenticated user'), 409); //이미 인증된 사용자입니다.
            }
            $strCodeTime = Carbon::parse($aMember->auth_code_date);
            $strLateTime = $strCodeTime->copy()->addMinutes(10); // 10분
            if (!$strCodeTime->lessThanOrEqualTo($strLateTime)) {
                $strAuthCode = strtoupper(Str::random(4));
                $this->membersRepository->updateMemberCode([
                    'email' => $aValidated['email'],
                    'code' => $strAuthCode,
                    'service' => $aValidated['service']
                ]);
                $strEmailPath = 'email.code';
                $strSubject = 'triumph :: Email Verification Code Notification';
                if (app()->getLocale() == 'ko') {
                    $strEmailPath = 'email.ko.code';
                    $strSubject = 'triumph :: 인증코드 이메일 입니다.';
                } elseif (app()->getLocale() == 'en') {
                    $strEmailPath = 'email.en.code';
                    $strSubject = 'triumph :: Email Verification Code Notification';
                }
                $aMailInfo = [
                    'toName' => $aValidated['email'], // 받는사람
                    'toMail' => $aValidated['email'], // 받는사람이메일
                    'fromName' => 'masangsoft', // 보낸사람
                    'fromMail' => 'noreply@masangsoft.com', // 받는사람이메일
                    'subject' => $strSubject, // 제목
                    'content' => View::make($strEmailPath, ['code' => $strAuthCode])->render(), //메일내용
                ];
                $result = $this->directSendMailService->sendMail($aMailInfo);
                if ($result['RETURN'] != 'SUC') {
                    throw new \Exception(__('messages.Failed to send the verification code email. Please resend'), 400); //인증코드 메일 전송에 실패했습니다. 재발송해주세요.
                }
                throw new \Exception(__('messages.Authentication time has expired. We have resent the email. Please check your mailbox and enter the authentication code'), 401); //인증시간을 초과하였습니다. 메일을 재전송 하였습니다. 메일함을 확인하여 인증코드를 입력해주세요.
            }
            // 인증코드 검증
            if ($aMember->auth_code != $aValidated['code']) {
                throw new \Exception(__('messages.Invalid authentication code'), 401); //잘못된 인증 코드입니다.
            }
            // 인증 완료
            $this->membersRepository->updateMemberStatus([
                'email' => $aValidated['email'],
                'status' => 1,
                'service' => $aValidated['service'],
            ]);
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful login'),
                'data' => []
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

    public function update(UpdateMemberRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $aUserInfo = $request->user();
            $aValidated['name'] = Arr::get($aValidated, 'name', $aUserInfo->name);
            $aValidated['image_url'] = Arr::get($aValidated, 'image_url', $aUserInfo->image_url);
            $aValidated['language'] = Arr::get($aValidated, 'language', $aUserInfo->language);
            $aValidated['push'] = Arr::get($aValidated, 'push', $aUserInfo->push);
            if ($aValidated['language'] == 'kr') {
                $aValidated['language'] = 'ko';
            }
            $aUpdateMember = $this->membersRepository->updateMemberWithMemberId([
                'member_id' => $aUserInfo->member_id,
                'name' => $aValidated['name'],
                'image_url' => $aValidated['image_url'],
                'language' => $aValidated['language'],
                'push' => $aValidated['push'],
            ]);
            if (!$aUpdateMember) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => []
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

    public function updatePassword(Request $request)
    {
        try {
            // 인증코드 검증 후 패스워드 입력
            $validator = Validator::make($request->all(), [
                'password' => [
                    'required', Password::min(8)->numbers()->letters(), 'max:20', // numbers(): 숫자를 포함 / letters(): 알파벳을 포함 / mixedCase(): 대소문자를 혼합하여 사용/ / symbols(): 특수문자 포함
                ],
                'device' => 'required|in:mobile,web,android,ios',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $tmpCode = 400;
                $tmpMsg = __('messages.Bad Request');
                if ($errors->has('password')) {
                    $tmpMsg = __('messages.Password must be a combination of letters and numbers, and should be between 4 and 20 characters'); //비밀번호는 알파벳과 숫자의 조합이어야 하고, 4자 ~ 20자를 넘어서는 안됩니다.
                    $tmpCode = 401;
                }
                throw new \Exception($tmpMsg, $tmpCode);
            }
            $aValidated = $validator->validated();
            $aMember = $request->user();
            if (!$aMember || $aMember->status != 2 || $aMember->password == '') {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $this->membersRepository->updateMemberPassword([
                'email' => $aMember->email,
                'nickname' => $aMember->name,
                'password' => $aValidated['password'],
                'service' => $aValidated['service']
            ]);
            $strToken = $this->tokenService->getPlainTextToken($aMember->email, $aValidated['service'], $aValidated['device']);
            if (!$strToken) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            //패스워드 설정완료
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'user' => [
                        'member_id' => $aMember->member_id,
                        'email' => $aMember->email,
                        'name' => $aMember->name,
                        'image_url' => null,
                        'service' => $aValidated['service'],
                    ],
                    'token' => $strToken
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

    public function insertPassword(Request $request)
    {
        try {
            // 인증코드 검증 후 패스워드 입력
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => [
                    'required', Password::min(8)->numbers()->letters(), 'max:20', // numbers(): 숫자를 포함 / letters(): 알파벳을 포함 / mixedCase(): 대소문자를 혼합하여 사용/ / symbols(): 특수문자 포함
                ],
                'device' => 'required|in:mobile,web,android,ios',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $tmpCode = 400;
                $tmpMsg = __('messages.Bad Request');
                if ($errors->has('password')) {
                    $tmpMsg = __('messages.Password must be a combination of letters and numbers, and should be between 4 and 20 characters'); //비밀번호는 알파벳과 숫자의 조합이어야 하고, 4자 ~ 20자를 넘어서는 안됩니다.
                    $tmpCode = 401;
                }
                throw new \Exception($tmpMsg, $tmpCode);
            }
            $aValidated = $validator->validated();
            $aMember = $this->membersRepository->getMemberEmail([
                'email' => $aValidated['email'],
                'service' => $aValidated['service']
            ]);
            if (!$aMember || $aMember->status != 1 || $aMember->password != '') {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $strNickname = $this->getCreateNickname();
            // 기본 이미지
            $aUserDefaultImage = [
                'https://web-files-virginia.masanggames.com/_Triumph/PageImages/Sub/icon_profile1.png',
                'https://web-files-virginia.masanggames.com/_Triumph/PageImages/Sub/icon_profile2.png',
                'https://web-files-virginia.masanggames.com/_Triumph/PageImages/Sub/icon_profile3.png',
            ];
            $strUserDefaultImage = collect($aUserDefaultImage)->random();
            $this->membersRepository->updateMemberPassword([
                'email' => $aValidated['email'],
                'nickname' => $strNickname,
                'password' => $aValidated['password'],
                'image_url' => $strUserDefaultImage,
                'service' => $aValidated['service']
            ]);
            $aMember = $this->membersRepository->getMemberEmail([
                'email' => $aValidated['email'],
                'service' => $aValidated['service']
            ]);
            $strToken = $this->tokenService->getPlainTextToken($aValidated['email'], $aValidated['service'], $aValidated['device']);
            if (!$strToken) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            //패스워드 설정완료
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'user' => [
                        'member_id' => $aMember->member_id,
                        'email' => $aValidated['email'],
                        'name' => $strNickname,
                        'image_url' => $strUserDefaultImage,
                        'service' => $aValidated['service'],
                    ],
                    'token' => $strToken
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

    private function getCreateNickname(): string
    {
        $strRnd = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $aAdjectives = config('globalvar.nickname.recommend.adjective');
        $aConstellation = config('globalvar.nickname.recommend.constellation');
        $strAdjective = $aAdjectives[array_rand($aAdjectives)];
        $strConstellation = $aConstellation[array_rand($aConstellation)];
        return $strAdjective . $strConstellation . '#' . $strRnd;
    }

    private function getCreateTempPassword(): string
    {
        $iRandNum = substr(str_shuffle('0123456789'), 0, 3);
        $strRandUpper = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
        $strRandLower = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
        return str_shuffle($iRandNum . $strRandUpper . $strRandLower);
    }

    private function _getPlainTextToken($objMember, $device)
    {
        $token = $objMember->createToken($device)->plainTextToken;
        $aToken = explode('|', $token);
        $strToken = $aToken[1];
        return $strToken;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $aValidated = $request->validated();
            $aMember = $this->membersRepository->modelGetMemberEmail($aValidated['email'], $aValidated['service']);
            if (!$aMember) {
                throw new \Exception(__('messages.Unprocessable id password'), 401); //아이디(로그인 전용 아이디) 또는 비밀번호를 잘못 입력했습니다. 입력하신 내용을 다시 확인해주세요.
            }
            if ($aMember->status < 2) {
                throw new \Exception(__('messages.Authentication is required'), 401); //인증이 필요합니다.
            }
            // 탈퇴계정 status 4
            if ($aMember->status > 3) {
                throw new \Exception(__('messages.The account is not available'), 403); // 사용할 수 없는 계정입니다.
            }
            // TODO [2024-04-22] [ijkim] 비밀번호 수정 일자 3개월 검증해야함
            if (!Hash::check($aValidated['password'], $aMember->password)) {
                if ($aMember->password_temp && Hash::check($aValidated['password'], $aMember->password_temp)) {
                    $aUpdateMemberPassword = $this->membersRepository->updateMemberPassword([
                        'email' => $aValidated['email'],
                        'nickname' => $aMember->name,
                        'password' => $aValidated['password'],
                        'service' => $aValidated['service'],
                    ]);
                    if (!$aUpdateMemberPassword) {
                        throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                    }
                } else {
                    // TODO [2024-04-22] [ijkim] 아이디 / 비밀번호 5회이상 실패시 아이피기록 및 캡챠 추가
                    $aUpdateMemberLoginFail = $this->membersRepository->updateMemberLoginFailCnt([
                        'email' => $aValidated['email'],
                        'service' => $aValidated['service']
                    ]);
                    if (!$aUpdateMemberLoginFail) {
                        throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                    }
                    throw new \Exception(__('messages.Unprocessable id password'), 401); //아이디(로그인 전용 아이디) 또는 비밀번호를 잘못 입력했습니다. 입력하신 내용을 다시 확인해주세요.
                }
            }
            // 새로운 사용자의 ID를 사용하여 토큰 생성
            $this->membersRepository->updateMemberDate([
                'email' => $aValidated['email'],
                'service' => $aValidated['service']
            ]);
            $strToken = $this->tokenService->getPlainTextToken($aValidated['email'], $aValidated['service'], $aValidated['device']);
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful login'),
                'data' => [
                    'user' => new MemberResource($aMember),
                    'token' => $strToken
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

    public function me(CommonRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $strToken = $request->header('Authorization');
            if ($strToken && Str::startsWith($strToken, 'Bearer ')) {
                $strToken = Str::after($strToken, 'Bearer '); // "Bearer " 부분 제거
            }

            $redisUserKey = $aValidated['service'] . '.me.' . $strToken;
            $aUserInfo = json_decode(Redis::get($redisUserKey), true);
            if (!$aUserInfo) {
                $aUserInfo = $request->user();
                Redis::setex($redisUserKey, 60, json_encode($aUserInfo)); // 초단위
            }

            $result = $request->user();
            if (!$result) {
                $aJsonData = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => __('messages.Bad Request'),
                    'data' => []
                ];
                return response()->json($aJsonData, $aJsonData['code']);
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'user' => new AuthMeResource($result)
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

    public function logout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device' => 'required|in:mobile,web,android,ios',
                'service' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                $aJsonData = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => __('messages.Bad Request'),
                    'data' => []
                ];
                return response()->json($aJsonData, $aJsonData['code']);
            }
            $aValidated = $validator->validated();
            $strDevice = request()->input('device');
            if (request()->user()->tokens()->where('name', $strDevice)->delete()) {
                $aJsonData = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => __('messages.Request successful logout'),
                    'data' => []
                ];
            }
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

    public function destroy(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device' => 'required|in:mobile,web,android,ios',
                'service' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                $aJsonData = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => __('messages.Bad Request'),
                    'data' => []
                ];
                return response()->json($aJsonData, $aJsonData['code']);
            }
            $aValidated = $validator->validated();

            $aMember = $request->user();
            $iTeamLeader = TeamMembers::where('member_id', $aMember->member_id)
                ->where('status', 1)
                ->where('grade', 1)
                ->count();
            if ($iTeamLeader) {
                throw new \Exception(__('messages.You cannot leave if a team where you are the leader exists'), 403); // 팀장인 팀이 존재할 경우 탈퇴가 불가능합니다.
            }

            $iEventModerator = Events::where('member_id', $aMember->member_id)
                ->where('status', '<', 3)
                ->count();
            if ($iEventModerator) {
                throw new \Exception(__('messages.You cannot leave if there are unfinished events you have created'), 403); // 본인이 생성한 이벤트 중 종료되지 않은 이벤트가 있을 경우 탈퇴가 불가능합니다.
            }

            $iEventParticipant = Participants::join('events', 'participants.event_id', '=', 'events.event_id')
                ->where(function ($query) use ($aMember) {
                    $query->where('participants.entrant_id', $aMember->member_id)
                        ->orWhere('participants.create_member_id', $aMember->member_id);
                })
                ->where('events.status', '<', 3)
                ->count();
            if ($iEventParticipant) {
                throw new \Exception(__('messages.You cannot leave if there are unfinished events you are participating in'), 403); // 참여한 이벤트 중 종료되지 않은 이벤트가 있을 경우 탈퇴가 불가능합니다.
            }

            $this->membersRepository->withdrawalMember([
                'member_id' => $aMember->member_id,
                'email' => $aMember->email,
                'service' => $aValidated['service'],
            ]);

            $strDevice = request()->input('device');
            if (request()->user()->tokens()->where('name', $strDevice)->delete()) {
                $aJsonData = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => __('messages.withdrawal successful'),
                    'data' => []
                ];
            }
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
}
