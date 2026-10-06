<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Открыть в Happ</title>
</head>
<body>
    <p>Открываем подписку в Happ…</p>
    <p><a href="{{ $intentUrl }}">Открыть в Happ</a></p>
    <script>
        window.location.replace(@json($intentUrl));
    </script>
</body>
</html>
