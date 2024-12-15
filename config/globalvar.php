<?php

return [
    //서비스 종류
    'service' => [
        'triumph'
    ],
    //서비스별 db 값
    'triumph' => [
        'members_service' => '1',
        'members_grade' => [
            'member' => '0', 'admin' => '1'
        ],
    ],
    //관리자 ip
    'admin' => [
        'ip' => ['112.185.196.17', '112.185.196.48']
    ],
    'nickname' => [
        //닉네임 추천recommend=>[constellation(별자리)[], adjective(형용사)[]]
        'recommend' => [
            'constellation' => [
                'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo',
                'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn',
                'Aquarius', 'Pisces', 'Pegasus', 'Perseus', 'Orion',
                'Andromeda', 'Hydra', 'Indus', 'Pegasus', 'Hercules'
            ],
            'adjective' => [
                'Dynamic', 'Bold', 'Passionate', 'Taurus', 'Stable',
                'Sturdy', 'Reliable', 'Versatile', 'Curious', 'Sociable',
                'Emotional', 'Caring', 'Shy', 'Confident', 'Proud',
                'Charismatic', 'Meticulous', 'Practical', 'Cautious', 'Harmonious'
            ],
        ]
    ],
    'env' => [
        'DYNAMODB_STAGE' => env('DYNAMODB_STAGE'),
        'TYPESENSE_HOST' => env('TYPESENSE_HOST'),
        'TYPESENSE_PORT' => env('TYPESENSE_PORT'),
        'TYPESENSE_API_KEY' => env('TYPESENSE_API_KEY'),
        'TYPESENSE_PROTOCOL' => env('TYPESENSE_PROTOCOL'),
        'CROSS_ORIGIN_POLICY_URLS' => env('CROSS_ORIGIN_POLICY_URLS'),
        'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
        'AWS_BUCKET' => env('AWS_BUCKET'),
        'AWS_CDN_URL' => env('AWS_CDN_URL'),
        'DYNAMODB_STAGE' => env('DYNAMODB_STAGE'),
        'WEBSOCKET_URL' => env('WEBSOCKET_URL'),
    ],
    'notification' => [
        'icon' => 'https://tp.masanggames.com/favicon_32x32.png',
        'badge' => 'https://tp.masanggames.com/favicon_32x32.png',
        'image' => 'https://web-files-virginia.masanggames.com/_Triumph/PageImages/Default/tp_logo_white.png',
        'profile' => 'https://web-files-virginia.masanggames.com/_Triumph/PageImages/Default/default_profile_img.png',
    ]
];
