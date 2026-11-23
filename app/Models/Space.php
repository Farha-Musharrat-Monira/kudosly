<?php

namespace App\Models;

use App\Policies\SpacePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'name',
    'slug',
    'title',
    'subtitle',
    'testimonial_request',
    'theme',
    'collect_rating',
    'is_published',
])]
#[Hidden(['user_id'])]
#[UsePolicy(SpacePolicy::class)]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    /**
     * The predefined page themes a space can use.
     *
     * @var array<string, string>
     */
    public const THEMES = [
        'minimal_light' => 'Minimal Light',
        'modern_dark' => 'Modern Dark',
        'soft_gradient' => 'Soft Gradient',
    ];

    public const DEFAULT_THEME = 'minimal_light';

    /**
     * The number of slug candidates tried before giving up.
     *
     * The retry loop is bounded so a pathological name cannot spin forever.
     */
    public const SLUG_ATTEMPTS = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collect_rating' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The account that owns this space.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The collection fields configured for this space.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(SpaceField::class)->orderBy('position');
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * The widget that embeds this space's testimonials.
     *
     * A space has exactly one widget. The rule is enforced by a unique
     * constraint on the widget's space column, so this relation can hold
     * at most one row even if the application is bypassed.
     */
    public function widget(): HasOne
    {
        return $this->hasOne(Widget::class);
    }

    /**
     * The display label for a theme key.
     */
    public static function themeLabel(string $theme): string
    {
        return self::THEMES[$theme] ?? self::THEMES[self::DEFAULT_THEME];
    }

    /**
     * Build a slug from a name that is not already taken.
     *
     * Slugs are globally unique because a space's public URL is /s/{slug}.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'space';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Create a space with a slug that is guaranteed to be unique.
     *
     * Two accounts can pick the same name at the same moment, so the slug
     * candidate is checked against the database and the insert is retried
     * when the unique index rejects it.
     *
     * Only a genuine unique violation on the slug column is retried. Any
     * other database error, and a unique violation on another column, is
     * rethrown so a real fault is never masked.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithUniqueSlug(array $attributes, string $name): self
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return static::query()->create([
                    ...$attributes,
                    'slug' => static::generateUniqueSlug($name),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $isSlugCollision = in_array('slug', $e->columns, true);

                if (! $isSlugCollision || $attempt >= static::SLUG_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * The public URL reserved for this space.
     */
    public function publicUrl(): string
    {
        return url('/s/'.$this->slug);
    }

    /**
     * The public URL a customer uses to leave a testimonial.
     *
     * The reserved page and the collection form are two different URLs. The
     * reserved page at /s/{slug} explains that the space is claimed, while
     * the collection form at /s/{slug}/submit is where a customer actually
     * sends a testimonial. An owner shares the collection URL, so this is
     * the URL the dashboard shows and copies.
     */
    public function collectionUrl(): string
    {
        return url('/s/'.$this->slug.'/submit');
    }

    /**
     * The public Wall of Love URL for this space.
     *
     * The slug is the same one the collection page uses, so the two public
     * URLs stay in step.
     */
    public function wallUrl(): string
    {
        return url('/wall/'.$this->slug);
    }

    /**
     * The testimonials that appear on this space's public wall.
     *
     * A testimonial is on the wall when the owner has hearted it and it is
     * publicly visible. Both conditions are applied in the query, so a
     * testimonial that is hidden or loses its consent leaves the wall
     * immediately rather than at the next moderation action.
     *
     * @return Builder<Testimonial>
     */
    public function wallTestimonials()
    {
        return $this->testimonials()
            ->hearted()
            ->publiclyVisible()
            ->orderByDesc('created_at');
    }

    /**
     * How many testimonials are on this space's public wall.
     */
    public function wallTestimonialCount(): int
    {
        return $this->wallTestimonials()->count();
    }

    public function testimonialCount(): int
    {
        return $this->testimonials()->count();
    }
}
