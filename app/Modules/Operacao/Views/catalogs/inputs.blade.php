<label class="grow">Nome<input name="name" value="{{ $item?->name }}" required maxlength="255" class="block w-full rounded-md border border-stone-300 px-3 py-2"></label>
@if ($type === 'professional')
    <label>Usuário vinculado
        <select name="user_id" class="block rounded-md border border-stone-300 bg-white px-3 py-2">
            <option value="">Nenhum</option>
            @foreach ($members as $id => $name)<option value="{{ $id }}" @selected($item?->user_id === $id)>{{ $name }}</option>@endforeach
        </select>
    </label>
@endif
