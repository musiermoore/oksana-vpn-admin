@props([
    'configPath',
    'canChangeStatus' => true
])

<table class="table">
    <thead>
    <tr>
        <th>Участник</th>
        <th>Конфиги</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($users as $user)
        <tr>
            <td>
                @if ($user->is_active)
                    <a href="{{ route('users.edit', $user->id) }}">{{ $user->full_name }}</a>
                @else
                    {{ $user->full_name }}
                @endif
            </td>
            <td>
                <div class="d-flex flex-column" style="gap: 5px">
                    @php
                    $configs = $configPath === 'vless-configs' ? $user->vlessConfigs : $user->configs
                    @endphp
                    @foreach ($configs as $config)
                        <div class="d-flex align-items-center justify-content-between" style="gap: 10px">
                            <a href="{{ route($configPath . '.edit', $config->id) }}">
                                {{ $config->server->code }}: {{ $config->name }}
                            </a>

                            <div class="d-flex align-items-center" style="gap: 5px;">
                                @if ($canChangeStatus)
                                    <form
                                        action="{{ route($config->is_active ? $configPath . '.disable' : $configPath . '.enable', $config->id) }}"
                                        method="POST"
                                        style="display:inline-block;"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            @class(['btn btn-sm', $config->is_active ? 'btn-danger' : 'btn-success'])
                                            title="{{ $config->is_active ? 'Отключить' : 'Включить' }} конфиг"
                                        >
                                            <i
                                                @class(['fa-solid', $config->is_active ? 'fa-ban' : 'fa-heart-pulse'])
                                            ></i>
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route($configPath . '.destroy', $config->id) }}" method="POST" style="display:inline-block;">
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
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
