<?php

declare(strict_types=1);

use YiiRocks\Voyti\Middleware\RequireLoginMiddleware;
use YiiRocks\Voyti\SocialAuth\Controller\Registration\SocialConnectController;
use YiiRocks\Voyti\SocialAuth\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\SocialAuth\Middleware\CaptureAuthActionRequestMiddleware;
use YiiRocks\Voyti\VoytiRoutes;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Yii\AuthClient\AuthAction;

/** @var array $params */

$voytiParams = $params['yiirocks/voyti'] ?? [];

return [
    Group::create()
        ->middleware(CaptureAuthActionRequestMiddleware::class, ...VoytiRoutes::webMiddleware($voytiParams))
        ->routes(
            Route::get('auth/{authclient}')->name('voyti/session-auth')->action(AuthAction::class),
            Route::get('connect/{code}')->name('voyti/registration-connect')->action([SocialConnectController::class, 'connect']),
            Group::create('settings/')
                ->middleware(RequireLoginMiddleware::class)
                ->routes(
                    Route::get('networks/')->name('voyti/user-social-network')->action([SocialNetworkController::class, 'index']),
                    Route::post('networks/disconnect/{id:\d+}')->name('voyti/user-social-network-delete')->action([SocialNetworkController::class, 'delete']),
                ),
        ),
];
