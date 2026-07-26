<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\User\Models\User;

/**
 * MaterialPolicy — controls access to group material.
 *
 * Core business rule: paid material needs a live subscription. Access is a
 * window in time now, so a lapsed subscription locks the content again.
 */
class MaterialPolicy
{
    public function watch(User $user, GroupMaterial $material): bool
    {
        $material->loadMissing('group.assignment');
        $group = $material->group;

        if (! $group) {
            return false;
        }

        // Admins, and the teacher who owns the group, always have access.
        if ($user->isAdmin() || $user->id === $group->assignment?->teacher_id) {
            return true;
        }

        if ($material->is_free_preview) {
            return true;
        }

        return $user->hasActiveSubscriptionTo($group);
    }
}
