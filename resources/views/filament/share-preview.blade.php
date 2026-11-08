{{-- Preview of the card apps build when the link page is shared. The panel theme does not compile
     this module's classes, so it is styled inline. Each app crops and lays the card out its own way. --}}
@php
    $card = $this->sharePreview();
@endphp

<div data-share-preview style="position: sticky; top: 5rem; display: flex; flex-direction: column; gap: 0.75rem;">
    <div>
        <p style="font-weight: 600; font-size: 0.875rem;">{{ __('link-page::messages.share.preview.title') }}</p>
        <p style="font-size: 0.8125rem; opacity: 0.75;">{{ __('link-page::messages.share.preview.help') }}</p>
    </div>

    <figure style="margin: 0; max-width: 24rem; overflow: hidden; border-radius: 0.75rem; border: 1px solid rgba(128, 128, 128, 0.35);">
        <div style="aspect-ratio: 1.91 / 1; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: rgba(128, 128, 128, 0.18);">
            @if ($card['imageUrl'] !== null)
                <img src="{{ $card['imageUrl'] }}" alt="{{ __('link-page::messages.share.preview.image_alt') }}"
                     style="width: 100%; height: 100%; {{ $card['custom'] ? 'object-fit: cover;' : 'object-fit: contain; padding: 1rem;' }}">
            @else
                <span aria-hidden="true" style="font-size: 3rem; font-weight: 700; opacity: 0.6;">{{ $card['initial'] }}</span>
            @endif
        </div>
        <figcaption style="padding: 0.625rem 0.75rem; display: flex; flex-direction: column; gap: 0.125rem;">
            <span style="font-size: 0.75rem; opacity: 0.7;">{{ $card['host'] }}</span>
            <span style="font-size: 0.9375rem; font-weight: 600; overflow-wrap: anywhere;" data-share-preview-title>{{ $card['title'] }}</span>
            @if ($card['description'] !== '')
                <span style="font-size: 0.8125rem; opacity: 0.85; overflow-wrap: anywhere;" data-share-preview-description>{{ $card['description'] }}</span>
            @endif
        </figcaption>
    </figure>

    @unless ($card['custom'])
        <p style="font-size: 0.8125rem; opacity: 0.75;">{{ __('link-page::messages.share.preview.site_logo') }}</p>
    @endunless
</div>
