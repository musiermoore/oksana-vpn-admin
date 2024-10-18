<x-layout title="Ограничения">
    <div class="d-flex justify-content-between mb-3">
        <h1>Ограничения</h1>
        <div>
            <a href="{{ route('limits.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Участник</th>
            <th>Конфиг</th>
            <th>Ограничения</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($configs as $config)
            <tr>
                <td>{{ $config->config->user->full_name }}</td>
                <td>{{ $config->config->name }}</td>
                <div class="d-flex flex-column" style="gap: 5px">
                    @foreach ($config->limits as $limit)
                        <div class="d-flex align-items-center justify-content-between" style="gap: 10px">
                            <div>
                                {{ $limit->amount }} Мбит/с
                            </div>
                            <div>
                                <form action="{{ route('limits.destroy', $limit->id) }}" method="POST" style="display:inline-block;">
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
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
