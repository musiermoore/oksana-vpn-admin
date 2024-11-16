<x-layout title="Транзакции">
    <div class="d-flex justify-content-between mb-3">
        <h1>Транзакции</h1>
        <div>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>

    @if ($pendingTransactions->isNotEmpty())
        <h2>
            На рассмотрении
        </h2>

        <table class="table">
            <thead>
            <tr>
                <th>Участник</th>
                <th>Сумма</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
                @foreach ($pendingTransactions as $transaction)
                    <tr>
                        <td>
                            @if ($transaction->user->is_active)
                                <a href="{{ route('users.edit', $transaction->user->id) }}">{{ $transaction->user->full_name }}</a>
                            @else
                                {{ $transaction->user->full_name }}
                            @endif
                        </td>
                        <td>{{ $transaction->amount }}</td>
                        <td>{{ $transaction->formatted_created_at }}</td>
                        <td>
                            <form method="POST" action="{{ route('transactions.approve', $transaction->id) }}" style="display:inline-block;">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-success btn-sm"
                                    formmethod="POST"
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

                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>
            Принятые
        </h2>
    @endif

    <table class="table">
        <thead>
        <tr>
            <th>Участник</th>
            <th>Сумма</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($transactions as $transaction)
            <tr>
                <td>
                    @if ($transaction->user->is_active)
                        <a href="{{ route('users.edit', $transaction->user->id) }}">{{ $transaction->user->full_name }}</a>
                    @else
                        {{ $transaction->user->full_name }}
                    @endif
                </td>
                <td>{{ $transaction->amount }}</td>
                <td>{{ $transaction->formatted_created_at }}</td>
                <td>
                    <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('transactions.destroy', $transaction->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
