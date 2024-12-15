<?php

namespace App\Services;

use App\Repositories\Triumph\MembersRepository;

class TokenService
{
    protected $membersRepository;

    public function __construct(MembersRepository $membersRepository)
    {
        $this->membersRepository = $membersRepository;
    }

    public function getPlainTextToken($email, $service, $device): string
    {
        if (!$email || !$service || !$device) {
            return '';
        }
        $objMember = $this->membersRepository->modelGetMemberEmail($email, $service);
        $token = $objMember->createToken($device)->plainTextToken;
        $aToken = explode('|', $token);
        $strToken = $aToken[1];
        return $strToken;
    }
}
