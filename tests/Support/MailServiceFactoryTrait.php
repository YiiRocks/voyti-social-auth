<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use Composer\InstalledVersions;
use YiiRocks\Voyti\Service\MailService;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\View\View;

/**
 * Builds a real MailService for tests instead of mocking the final class. Pair with MailCapture to
 * assert on sent mail, or FailingMailer to exercise the send-failure branch.
 */
trait MailServiceFactoryTrait
{
    private function createMailService(MailerInterface $mailer): MailService
    {
        $coreRoot = InstalledVersions::getInstallPath('yiirocks/voyti');

        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource($coreRoot . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        return new MailService(
            $mailer,
            $coreRoot . '/resources/mail',
            new View(),
            $translator,
            new FakeUrlGenerator(),
            'App',
        );
    }
}
