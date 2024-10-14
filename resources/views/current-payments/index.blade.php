<x-layout title="Периоды оплаты">
    <div class="d-flex justify-content-between mb-3">
        <h1>Периоды оплаты</h1>
        <div>
            <a href="{{ route('current-payments.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Начало</th>
            <th>Конец</th>
            <th>Сумма</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($currentPayments as $currentPayment)
            <tr>
                <td>{{ $currentPayment->start_date }}</td>
                <td>{{ $currentPayment->end_date }}</td>
                <td>{{ $currentPayment->amount }}</td>
                <td>
                    <a href="{{ route('current-payments.edit', $currentPayment->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('current-payments.destroy', $currentPayment->id) }}" method="POST" style="display:inline-block;">
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
