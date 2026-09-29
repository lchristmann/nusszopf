<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Demo;

/**
 * `docs/security/authorization-matrix.md`, "User (account / profile)": every
 * row that isn't public read is self-only, with no exception and no admin
 * override — there is no staff/admin role anywhere in the historical product.
 */
class UserPolicy
{
    /**
     * Upload/replace own avatar: self-only. The shared demo account is the one exception: it belongs to every
     * visitor at once, so nobody may change it (docs/deployment/demo.md).
     */
    public function update(User $user, User $target): bool
    {
        return $user->is($target) && ! Demo::isDemoUser($target);
    }

    /**
     * Delete own account: self-only, and never the shared demo account.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->is($target) && ! Demo::isDemoUser($target);
    }
}
