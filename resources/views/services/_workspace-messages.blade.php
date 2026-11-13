@if(session('success'))
    <p class="sg-message" role="status">{{ session('success') }}</p>
@endif
@if($errors->any())
    <ul class="sg-message sg-error" role="alert">
        @foreach($errors->getMessages() as $field => $messages)
            @php($line = str_starts_with($field, 'rows.') ? (int) explode('.', $field)[1] + 1 : null)
            @foreach($messages as $message)
                <li>@if($line)Linha {{ $line }} · @endif{{ $message }}</li>
            @endforeach
        @endforeach
    </ul>
@endif
