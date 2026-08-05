<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nouveau mot de passe · PharmaCare</title>
    @include('components.auth-styles')
</head>
<body>
<main class="card">
    <img class="logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare">
    <h1>Nouveau mot de passe</h1>
    <p>Choisissez au moins 12 caractères et évitez un mot de passe déjà utilisé ailleurs.</p>
    @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email',$email) }}" required>
        <label for="password">Nouveau mot de passe</label>
        <input id="password" name="password" type="password" minlength="12" required>
        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required>
        <button style="width:100%;margin-top:20px">Réinitialiser le mot de passe</button>
    </form>
</main>
</body>
</html>
