<?php

declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\SocialAuth\Controller\SocialNetwork\SocialNetworkController;
use YiiRocks\Voyti\SocialAuth\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialAuthCallbackService;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialAuthClientReturnUrlConfigurator;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialUserAttributesNormalizer;
use YiiRocks\Voyti\SocialAuth\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\SocialAuth\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\View\WebView;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/** @var array $params */

return [
    // The redirect/callback route action wired directly as the `voyti/session-auth` route (see
    // routes.php). Unlike core's old class_exists()-guarded binding, this package hard-requires
    // yiisoft/yii-auth-client, so the guard isn't needed here.
    AuthAction::class => static fn(
        Collection $clientCollection,
        Aliases $aliases,
        WebView $view,
        ResponseFactoryInterface $responseFactory,
        CurrentRoute $currentRoute,
        SocialAuthClientReturnUrlConfigurator $returnUrlConfigurator,
        SocialAuthCallbackService $callback,
    ) => (new AuthAction(
        $returnUrlConfigurator->configure($clientCollection),
        $aliases,
        $view,
        $responseFactory,
        $currentRoute,
    ))
        ->withSuccessCallback($callback->handleSuccess(...))
        ->withCancelCallback($callback->handleCancel(...)),

    SocialNetworkController::class => static fn(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        UrlGeneratorInterface $url,
        VoytiConfig $config,
        ?Collection $clientCollection,
        CurrentUser $currentUser,
        ResponseFactoryInterface $responseFactory,
        FlashNotifier $flashNotifier,
    ) => new SocialNetworkController(
        $translator,
        $viewRenderer,
        $url,
        $config,
        $clientCollection,
        $currentUser,
        $responseFactory,
        $flashNotifier,
        $params['yiirocks/voyti']['social-auth']['allowMultipleAccountsPerProvider'] ?? false,
    ),

    SocialUserAttributesNormalizer::class => SocialUserAttributesNormalizer::class,
    SocialAuthCallbackService::class => SocialAuthCallbackService::class,
    UserSocialAccountConnectService::class => static fn(
        TranslatorInterface $translator,
    ) => new UserSocialAccountConnectService(
        $translator,
        $params['yiirocks/voyti']['social-auth']['allowMultipleAccountsPerProvider'] ?? false,
    ),
    SocialAuthClientReturnUrlConfigurator::class => static fn(
        UrlGeneratorInterface $url,
    ) => new SocialAuthClientReturnUrlConfigurator(
        $url,
        $params['yiirocks/voyti']['social-auth']['allowMultipleAccountsPerProvider'] ?? false,
    ),

    // Implements core's PostLoginHookInterface and PostRegistrationHookInterface (tagged below), so
    // it's consulted by core's LoginCompletionService/RegistrationController without core knowing
    // this class exists.
    PendingSocialAccountService::class => [
        'class' => PendingSocialAccountService::class,
        '__construct()' => [
            'allowMultipleAccountsPerProvider' => $params['yiirocks/voyti']['social-auth']['allowMultipleAccountsPerProvider'] ?? false,
        ],
        'tags' => ['voyti.post-login-hook', 'voyti.post-registration-hook'],
    ],

    UserSocialAuthenticateService::class => static fn(
        VoytiConfig $config,
        CurrentUser $currentUser,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
        UserCreationHelper $userCreationHelper,
        PendingSocialAccountService $pendingSocialAccountService,
        TranslatorInterface $translator,
    ) => new UserSocialAuthenticateService(
        $config,
        $params['yiirocks/voyti']['social-auth']['enableSocialNetworkRegistration'] ?? true,
        $currentUser,
        $session,
        $eventDispatcher,
        $userCreationHelper,
        $pendingSocialAccountService,
        $translator,
    ),

    // Translation category source for this package's message files.
    'yiirocks/voyti-social-auth.translator' => [
        'definition' => static fn() => new CategorySource(
            'voyti-social-auth',
            new MessageSource(dirname(__DIR__) . '/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
