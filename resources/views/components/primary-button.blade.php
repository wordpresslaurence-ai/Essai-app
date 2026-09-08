<button {{ $attributes->merge(['type' => 'submit', 'class' => 'lb-btn lb-btn-primary']) }}>
    {{ $slot }}
</button>
