@props([
    'prefix' => 'new_address',
    'latitude' => null,
    'longitude' => null,
])

@php
    $mapConfig = [
        'enabled' => (bool) config('shipnest.map.enabled', true),
        'provider' => config('shipnest.map.provider', 'leaflet'),
        'googleKey' => config('shipnest.map.google_maps_api_key'),
        'lat' => (float) ($latitude ?? config('shipnest.map.default_lat', 23.8103)),
        'lng' => (float) ($longitude ?? config('shipnest.map.default_lng', 90.4125)),
        'zoom' => (int) config('shipnest.map.default_zoom', 12),
        'country' => config('shipnest.map.country_code', 'bd'),
        'prefix' => $prefix,
    ];
    $field = fn (string $name) => $prefix ? "{$prefix}[{$name}]" : $name;
@endphp

@if($mapConfig['enabled'])
<div class="sm:col-span-2 map-picker-root" data-map-picker-root data-map-config='@json($mapConfig)'>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.select_on_map') }}</label>
    <p class="text-xs text-gray-500 mb-2">{{ __('messages.map_hint') }}</p>

    <button type="button" data-map-expand class="map-picker-preview-btn group" aria-label="{{ __('messages.map_expand') }}">
        <div data-map-preview class="map-picker-preview-canvas"></div>
        <span class="map-picker-preview-overlay">
            <span class="map-picker-preview-label">{{ __('messages.map_expand') }}</span>
        </span>
    </button>

    <p data-map-coords class="text-xs text-gray-500 mt-1.5 hidden"></p>

    <input type="hidden" name="{{ $field('latitude') }}" value="{{ old($field('latitude'), $latitude) }}" data-map-lat>
    <input type="hidden" name="{{ $field('longitude') }}" value="{{ old($field('longitude'), $longitude) }}" data-map-lng>

    <div data-map-modal class="map-picker-modal" hidden>
        <div class="map-picker-modal-backdrop" data-map-close></div>
        <div class="map-picker-modal-panel" role="dialog" aria-modal="true" aria-label="{{ __('messages.select_on_map') }}">
            <div class="map-picker-modal-head">
                <h3 class="map-picker-modal-title">{{ __('messages.select_on_map') }}</h3>
                <button type="button" class="map-picker-modal-close" data-map-close aria-label="Close">&times;</button>
            </div>
            <p class="map-picker-modal-hint">{{ __('messages.map_modal_hint') }}</p>
            <div data-map-modal-canvas class="map-picker-modal-canvas"></div>
            <button type="button" class="btn-primary w-full mt-4" data-map-done>{{ __('messages.map_confirm') }}</button>
        </div>
    </div>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @endpush
@endonce
@endif
