@props(['userToken', 'password'])

@foreach ($userToken->user->configs as $key => $config)
    <h4>
        Конфиг <b>{{ $config->name }}</b>
    </h4>
    <div>
        @php
            $params = ['userToken' => $userToken->token, 'config' => $config->id, 'password' => $password]
        @endphp

        <a href="{{ route('users.configs.qr-code', $params) }}" target="_blank">QR-Code</a>
        <a href="{{ route('users.configs.download', $params) }}">Скачать</a>
    </div>
@endforeach
