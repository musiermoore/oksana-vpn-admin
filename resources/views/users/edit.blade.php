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

    <div>
        <div class="d-flex justify-content-between">
            <h2>Конфиги</h2>
            <div>
                <a href="{{ route('configs.create') }}" class="btn btn-primary">Создать</a>
            </div>
        </div>

        <div class="d-flex flex-column mb-3" style="gap: 5px;">
            @foreach ($user->configs as $config)
                <div class="d-flex align-items-center justify-content-between" style="gap: 10px">
                    <a href="{{ route('configs.edit', $config->id) }}">
                        {{ $config->server->code }}: {{ $config->name }}
                    </a>

                    <div class="d-flex align-items-center" style="gap: 5px;">
                        <form
                            action="{{ route($config->is_active ? 'configs.disable' : 'configs.enable', $config->id) }}"
                            method="POST"
                            style="display:inline-block;"
                        >
                            @csrf
                            <button
                                type="submit"
                                @class(['btn btn-sm', $config->is_active ? 'btn-danger' : 'btn-success'])
                                title="{{ $config->is_active ? 'Отключить' : 'Включить' }} конфиг"
                                formmethod="POST"
                                formaction="{{ route($config->is_active ? 'configs.disable' : 'configs.enable', $config->id) }}"
                            >
                                <i
                                    @class(['fa-solid', $config->is_active ? 'fa-ban' : 'fa-heart-pulse'])
                                ></i>
                            </button>
                        </form>

                        <form action="{{ route('configs.destroy', $config->id) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between mb-3">
            <h2>Транзакции</h2>
            <div>
                <a href="{{ route('transactions.create') }}" class="btn btn-primary">Создать</a>
            </div>
        </div>

        <table class="table">
            <thead>
            <tr>
                <th>Сумма</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($user->transactions as $transaction)
                <tr>
                    <td>{{ $transaction->amount }}</td>
                    <td>{{ $transaction->formatted_created_at }}</td>
                    <td>
                        @if ($transaction->is_approved)
                            <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-warning btn-sm" target="_blank"><i class="fa-solid fa-pen-to-square"></i></a>
                            <form action="{{ route('transactions.destroy', $transaction->id) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('transactions.approve', $transaction->id) }}" style="display:inline-block;">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-success btn-sm"
                                    formmethod="POST"
                                    formaction="{{ route('transactions.approve', $transaction->id) }}"
                                >
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('transactions.decline', $transaction->id) }}" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm js-remove_confirmation"
                                    formaction="{{ route('transactions.decline', $transaction->id) }}"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
