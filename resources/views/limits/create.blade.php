<x-layout title="Создание ограничения">
    <h1>Создание ограничения</h1>
    <form action="{{ route('limits.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="config_id">Конфиг</label>
            <select name="config_id" id="config_id" class="form-control" required>
                @foreach ($configs as $config)
                    <option value="{{ $config->id }}">
                        {{ $config->name }} - {{ $config->user->full_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Ограничение</label>
            <select name="amount" id="amount" class="form-control" required>
                @foreach ($speedLimits as $speedLimit)
                    <option value="{{ $speedLimit['amount'] }}">
                        {{ $speedLimit['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
