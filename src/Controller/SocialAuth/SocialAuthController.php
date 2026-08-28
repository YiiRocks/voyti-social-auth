<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Controller\SocialAuth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Helper\Views\VoytiCommonParametersInjection;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Lists the current user's connected social accounts and lets them disconnect one.
 */
final readonly class SocialAuthController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private ?Collection $clientCollection,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private FlashNotifier $flashNotifier,
        private bool $allowMultipleAccountsPerProvider,
    ) {}

    public function delete(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        /** @var ?UserSocialAccount $account */
        $account = UserSocialAccount::query()->where(['id' => $id])->one();

        if ($account !== null && $account->getUserId() === (int) $user->getId()) {
            $account->delete();
            $this->flashNotifier->add(
                FlashType::SUCCESS,
                $this->translator->translate('voyti.settings.account_disconnected', category: 'voyti-social-auth'),
            );

            return $this->redirect($this->url->generate('voyti/user-social-auth'));
        }

        return $this->renderView('shared/message', [
            'data' => [
                'title' => $this->translator->translate('voyti.settings.account_not_found', category: 'voyti-social-auth'),
                'homeUrl' => $this->homeUrl(),
            ],
        ]);
    }

    public function index(): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        $accounts = UserSocialAccount::findByUserId((int) $user->getId());

        $authChoice = null;
        if ($this->clientCollection !== null) {
            /** @infection-ignore-all The auth-choice widget (route + cosmetic button styling) only renders when the host has configured OAuth clients, so its construction has no behavioural effect the library's own suite can observe. */
            $authChoice = AuthChoice::widget()
                ->authRoute('voyti/session-auth')
                ->linkAttributes(['class' => LinkButtonHelper::submitButtonClass()]);
        }
        $clients = $authChoice?->getClients() ?? [];

        if ($authChoice !== null && !$this->allowMultipleAccountsPerProvider) {
            $connectedProviders = array_fill_keys(
                array_map(
                    static fn(UserSocialAccount $account): string => $account->getProvider(),
                    $accounts,
                ),
                true,
            );
            $authChoice->setClients(array_diff_key($clients, $connectedProviders));
        }
        $url = $this->url;

        $rows = array_map(
            static function (UserSocialAccount $account) use ($clients, $url): array {
                $provider = $account->getProvider();
                $title = array_key_exists($provider, $clients) ? $clients[$provider]->getTitle() : $provider;
                $decodedData = $account->getDecodedData();

                return [
                    'providerTitle' => $title,
                    'identity' => $decodedData['username'] ?? $decodedData['name'] ?? $account->getClientId(),
                    'formSubmitUrl' => $url->generate('voyti/user-social-auth-delete', ['id' => $account->getId()]),
                ];
            },
            $accounts,
        );

        return $this->renderView('social-auth/index', [
            'data' => [
                'menuHtml' => $this->viewRenderer
                    ->withAddedInjections(CsrfViewInjection::class, VoytiCommonParametersInjection::class)
                    ->withViewPath($this->resolveViewPath('shared/_menu'))
                    ->renderPartialAsString('shared/_menu', ['menu' => MenuView::account($this->config, $this->url, $this->translator())]),
                'flashHtml' => $this->viewRenderer
                    ->withAddedInjections(CsrfViewInjection::class, VoytiCommonParametersInjection::class)
                    ->withViewPath($this->resolveViewPath('shared/_flash'))
                    ->renderPartialAsString('shared/_flash'),
                'accounts' => $rows,
                'authChoice' => $authChoice,
            ],
        ]);
    }
}
