<?php

declare(strict_types=1);

/** @var array $params */

return [
    'yiirocks/voyti' => [
        'accountMenuItems' => [
            [
                'label' => 'voyti.menu.social_auth',
                'category' => 'voyti-social-auth',
                'route' => 'voyti/user-social-auth',
            ],
        ],

        'social-auth' => [
            'enableSocialAuthRegistration' => true,
            'allowMultipleAccountsPerProvider' => false,
        ],
    ],
];
