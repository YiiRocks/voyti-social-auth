<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use YiiRocks\Voyti\SocialAuth\tests\TestCase;

/**
 * Test case that boots the in-memory SQLite database (module + RBAC migrations) for each test.
 */
abstract class DatabaseTestCase extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
