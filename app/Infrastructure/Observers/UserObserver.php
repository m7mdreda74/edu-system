<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\User\Models\User;

class UserObserver
{
    public function saved(User $user): void
    {
        cache()->forget("user.{$user->id}.roles");
    }

    /**
     * When a user is soft-deleted, deactivate their account.
     * Enrollments and payments are NOT deleted — they are audit records.
     */
    public function deleted(User $user): void
    {
        cache()->forget("user.{$user->id}.roles");
        $user->updateQuietly(['is_active' => false]);
    }
}
