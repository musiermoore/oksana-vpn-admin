<x-layout title="Трафик">
    <form action="{{ route('wireguard.traffic') }}">
        <div class="form-group">
            <label for="server_id">Сервер</label>
            <select name="server_id" id="server_id" class="form-control">
                @foreach ($servers as $server)
                    <option
                        value="{{ $server->id }}"
                        @selected(!request()->server_id || $server->id == request()->server_id)
                    >{{ $server->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Время сервера (UTC)</label>
            <input type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d H:i') }}" readonly />
            <small>
                <a href="https://www.timeanddate.com/worldclock/converter.html?p1=374&p2=166&p3=tz_gmt" target="_blank">
                    Конвертор времени
                </a>
            </small>
        </div>

        <div class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control">
                <option value="">Не выбран</option>
                @foreach ($users as $user)
                    <option
                        value="{{ $user->id }}"
                        @selected($user->id == request()->user_id)
                    >{{ $user->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="start_date">Начало</label>
            <input
                type="datetime-local"
                class="form-control"
                id="start_date"
                name="start_date"
                value="{{ request()->query('start_date', now()->subMinutes(10)->format('Y-m-d H:i')) }}"
                required
            />
        </div>
        <div class="form-group">
            <label for="end_date">Конец</label>
            <input
                type="datetime-local"
                class="form-control"
                id="end_date"
                name="end_date"
                value="{{ request()->query('end_date', now()->format('Y-m-d H:i')) }}"
                required
            />
        </div>

        <button type="submit" class="btn btn-primary">Отфильтировать</button>
        <a href="{{ route('wireguard.traffic') }}" class="btn btn-primary">Сбросить</a>
    </form>

    <h1 class="mb-4">Конфиги ({{ count($peers) }})</h1>

    <div class="row">
        @foreach($peers as $peer)
            <div class="col-12 col-sm-6 col-md-4 col-xl-3 p-2">
                <div class="card h-100 w-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            {{ $peer['telegram'] }}
                        </h5>

                        <div class="card-text">
                            <strong>Конфиг:</strong> <br />
                            <div>
                                {{ $peer['name'] }}
                            </div>
                        </div>
                        <div class="card-text">
                            <strong>Трафик:</strong> <br />

                            @foreach($peer['config']->formatted_last_traffic ?? [] as $type => $amount)
                                <div>
                                    {{ $type === 'sent' ? 'Отправлено' : 'Получено' }} {{ $amount }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layout>
