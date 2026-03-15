<x-layout title="Конфиги">
    <div class="d-flex justify-content-between mb-3">
        <h1>Конфиги</h1>
        <div>
            <a href="{{ route('vless-configs.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <div>
        <a href="{{ route('configs.index') }}">WireGuard</a>
        <a href="{{ route('vless-configs.index') }}">VLESS</a>
    </div>
    @include('configs.config-table', ['configPath' => 'vless-configs', 'canChangeStatus' => false])
</x-layout>
