<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class UserProfileFieldRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array{label:string,value:string,position:int}> */
    public function forUser(int $userId): array
    {
        return array_map(
            static fn (array $row): array => [
                'label' => (string) $row['label'],
                'value' => (string) $row['value'],
                'position' => (int) $row['position'],
            ],
            $this->db->fetchAll(
                'SELECT label, value, position FROM user_profile_fields WHERE user_id = ? ORDER BY position ASC',
                [$userId],
            ),
        );
    }

    /** @param array<int,array{label:string,value:string}> $fields */
    public function replaceForUser(int $userId, array $fields): void
    {
        $this->db->transaction(function () use ($userId, $fields): void {
            $this->db->run('DELETE FROM user_profile_fields WHERE user_id = ?', [$userId]);
            foreach (array_values($fields) as $position => $field) {
                $this->db->run(
                    'INSERT INTO user_profile_fields (user_id, label, value, position, created_at)
                     VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$userId, $field['label'], $field['value'], $position],
                );
            }
        });
    }
}
