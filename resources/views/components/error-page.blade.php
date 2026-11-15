<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · Oakfield</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="oak-error-page">
    <main class="oak-error-card">
        <div class="oak-error-code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        @if(filled($detail ?? null))<p class="oak-error-detail">{{ $detail }}</p>@endif
        <a class="oak-btn oak-btn-primary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    </main>
</body>
</html>
