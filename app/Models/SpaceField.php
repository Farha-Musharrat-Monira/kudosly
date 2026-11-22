<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// The space relation sets the foreign key, so it is never mass assigned.
#[Fillable(['key', 'label', 'enabled', 'required', 'position'])]
class SpaceField extends Model
{
    /** @use HasFactory<SpaceFieldFactory> */
    use HasFactory;

    /**
     * The predefined collection fields. Name and Email are always collected
     * and always required; the remaining fields are opt-in.
     *
     * @var array<string, array{label: string, enabled: bool, required: bool}>
     */
    public const KEYS = [
        'name' => ['label' => 'Name', 'enabled' => true, 'required' => true],
        'email' => ['label' => 'Email', 'enabled' => true, 'required' => true],
        'company_name' => ['label' => 'Company Name', 'enabled' => false, 'required' => false],
        'social_url' => ['label' => 'Social Profile URL', 'enabled' => false, 'required' => false],
        'profile_photo' => ['label' => 'Profile Photo', 'enabled' => false, 'required' => false],
    ];

    /**
     * The fields that are always collected and always required.
     *
     * @var list<string>
     */
    public const ALWAYS_ON = ['name', 'email'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Whether the field is one of the always-collected ones.
     */
    public function isAlwaysOn(): bool
    {
        return in_array($this->key, self::ALWAYS_ON, true);
    }

    /**
     * Create the default set of fields for a space.
     */
    public static function defaultsFor(Space $space): void
    {
        $position = 0;

        foreach (self::KEYS as $key => $config) {
            $space->fields()->create([
                'key' => $key,
                'label' => $config['label'],
                'enabled' => $config['enabled'],
                'required' => $config['required'],
                'position' => $position++,
            ]);
        }
    }
}
