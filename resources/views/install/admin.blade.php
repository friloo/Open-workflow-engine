<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — Admin-Konto</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 3])

    @if ($errors->any())
        <div class="alert-error">
            <ul style="margin: 4px 0 0 18px; padding: 0;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <h2>Erstes Admin-Konto</h2>
    <p class="sub">Dieser Account hat Vollzugriff. Mit dieser Mail meldest du dich gleich an.</p>

    <form method="POST" action="{{ route('install.admin') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required autofocus>

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>

        <div class="row">
            <div>
                <label for="password">Passwort (min. 8 Zeichen)</label>
                <input id="password" name="password" type="password" required minlength="8">
            </div>
            <div>
                <label for="password_confirmation">Passwort wiederholen</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
            </div>
        </div>

        <div style="margin-top:20px; text-align: right;">
            <button type="submit" class="primary">Konto anlegen →</button>
        </div>
    </form>
</div></div>
</body>
</html>
