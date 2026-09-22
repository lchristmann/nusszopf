<?php

namespace App\Policies;

use App\Models\User;

/**
 * `docs/security/authorization-matrix.md`, "User (account / profile)": every
 * row that isn't public read is self-only, with no exception and no admin
 * override — there is no staff/admin role anywhere in the historical product.
 */
class UserPolicy
{
    /**
     * Upload/replace own avatar: self-only.
     */
    public function update(User $user, User $target): bool
    {
        return $user->is($target);
    }

    /**
     * Delete own account: self-only.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->is($target);
    }
}
