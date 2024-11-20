<x-layout title="Создание периода оплаты">
    <h1>Создание периода оплаты</h1>
    <form action="{{ route('current-payments.store') }}" method="POST">
        @csrf
        @php
            $subMonth = now()->day < 21 ? 1 : 0;
        @endphp
        <div class="form-group">
            <label for="start_date">Дата начала</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ now()->subMonths($subMonth)->format('Y-m-21') }}" />
        </div>
        <div class="form-group">
            <label for="end_date">Дата окончания</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ now()->addMonth()->subMonths($subMonth)->format('Y-m-21') }}" />
        </div>
        <div class="form-group">
            <label for="amount">Сумма</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" value="" required>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
