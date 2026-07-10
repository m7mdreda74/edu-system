<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\User\Models\User;

class UserObserver
{
    /**
     * When a user is soft-deleted, deactivate their account.
     * Enrollments and payments are NOT deleted — they are audit records.
     */
    public function deleted(User $user): void
    {
        $user->updateQuietly(['is_active' => false]);
    }
}
