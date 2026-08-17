<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\LinkButtonHelper;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *   providerTitle: string,
 *   loginUrl: string,
 *   registerUrl: string,
 * } $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.connect_title', category: 'voyti-social-auth'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.connect_title', category: 'voyti-social-auth'));
echo Html::p($translator->translate('voyti.view.registration.connect_provider', ['provider' => $data['providerTitle']], category: 'voyti-social-auth'));

echo Html::p($translator->translate('voyti.view.registration.connect_message', category: 'voyti-social-auth'));

echo Html::a($translator->translate('voyti.view.registration.connect_login', category: 'voyti-social-auth'), $data['loginUrl'])->class(LinkButtonHelper::submitButtonClass());

echo ' ';

echo Html::a($translator->translate('voyti.view.registration.connect_register', category: 'voyti-social-auth'), $data['registerUrl'])->class('btn', 'btn-outline-secondary');
echo Html::div()->close();
