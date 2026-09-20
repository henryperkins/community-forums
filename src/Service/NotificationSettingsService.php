<?php

declare(strict_types=1);
namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\UserRepository;
use App\Security\WriteGate;

final class NotificationSettingsService
{
    public function __construct(private Database $db, private UserRepository $users, private EmailPreferenceService $email, private WriteGate $writeGate) {}

    public function update(User $viewer, array $input): void
    {
        $draft = [];
        foreach (['timezone', 'digest_hour', 'pause_all_email'] as $key) {
            $draft[$key] = is_scalar($input[$key] ?? null) ? (string) $input[$key] : '';
        }
        $zone = trim($draft['timezone']);
        $zone = $zone === '' ? 'UTC' : $zone;
        $hourRaw = $draft['digest_hour'];
        $errors = [];
        if (is_array($input['timezone'] ?? null) || !in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Choose a valid timezone.';
        }
        if (is_array($input['digest_hour'] ?? null) || ($hourRaw !== '' && $hourRaw !== 'off' && preg_match('/^(?:[0-9]|1[0-9]|2[0-3])$/D', $hourRaw) !== 1)) {
            $errors['digest_hour'] = 'Choose Off or an hour from 0 to 23.';
        }
        if (isset($input['pause_all_email']) && !in_array($input['pause_all_email'], ['0', '1', 0, 1, false, true, ''], true)) {
            $errors['pause_all_email'] = 'Choose whether email is paused.';
        }
        if ($errors !== []) { throw new ValidationException($errors, $draft); }
        $hour = $hourRaw === '' || $hourRaw === 'off' ? null : (int) $hourRaw;
        $pause = $draft['pause_all_email'] === '1';
        $this->db->transaction(function () use ($viewer, $zone, $hour, $pause): void {
            $row = $this->users->findForUpdate($viewer->id());
            if ($row === null) { throw new NotFoundException('Account not found.'); }
            $oldZone = trim((string) ($row['timezone'] ?? '')) ?: 'UTC';
            $oldPause = $this->email->pauseAllEmail($viewer->id());
            $escalation = (!$pause && $oldPause) || ($hour !== null && (
                $row['digest_hour'] === null || $hour !== (int) $row['digest_hour'] || $zone !== $oldZone
            ));
            if ($escalation) { $this->writeGate->assertCanWrite(User::fromRow($row)); }
            $this->users->updateDigest($viewer->id(), $zone, $hour);
            $this->email->setPauseAllEmail($viewer->id(), $pause);
        });
    }
}
