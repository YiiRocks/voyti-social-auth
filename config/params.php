<?php

declare(strict_types=1);

/** @var array $params */

return [
    'yiirocks/voyti' => [
        'accountMenuItems' => [
            [
                'label' => 'voyti.menu.networks',
                'category' => 'voyti-social-auth',
                'route' => 'voyti/user-social-network',
            ],
        ],
    ],

    'yiirocks/voyti-social-auth' => [
        'enableSocialNetworkRegistration' => true,
        'allowMultipleAccountsPerProvider' => false,
    ],
];
