<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Creates the `user_social_account` table owned by yiirocks/voyti-social-auth, kept out of core's
 * own user-module migration: a social-provider identity (e.g. Google, GitHub), either already
 * linked to a `user_id` or still pending connection via its one-time `code`.
 */
final class M260621102500_create_user_social_account_table implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%user_social_account}}');
    }

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%user_social_account}}', [
            'id' => ColumnBuilder::primaryKey(),
            'user_id' => ColumnBuilder::integer(),
            'provider' => ColumnBuilder::string(255)->notNull(),
            'client_id' => ColumnBuilder::string(255)->notNull(),
            'code' => ColumnBuilder::string(32),
            'email' => ColumnBuilder::string(255),
            'username' => ColumnBuilder::string(255),
            'data' => ColumnBuilder::text(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-user-id', ['user_id']);
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-provider-client-id', ['provider', 'client_id'], 'UNIQUE');
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-code', ['code'], 'UNIQUE');
    }
}
