<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Webhook Hub</title>

    {{-- A téma még a stíluslap előtt eldől, hogy ne villanjon fel a világos háttér. --}}
    <script>
        (function () {
            try {
                var mode = localStorage.getItem('webhookhub-theme') || 'system';
                var dark = mode === 'dark'
                    || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
