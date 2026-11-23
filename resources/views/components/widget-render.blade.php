@php
    /**
     * The rendered widget.
     *
     * This is the single template behind both the live preview in the
     * dashboard and the public embed. One template means the preview
     * cannot drift away from what a visitor actually sees.
     *
     * The markup is deliberately free of global utility class names: every
     * class carries the "kw-" prefix, and every rule in the stylesheet is
     * scoped to this widget's own id. That is what keeps the widget's
     * styling away from the host page, and the host page's styling away
     * from the widget.
     *
     * @var string $publicId       The widget's unique public identifier
     * @var string $layout         Either "masonry" or "carousel"
     * @var string $theme          Either "light" or "dark"
     * @var string $backgroundColor A validated #rrggbb colour
     * @var bool $hasAnimation     Whether the entrance animation is on
     * @var iterable<int, \App\Models\Testimonial> $testimonials
     */
    $isDark = $theme === 'dark';
    $isMasonry = $layout === 'masonry';
    $count = is_countable($testimonials) ? count($testimonials) : iterator_count($testimonials);
@endphp

<div
    id="kw-{{ $publicId }}"
    class="kw-root kw-theme-{{ $theme }} kw-layout-{{ $layout }}"
    data-layout="{{ $layout }}"
    data-theme="{{ $theme }}"
    style="background-color: {{ $backgroundColor }};"
>
    @if ($count === 0)
        <p class="kw-empty">No testimonials are available to show yet.</p>
    @else
        <div class="kw-track">
            @foreach ($testimonials as $testimonial)
                @php
                    /**
                     * The social link is only rendered when its scheme is
                     * safe. A customer supplies this value, so an unsafe
                     * scheme is dropped rather than linked.
                     */
                    $socialUrl = $testimonial->safeSocialUrl();
                @endphp

                <figure class="kw-card">
                    <blockquote class="kw-quote">
                        <p class="kw-body">{{ $testimonial->body }}</p>
                    </blockquote>

                    @if ($testimonial->rating !== null)
                        <span class="kw-rating" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5">
                            @for ($star = 1; $star <= 5; $star++)
                                <span aria-hidden="true">{{ $star <= $testimonial->rating ? '★' : '☆' }}</span>
                            @endfor
                        </span>
                    @endif

                    <figcaption class="kw-meta">
                        <span class="kw-name">{{ $testimonial->name }}</span>

                        @if ($testimonial->company_name)
                            <span class="kw-company">{{ $testimonial->company_name }}</span>
                        @endif

                        @if ($socialUrl)
                            <a
                                href="{{ $socialUrl }}"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="kw-link"
                            >View profile</a>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
</div>

<style>
    /*
     * The widget's own stylesheet.
     *
     * Every rule is scoped to this widget's own id, so nothing here can
     * match an element on the host page and no host rule can match an
     * element here. The inherited properties that a host page usually
     * imposes are reset explicitly, so the widget looks the same wherever
     * it is embedded.
     */
    #kw-{{ $publicId }} {
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        color: {{ $isDark ? '#f8fafc' : '#0f172a' }};
        font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 16px;
        line-height: 1.5;
        text-align: left;
    }

    #kw-{{ $publicId }} *,
    #kw-{{ $publicId }} *::before,
    #kw-{{ $publicId }} *::after {
        box-sizing: border-box;
    }

    #kw-{{ $publicId }} .kw-empty {
        margin: 0;
        padding: 24px;
        color: {{ $isDark ? '#cbd5e1' : '#475569' }};
        font-size: 14px;
        text-align: center;
    }

    #kw-{{ $publicId }} .kw-track {
        display: flex;
        gap: 16px;
        margin: 0;
        padding: 0;
    }

    /* Masonry: columns flow top to bottom, then across. */
    #kw-{{ $publicId }}.kw-layout-masonry .kw-track {
        display: block;
        column-gap: 16px;
        column-count: 3;
    }

    #kw-{{ $publicId }}.kw-layout-masonry .kw-card {
        display: block;
        width: 100%;
        margin: 0 0 16px;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* Carousel: a single scrolling row that snaps to each card. */
    #kw-{{ $publicId }}.kw-layout-carousel .kw-track {
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    #kw-{{ $publicId }}.kw-layout-carousel .kw-track::-webkit-scrollbar {
        display: none;
    }

    #kw-{{ $publicId }}.kw-layout-carousel .kw-card {
        flex: 0 0 auto;
        width: 32%;
        min-width: 240px;
        scroll-snap-align: start;
    }

    #kw-{{ $publicId }} .kw-card {
        padding: 20px;
        border: 1px solid {{ $isDark ? '#1e293b' : '#e2e8f0' }};
        border-radius: 12px;
        background-color: {{ $isDark ? '#0f172a' : '#ffffff' }};
        color: inherit;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    #kw-{{ $publicId }} .kw-quote {
        margin: 0;
        padding: 0;
        border: 0;
    }

    #kw-{{ $publicId }} .kw-body {
        margin: 0;
        color: {{ $isDark ? '#e2e8f0' : '#334155' }};
        font-size: 15px;
        line-height: 1.6;
        white-space: pre-line;
    }

    #kw-{{ $publicId }} .kw-rating {
        display: inline-block;
        margin-top: 12px;
        color: #f59e0b;
        font-size: 14px;
        letter-spacing: 1px;
    }

    #kw-{{ $publicId }} .kw-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid {{ $isDark ? '#1e293b' : '#e2e8f0' }};
    }

    #kw-{{ $publicId }} .kw-name {
        color: {{ $isDark ? '#f8fafc' : '#0f172a' }};
        font-size: 14px;
        font-weight: 600;
    }

    #kw-{{ $publicId }} .kw-company {
        color: {{ $isDark ? '#cbd5e1' : '#475569' }};
        font-size: 12px;
    }

    #kw-{{ $publicId }} .kw-link {
        color: {{ $isDark ? '#a5b4fc' : '#4f46e5' }};
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
    }

    #kw-{{ $publicId }} .kw-link:hover {
        text-decoration: underline;
    }

    @if ($hasAnimation)
        @keyframes kw-fade-up-{{ $publicId }} {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #kw-{{ $publicId }} .kw-card {
            animation: kw-fade-up-{{ $publicId }} 0.4s ease both;
        }

        #kw-{{ $publicId }} .kw-card:nth-child(2) { animation-delay: 0.05s; }
        #kw-{{ $publicId }} .kw-card:nth-child(3) { animation-delay: 0.1s; }
        #kw-{{ $publicId }} .kw-card:nth-child(4) { animation-delay: 0.15s; }
        #kw-{{ $publicId }} .kw-card:nth-child(5) { animation-delay: 0.2s; }
        #kw-{{ $publicId }} .kw-card:nth-child(6) { animation-delay: 0.25s; }
    @endif

    /*
     * A visitor who has asked their system for reduced motion never sees
     * the animation, whatever the owner chose. This rule is always
     * present, whether or not the animation is switched on.
     */
    @media (prefers-reduced-motion: reduce) {
        #kw-{{ $publicId }} .kw-card {
            animation: none !important;
            transition: none !important;
            scroll-behavior: auto !important;
        }
    }

    /* The layout follows the width of the widget's own container. */
    @container (max-width: 480px) {
        #kw-{{ $publicId }}.kw-layout-masonry .kw-track {
            column-count: 1;
        }

        #kw-{{ $publicId }}.kw-layout-carousel .kw-card {
            width: 85%;
            min-width: 220px;
        }
    }

    @container (min-width: 481px) and (max-width: 900px) {
        #kw-{{ $publicId }}.kw-layout-masonry .kw-track {
            column-count: 2;
        }

        #kw-{{ $publicId }}.kw-layout-carousel .kw-card {
            width: 45%;
            min-width: 220px;
        }
    }
</style>
