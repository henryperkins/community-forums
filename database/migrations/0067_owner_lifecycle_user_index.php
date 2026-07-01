<?php

declare(strict_types=1);

/**
 * 0067 - Owner lifecycle locking index.
 *
 * Account-lifecycle owner/admin guards use SELECT ... FOR UPDATE over active
 * admins. Keep that locking read on a narrow role/status/id index so it does not
 * scan-lock unrelated user rows on larger installs.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE users ADD KEY idx_users_role_status_id (role, status, id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE users DROP KEY idx_users_role_status_id');
    }
};
