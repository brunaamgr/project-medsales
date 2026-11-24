@if (session('status'))
    <x-alert class="mb-4">{{ session('status') }}</x-alert>
@endif
@if ($errors->any())
    <x-alert tone="error" class="mb-4">Confira os campos destacados: {{ $errors->first() }}</x-alert>
@endif
