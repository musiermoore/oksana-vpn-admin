<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="{{ url('/') }}">Latvia VPN</a>
    @if (!empty($isAuthorized))
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="{{ url('/users') }}">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/configs') }}">Configs</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/user-tokens') }}">Tokens</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/transactions') }}">Transactions</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/current-payments') }}">Сколько платить</a></li>
            </ul>
        </div>
    @endif
</nav>
<div class="container mt-4">
    @yield('content')
</div>
</body>
</html>
