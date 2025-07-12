<x-layout title="Транзакции">
    <div class="d-flex justify-content-between">
        <h1>Дополнительные оплаты</h1>
        <div>
            <a href="{{ route('extra-payments.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>

    <table class="table">
        <thead>
        <tr>
            <th>Участник</th>
            <th>Период</th>
            <th>Сумма</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($payments as $payment)
            <tr>
                <td>
                    @if ($payment->user->is_active)
                        <a href="{{ route('users.edit', $payment->user->id) }}">{{ $payment->user->full_name }}</a>
                    @else
                        {{ $payment->user->full_name }}
                    @endif
                </td>
                <td>{{ $payment->currentPayment->full_date }}</td>
                <td>{{ $payment->amount }}</td>
                <td>
                    <form action="{{ route('extra-payments.destroy', $payment->id) }}" method="POST" style="display:inline-block;">
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
