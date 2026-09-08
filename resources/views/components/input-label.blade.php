@props(['value'])

<label {{ $attributes->merge(['class' => 'lb-label']) }}>
    {{ $value ?? $slot }}
</label>
