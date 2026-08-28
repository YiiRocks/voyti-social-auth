<?php

declare(strict_types=1);

use YiiRocks\Voyti\Middleware\RequireLoginMiddleware;
use YiiRocks\Voyti\SocialAuth\Controller\Registration\SocialConnectController;
use YiiRocks\Voyti\SocialAuth\Controller\SocialAuth\SocialAuthController;
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
                    Route::get('social/')->name('voyti/user-social-auth')->action([SocialAuthController::class, 'index']),
                    Route::post('social/disconnect/{id:\d+}')->name('voyti/user-social-auth-delete')->action([SocialAuthController::class, 'delete']),
                ),
        ),
];
