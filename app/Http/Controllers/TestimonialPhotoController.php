<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestimonialPhotoController extends Controller
{
    /**
     * The content types the private photo route is allowed to serve.
     *
     * The extension is read from the stored path rather than trusted from
     * the request, and only these four types may be served. A path with any
     * other extension is reported as missing.
     */
    protected const CONTENT_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * Show a customer's private profile photo to the space owner.
     *
     * A customer's photo is private data on a disk that is never served over
     * the web, so it is streamed through this authorized route instead. The
     * testimonial is resolved through the space that received it, and the
     * space must belong to the signed-in account. A testimonial that belongs
     * to another account is reported as missing rather than forbidden, so
     * the response reveals nothing about whether it exists.
     *
     * The storage path never leaves the server: the request carries only a
     * numeric testimonial id.
     */
    public function show(int $testimonial): StreamedResponse
    {
        $record = Testimonial::query()
            ->whereHas('space', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($testimonial);

        $path = $record->profile_photo;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $contentType = self::CONTENT_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($contentType === null) {
            abort(404);
        }

        $disk = Storage::disk(Testimonial::PHOTO_DISK);

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
