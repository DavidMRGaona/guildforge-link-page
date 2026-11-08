{{-- Live preview of the link page appearance. The panel theme does not compile this module's
     classes, so it is styled inline with values AppearancePreview builds from checked input. --}}
@php
    $preview = $this->appearancePreview();
@endphp

<div style="position: sticky; top: 5rem; display: flex; flex-direction: column; gap: 0.75rem;">
    <div>
        <p style="font-weight: 600; font-size: 0.875rem;">{{ __('link-page::messages.appearance.preview.title') }}</p>
        <p style="font-size: 0.8125rem; opacity: 0.75;">{{ __('link-page::messages.appearance.preview.help') }}</p>
        @if (! $preview['valid'])
            <p style="font-size: 0.8125rem; color: #b45309;" role="status">{{ __('link-page::messages.appearance.preview.invalid') }}</p>
        @elseif ($preview['siteColors'])
            <p style="font-size: 0.8125rem; opacity: 0.75;">{{ __('link-page::messages.appearance.preview.site_colors') }}</p>
        @endif
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
        @foreach ($preview['phones'] as $phone)
            <figure style="flex: 1 1 13rem; max-width: 17rem; margin: 0;" data-preview="{{ $phone['set'] }}">
                <figcaption style="font-size: 0.75rem; font-weight: 600; margin-bottom: 0.375rem;">{{ $phone['label'] }}</figcaption>
                <div style="position: relative; overflow: hidden; isolation: isolate; border-radius: 1.5rem; border: 6px solid #1c1917; min-height: 26rem; {{ $phone['page'] }}">
                    @if ($phone['overlay'] !== null)
                        <div aria-hidden="true" style="position: absolute; inset: 0; z-index: -1; {{ $phone['overlay'] }}"></div>
                    @endif
                    <div style="display: flex; flex-direction: column; gap: 0.875rem; padding: 1.25rem 0.875rem;">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.375rem; text-align: center;">
                            @if ($preview['logoUrl'] !== null)
                                <img src="{{ $preview['logoUrl'] }}" alt="" width="56" height="56"
                                     style="width: 3.5rem; height: 3.5rem; {{ $preview['logoCustom'] ? 'object-fit: cover;' : 'object-fit: contain; padding: 0.25rem; background-color: '.($preview['logoIsDark'] ? '#1c1917' : '#ffffff').';' }} {{ $phone['logo'] }}">
                            @else
                                <span aria-hidden="true" style="display: flex; width: 3.5rem; height: 3.5rem; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; {{ $phone['initial'] }}">{{ $preview['initial'] }}</span>
                            @endif
                            <span style="font-size: 1rem; font-weight: 700; {{ $phone['text'] }}">{{ $preview['name'] }}</span>
                            @if ($preview['intro'] !== null)
                                <span style="font-size: 0.75rem; white-space: pre-line; {{ $phone['text'] }}">{{ \Illuminate\Support\Str::limit($preview['intro'], 120) }}</span>
                            @endif
                        </div>
                        <span style="font-size: 0.8125rem; font-weight: 600; {{ $phone['text'] }}">{{ __('link-page::messages.appearance.preview.upcoming') }}</span>
                        @foreach ([[__('link-page::messages.appearance.preview.sample_event'), true], [__('link-page::messages.appearance.preview.sample_event_2'), false]] as [$title, $pinned])
                            <div style="display: flex; flex-direction: column; padding: 0.5rem 0.75rem; font-size: 0.8125rem; {{ $phone['button'] }}">
                                <span style="display: flex; align-items: center; gap: 0.375rem; font-weight: 600;">
                                    @if ($pinned)
                                        <span style="border-radius: 9999px; padding: 0 0.375rem; font-size: 0.625rem; {{ $phone['badge'] }}">{{ __('link-page::messages.appearance.preview.pinned') }}</span>
                                    @endif
                                    {{ $title }}
                                </span>
                                <span style="font-size: 0.6875rem; {{ $phone['meta'] }}">{{ __('link-page::messages.appearance.preview.sample_meta') }}</span>
                            </div>
                        @endforeach
                        <span style="font-size: 0.6875rem; text-align: center; text-decoration: underline; {{ $phone['text'] }}">{{ __('link-page::messages.appearance.preview.visit_site', ['name' => $preview['name']]) }}</span>
                    </div>
                </div>

                @if ($phone['checks'] !== [])
                    <dl style="margin-top: 0.5rem; display: grid; grid-template-columns: 1fr auto; gap: 0.125rem 0.5rem; font-size: 0.75rem;" aria-label="{{ __('link-page::messages.appearance.preview.contrast_title') }}">
                        @foreach ($phone['checks'] as $check)
                            <dt>{{ $check['label'] }}</dt>
                            <dd style="margin: 0; font-variant-numeric: tabular-nums; font-weight: 600; color: {{ $check['passes'] ? '#15803d' : '#b91c1c' }};">
                                {{ $check['ratio'] }}:1
                                <span style="font-weight: 400;">({{ $check['passes'] ? __('link-page::messages.appearance.preview.passes') : __('link-page::messages.appearance.preview.fails') }}, ≥ {{ $check['minimum'] }})</span>
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </figure>
        @endforeach
    </div>
</div>
