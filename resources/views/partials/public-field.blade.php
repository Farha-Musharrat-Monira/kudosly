@php
    /**
     * A single collected field on the public testimonial form.
     *
     * @var \App\Models\SpaceField $field
     * @var array<string, string> $theme
     */
    $key = $field->key;
    $label = $field->label;
    $required = $field->required;
    $inputId = 'field-'.$key;
    $describedBy = null;
@endphp

@if ($key === 'name')
    <div>
        <label for="{{ $inputId }}" class="block text-sm font-medium {{ $theme['heading'] }}">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>

        <input type="text" id="{{ $inputId }}" name="name" value="{{ old('name') }}"
            @if ($required) required @endif
            autocomplete="name"
            class="mt-1.5 block w-full rounded-lg border {{ $theme['field'] }} shadow-sm focus:ring-2 sm:text-sm {{ $theme['accent_ring'] }}">

        @error('name')
            <x-error class="mt-2">{{ $message }}</x-error>
        @enderror
    </div>
@elseif ($key === 'email')
    <div>
        <label for="{{ $inputId }}" class="block text-sm font-medium {{ $theme['heading'] }}">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>

        <input type="email" id="{{ $inputId }}" name="email" value="{{ old('email') }}"
            @if ($required) required @endif
            autocomplete="email"
            class="mt-1.5 block w-full rounded-lg border {{ $theme['field'] }} shadow-sm focus:ring-2 sm:text-sm {{ $theme['accent_ring'] }}">

        @error('email')
            <x-error class="mt-2">{{ $message }}</x-error>
        @enderror
    </div>
@elseif ($key === 'company_name')
    <div>
        <label for="{{ $inputId }}" class="block text-sm font-medium {{ $theme['heading'] }}">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>

        <input type="text" id="{{ $inputId }}" name="company_name" value="{{ old('company_name') }}"
            @if ($required) required @endif
            autocomplete="organization"
            class="mt-1.5 block w-full rounded-lg border {{ $theme['field'] }} shadow-sm focus:ring-2 sm:text-sm {{ $theme['accent_ring'] }}">

        @error('company_name')
            <x-error class="mt-2">{{ $message }}</x-error>
        @enderror
    </div>
@elseif ($key === 'social_url')
    <div>
        <label for="{{ $inputId }}" class="block text-sm font-medium {{ $theme['heading'] }}">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>

        <input type="url" id="{{ $inputId }}" name="social_url" value="{{ old('social_url') }}"
            @if ($required) required @endif
            placeholder="https://"
            class="mt-1.5 block w-full rounded-lg border {{ $theme['field'] }} shadow-sm placeholder:text-slate-400 focus:ring-2 sm:text-sm {{ $theme['accent_ring'] }}">

        @error('social_url')
            <x-error class="mt-2">{{ $message }}</x-error>
        @enderror
    </div>
@elseif ($key === 'profile_photo')
    <div>
        <label for="{{ $inputId }}" class="block text-sm font-medium {{ $theme['heading'] }}">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>

        <input type="file" id="{{ $inputId }}" name="profile_photo"
            @if ($required) required @endif
            accept="image/jpeg,image/png,image/webp"
            class="mt-1.5 block w-full text-sm {{ $theme['body'] }} file:mr-4 file:rounded-lg file:border-0 file:bg-slate-200 file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-slate-300">

        <p class="mt-1 text-xs {{ $theme['muted'] }}">JPG, PNG or WebP. Maximum 2MB.</p>

        @error('profile_photo')
            <x-error class="mt-2">{{ $message }}</x-error>
        @enderror
    </div>
@endif
