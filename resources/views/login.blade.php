<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Belépés – Webhook Hub</title>
    @vite(['resources/css/app.css'])
</head>
<body class="login-page">
    <form method="POST" action="/login" class="login-card">
        @csrf
        <h1>Webhook Hub</h1>

        @if ($errors->any())
            <p class="login-error">{{ $errors->first() }}</p>
        @endif

        <label for="email">E-mail cím</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Jelszó</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <label class="login-remember">
            <input type="checkbox" name="remember" value="1"> Maradjak bejelentkezve
        </label>

        <button type="submit">Belépés</button>
    </form>
</body>
</html>
