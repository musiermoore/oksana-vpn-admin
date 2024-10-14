@extends('layouts.app')

@section('title', 'Активные подключения')

@section('content')

    @foreach($peers as $key => $peerType)
        <h1 class="mb-4">{{ $key === 'active' ? 'Активные' : 'Оффлайн' }} ({{ count($peerType) }})</h1>
        <div class="row">
            @foreach($peerType as $peer)
                <div class="col-12 col-sm-6 col-md-4 col-xl-3 p-2">
                    <div class="card h-100 w-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $peer['telegram'] }}</h5>
                            <p class="card-text">
                                <strong>Последняя активность:</strong> <br />
                                {{ $peer['latest_handshake'] }}
                            </p>
                            <p class="card-text">
                                <strong>Трафика использовано (Всего):</strong> <br />
                                {{ $peer['transfer'] }}
                            </p>
                            @if (!empty($peer['config']->last_traffic))
                                <p class="card-text">
                                    <strong>Трафика использовано (За 10 мин):</strong> <br />

                                    @foreach($peer['config']->formatted_last_traffic ?? [] as $type => $amount)
                                        <div>
                                            {{ $type === 'sent' ? 'Отправлено' : 'Получено' }} {{ $amount }}
                                        </div>
                                    @endforeach
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
