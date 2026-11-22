<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestimonialSubmissionController extends Controller
{
    /**
     * The minimum length of a testimonial body.
     */
    public const MINIMUM_BODY_LENGTH = 20;

    /**
     * The maximum size of an uploaded profile photo, in kilobytes.
     */
    public const MAXIMUM_PHOTO_SIZE = 2048;

    /**
     * The image types an uploaded profile photo may use.
     *
     * @var list<string>
     */
    public const ALLOWED_PHOTO_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Store a public testimonial submission.
     *
     * The space is looked up by slug and must be published. An unpublished
     * space is reported as missing so its existence and its public URL stay
     * private, and no submission can ever be accepted for it.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $space = Space::query()->where('slug', $slug)->first();

        if ($space === null || ! $space->is_published) {
            throw new NotFoundHttpException;
        }

        // The honeypot field is hidden from customers and must stay empty.
        // A filled field means a bot filled it in, so the submission is
        // discarded quietly rather than reported back to the sender.
        if (filled($request->input('website'))) {
            return redirect()
                ->route('spaces.public.thank-you', $space->slug)
                ->with('status', 'received');
        }

        $fields = $space->fields()->get()->keyBy('key');

        $validated = $request->validate(
            $this->rules($fields, $space),
            $this->messages(),
            $this->attributes(),
        );

        // A space cannot accept more testimonials than its plan allows.
        $this->ensureWithinQuota($space);

        $photoPath = $this->storePhoto($request);

        $testimonial = DB::transaction(function () use ($space, $validated, $photoPath): Testimonial {
            // The owner, the moderation status and the owner's own flags are
            // never taken from the request. They are set here so a crafted
            // request cannot approve itself or claim another account.
            return Testimonial::query()->create([
                'space_id' => $space->id,
                'user_id' => $space->user_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'body' => $validated['body'],
                'rating' => $validated['rating'] ?? null,
                'consent_to_share' => (bool) ($validated['consent_to_share'] ?? false),
                'status' => Testimonial::STATUS_PENDING,
                'company_name' => $validated['company_name'] ?? null,
                'social_url' => $validated['social_url'] ?? null,
                'profile_photo' => $photoPath,
            ]);
        });

        // The public page never shows a testimonial, so the customer is
        // taken straight to the thank you page.
        return redirect()
            ->route('spaces.public.thank-you', $space->slug)
            ->with('testimonial_id', $testimonial->id);
    }

    /**
     * The validation rules for a submission.
     *
     * Every rule is derived from the space's own configuration, so a field
     * that is not collected is never validated and a field that is required
     * is always enforced server-side.
     *
     * @param  Collection<string, SpaceField>  $fields
     * @return array<string, mixed>
     */
    protected function rules($fields, Space $space): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'body' => ['required', 'string', 'min:'.self::MINIMUM_BODY_LENGTH, 'max:5000'],
            'consent_to_share' => ['nullable', 'boolean'],
        ];

        // The rating is only collected when the space asks for it.
        if ($space->collect_rating) {
            $rules['rating'] = ['required', 'integer', 'between:1,5'];
        } else {
            $rules['rating'] = ['nullable', 'integer', 'between:1,5'];
        }

        $company = $fields->get('company_name');
        $social = $fields->get('social_url');
        $photo = $fields->get('profile_photo');

        if ($company?->enabled) {
            $rules['company_name'] = $company->required
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'];
        }

        if ($social?->enabled) {
            $rules['social_url'] = $social->required
                ? ['required', 'string', 'url', 'max:255']
                : ['nullable', 'string', 'url', 'max:255'];
        }

        if ($photo?->enabled) {
            $rules['profile_photo'] = array_merge(
                [$photo->required ? 'required' : 'nullable'],
                ['image', 'max:'.self::MAXIMUM_PHOTO_SIZE],
                [Rule::file()->types(self::ALLOWED_PHOTO_TYPES)],
            );
        }

        return $rules;
    }

    /**
     * The validation messages for a submission.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'body.required' => 'Please tell us what you think.',
            'body.min' => 'Please write a little more so we can read it properly.',
            'rating.required' => 'Please choose a star rating.',
            'rating.between' => 'Please choose a rating between 1 and 5 stars.',
            'profile_photo.image' => 'Your photo must be an image.',
            'profile_photo.max' => 'Your photo must be smaller than 2MB.',
        ];
    }

    /**
     * The friendly names used in validation messages.
     *
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'body' => 'testimonial',
            'company_name' => 'company name',
            'social_url' => 'social profile URL',
            'profile_photo' => 'photo',
            'consent_to_share' => 'sharing consent',
        ];
    }

    /**
     * Ensure the space has not reached its testimonial quota.
     */
    protected function ensureWithinQuota(Space $space): void
    {
        $owner = $space->owner;

        if ($owner === null) {
            return;
        }

        $used = $space->testimonials()->count();

        if ($used >= $owner->testimonialQuota()) {
            throw ValidationException::withMessages([
                'body' => [__('This space is not accepting new testimonials right now.')],
            ]);
        }
    }

    /**
     * Store the uploaded profile photo on the private disk.
     *
     * The photo is private customer data, so it is kept on a disk that does
     * not serve files over the web. The returned value is a path, never a
     * public URL.
     */
    protected function storePhoto(Request $request): ?string
    {
        $photo = $request->file('profile_photo');

        if ($photo === null || ! $photo->isValid()) {
            return null;
        }

        $extension = strtolower($photo->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_PHOTO_TYPES, true)) {
            return null;
        }

        return $photo->store('testimonials', Testimonial::PHOTO_DISK);
    }
}
