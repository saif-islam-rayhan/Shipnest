@props([
    'label',
    'name',
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'form-group']) }}>
    <label for="{{ $name }}" class="form-label{{ $required ? ' form-label-required' : '' }}">
        {{ $label }}
    </label>
    {{ $slot }}
    @if($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
