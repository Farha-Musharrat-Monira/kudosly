<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'space_id',
    'user_id',
    'name',
    'email',
    'body',
    'rating',
    'consent_to_share',
    'status',
    'is_favorite',
    'is_heart',
    'company_name',
    'social_url',
    'profile_photo',
])]
#[Hidden(['user_id'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HIDDEN = 'hidden';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'consent_to_share' => 'boolean',
            'is_favorite' => 'boolean',
            'is_heart' => 'boolean',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The disk that holds the customer's profile photo.
     *
     * The photo is private customer data, so it is stored on a disk that
     * never serves files over the web.
     */
    public const PHOTO_DISK = 'testimonial-photos';

    /**
     * The attributes a public submission is allowed to set.
     *
     * The owner, the moderation status and the owner's favourite and heart
     * flags are deliberately absent. A submission can never claim to belong
     * to another account, can never approve itself, and can never mark
     * itself as a favourite.
     *
     * @var list<string>
     */
    public const SUBMITTABLE = [
        'name',
        'email',
        'body',
        'rating',
        'consent_to_share',
        'company_name',
        'social_url',
    ];

    /**
     * Whether the testimonial has a stored profile photo.
     */
    public function hasPhoto(): bool
    {
        return filled($this->profile_photo);
    }

    /**
     * Whether this testimonial may appear on a public surface.
     *
     * Public visibility requires approval, a non-hidden state and explicit
     * sharing consent.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->status !== self::STATUS_HIDDEN
            && $this->consent_to_share;
    }

    /**
     * Limit a query to the testimonials that may appear on a public surface.
     *
     * The rules are applied in the database rather than in PHP, so the
     * visibility check cannot drift away from isPubliclyVisible(). A
     * testimonial that was hearted and then hidden or had its consent
     * withdrawn drops out of the result, because the conditions are
     * evaluated on every render rather than once when the heart was set.
     *
     * @param  Builder<Testimonial>  $query
     * @return Builder<Testimonial>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('consent_to_share', true);
    }

    /**
     * Limit a query to the testimonials the owner has put on the wall.
     *
     * @param  Builder<Testimonial>  $query
     * @return Builder<Testimonial>
     */
    public function scopeHearted(Builder $query): Builder
    {
        return $query->where('is_heart', true);
    }

    /**
     * The customer's social profile URL, when it is safe to link to.
     *
     * A customer supplies this value, so the scheme is checked before the
     * URL is ever rendered as a link. Only http and https are allowed, so a
     * javascript: or data: URL can never become a clickable link. The value
     * is returned exactly as stored; only the scheme is decided here.
     */
    public function safeSocialUrl(): ?string
    {
        $url = $this->social_url;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
