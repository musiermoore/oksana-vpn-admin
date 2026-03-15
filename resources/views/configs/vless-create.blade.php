<x-layout title="Создать конфиг">
    <h1>Создать конфиг</h1>
    <form action="{{ route('vless-configs.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="config_id">Конфиг</label>
            <select name="config_id" id="config_id" class="form-control" required>
                @foreach ($existingConfigs as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
