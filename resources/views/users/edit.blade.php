<x-layout title="Редактирование участника">
    <h1>Редактирование участника</h1>
    <form action="{{ route('users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">Имя</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
        </div>
        <div class="form-group">
            <label for="telegram">Telegram</label>
            <input type="text" name="telegram" id="telegram" class="form-control" value="{{ old('telegram', $user->telegram) }}" required>
        </div>
        <div class="form-group">
            <label for="extra_payment">Доп. оплата</label>
            <input type="number" name="extra_payment" id="extra_payment" class="form-control" value="{{ old('extra_payment', $user->extra_payment) }}" required>
        </div>
        <div class="form-group">
            <label for="description">Описание</label>
            <textarea name="description" id="description" class="form-control">{{ old('description', $user->description) }}</textarea>
        </div>
        <div class="form-group">
            <label for="join_at">Дата присоединения</label>
            <select name="join_at" id="join_at" class="form-control">
                @foreach ($payments as $payment)
                    <option
                        value="{{ $payment->start_date }}"
                        @selected(old('join_at', $user->join_at) === $payment->start_date)
                    >
                        {{ $payment->formatted_start_date }} ({{ $payment->amount }}₽)
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
