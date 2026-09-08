@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'lb-error space-y-1']) }} style="list-style:none;padding:0;margin-top:6px">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
