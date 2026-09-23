<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A newsletter subscriber (docs/domain/entities.md, `Lead`). Pending until the
 * double-opt-in link is clicked (`confirmed_at`), on every path — decision A-1,
 * BUG-011. Linked to an account only through the same e-mail address, as
 * historically (`User.lead`); there is no foreign key.
 *
 * @property string $id
 * @property string $email
 * @property string $name
 * @property string $source
 * @property string $consent_version
 * @property Carbon $requested_at
 * @property Carbon|null $confirmed_at
 */
#[Fillable(['email', 'name', 'source', 'consent_version', 'requested_at', 'confirmed_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, HasUuids;

    /** Where the consent was given (the three historical paths). */
    public const SOURCE_FORM = 'form';

    public const SOURCE_REGISTRATION = 'registration';

    public const SOURCE_PROFILE = 'profile';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
