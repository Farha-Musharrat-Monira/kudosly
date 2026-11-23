<?php

namespace App\Models;

use App\Policies\WidgetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * An embeddable widget.
 *
 * A widget shows a space's testimonials somewhere else: on a customer's
 * own website, in an iframe or through the JavaScript loader. It carries
 * the settings that decide how those testimonials look, and it owns a
 * public identifier that is safe to publish.
 */
#[Fillable([
    'space_id',
    'layout',
    'theme',
    'background_color',
    'has_animation',
    'display_limit',
])]
#[Hidden(['space_id'])]
#[UsePolicy(WidgetPolicy::class)]
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /**
     * The layouts a widget can use.
     *
     * @var array<string, string>
     */
    public const LAYOUTS = [
        'masonry' => 'Masonry',
        'carousel' => 'Carousel',
    ];

    /**
     * The themes a widget can use.
     *
     * These are the widget's own themes rather than the space themes: a
     * widget sits inside somebody else's page, so it carries its own
     * light or dark surface.
     *
     * @var array<string, string>
     */
    public const THEMES = [
        'light' => 'Light',
        'dark' => 'Dark',
    ];

    public const DEFAULT_LAYOUT = 'masonry';

    public const DEFAULT_THEME = 'light';

    public const DEFAULT_BACKGROUND_COLOR = '#ffffff';

    /**
     * The bounds on the display limit.
     *
     * The limit is how many testimonials the widget shows. The lower
     * bound keeps the widget from being empty, and the upper bound keeps
     * a single embed from pulling an unreasonable number of rows.
     */
    public const MIN_DISPLAY_LIMIT = 1;

    public const MAX_DISPLAY_LIMIT = 50;

    public const DEFAULT_DISPLAY_LIMIT = 12;

    /**
     * The background colours offered in the picker.
     *
     * The owner may still type any valid hex colour; these are the
     * presets.
     *
     * @var list<string>
     */
    public const BACKGROUND_COLORS = [
        '#ffffff',
        '#f8fafc',
        '#f5f3ff',
        '#ecfdf5',
        '#fef3c7',
        '#fff7ed',
        '#eef2ff',
        '#0f172a',
    ];

    /**
     * The number of bytes behind a public identifier.
     *
     * Sixteen bytes are 128 bits of entropy, which is far beyond what is
     * needed to make a public URL unguessable.
     */
    public const PUBLIC_ID_BYTES = 16;

    /**
     * The number of times a genuine public identifier collision is
     * retried before the failure is allowed to surface.
     */
    public const PUBLIC_ID_ATTEMPTS = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_animation' => 'boolean',
            'display_limit' => 'integer',
        ];
    }

    /**
     * The space whose testimonials this widget displays.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * The display label for this widget's layout.
     */
    public function layoutLabel(): string
    {
        return self::LAYOUTS[$this->layout] ?? self::LAYOUTS[self::DEFAULT_LAYOUT];
    }

    /**
     * The display label for this widget's theme.
     */
    public function themeLabel(): string
    {
        return self::THEMES[$this->theme] ?? self::THEMES[self::DEFAULT_THEME];
    }

    /**
     * Build a cryptographically secure public identifier.
     *
     * The identifier is hexadecimal, which keeps it safe in a URL path,
     * in an HTML attribute and in a CSS id selector without any escaping.
     * A string helper would be simpler but would not guarantee that
     * character set.
     */
    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(self::PUBLIC_ID_BYTES));
    }

    /**
     * Create a widget with a public identifier that is guaranteed unique.
     *
     * The identifier is generated from a cryptographic random source, so
     * a collision is extraordinarily unlikely. It is still handled: the
     * insert is retried with a fresh identifier when the unique index
     * rejects it.
     *
     * Only a collision on the public identifier is retried. A unique
     * violation on the space means the space already has a widget, which
     * is a rule the application enforces before it gets this far, so that
     * failure is rethrown rather than silently retried.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithPublicId(array $attributes): self
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $widget = new self($attributes);

                // The public identifier is deliberately not fillable, so it
                // is assigned here and can never be set by a request.
                $widget->forceFill([
                    'public_id' => static::generatePublicId(),
                ]);

                $widget->save();

                return $widget;
            } catch (UniqueConstraintViolationException $e) {
                if (! static::isPublicIdCollision($e) || $attempt >= static::PUBLIC_ID_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Whether a unique constraint failure was caused by the public
     * identifier.
     *
     * The two database engines report the failure differently. SQLite
     * names the columns and leaves the index name empty, while MySQL
     * names the index and leaves the column list empty. Both are checked
     * so the retry works whichever engine is in use.
     */
    protected static function isPublicIdCollision(UniqueConstraintViolationException $e): bool
    {
        if (in_array('public_id', $e->columns, true)) {
            return true;
        }

        return $e->index !== null && str_contains($e->index, 'public_id');
    }

    /**
     * The testimonials this widget shows.
     *
     * Only approved, consented and visible testimonials may be rendered
     * publicly, so the same rule the Wall of Love uses is applied here.
     * The query is built in the database rather than in PHP, so the
     * check cannot drift away from the model's own rule.
     *
     * The relation is returned rather than a plain builder, because the
     * scopes are chained onto the space's own relation.
     */
    public function visibleTestimonials(): HasMany
    {
        return $this->space->testimonials()
            ->publiclyVisible()
            ->orderByDesc('created_at')
            ->limit($this->effectiveDisplayLimit());
    }

    /**
     * The display limit, held inside its allowed range.
     */
    public function effectiveDisplayLimit(): int
    {
        $limit = (int) $this->display_limit;

        if ($limit < self::MIN_DISPLAY_LIMIT) {
            return self::MIN_DISPLAY_LIMIT;
        }

        if ($limit > self::MAX_DISPLAY_LIMIT) {
            return self::MAX_DISPLAY_LIMIT;
        }

        return $limit;
    }

    /**
     * The public URL of the standalone widget page.
     */
    public function publicUrl(): string
    {
        return url('/w/'.$this->public_id);
    }

    /**
     * The public URL of the embeddable fragment.
     */
    public function embedUrl(): string
    {
        return url('/widget/'.$this->public_id.'/embed');
    }

    /**
     * The copyable iframe snippet for this widget.
     *
     * The snippet is built on the server, so the owner copies it exactly
     * as it is rather than assembling it in the browser.
     */
    public function iframeSnippet(): string
    {
        return '<iframe src="'.$this->embedUrl().'" width="100%" height="400" style="border:0" loading="lazy" title="Customer testimonials"></iframe>';
    }

    /**
     * The copyable JavaScript snippet for this widget.
     *
     * The loader is served from this application's own origin, so the
     * loader can always work out where to fetch the fragment from.
     */
    public function scriptSnippet(): string
    {
        return '<script src="'.url('/widget.js').'" data-widget="'.$this->public_id.'" async></script>';
    }
}
