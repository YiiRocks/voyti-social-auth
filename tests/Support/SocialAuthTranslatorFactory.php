<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Builds a real translator bound to this package's `voyti-social-auth` message files, so service
 * tests exercise the actual message-key to English-string mapping instead of mocking it.
 */
final class SocialAuthTranslatorFactory
{
    public static function create(): TranslatorInterface
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti-social-auth',
                new MessageSource(dirname(__DIR__, 2) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        return $translator;
    }
}
