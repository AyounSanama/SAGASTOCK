<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mot de passe oublié · PharmaCare</title>
    @include('components.auth-styles')
</head>
<body>
<main class="card">
    <img class="logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare">
    <h1>Mot de passe oublié</h1>
    <p>Indiquez l’adresse e-mail associée à votre compte. Si elle existe, vous recevrez un lien sécurisé.</p>
    @if(session('status'))<p class="message">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="message error">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        <button style="width:100%;margin-top:18px">Envoyer le lien</button>
    </form>
    <a style="display:block;text-align:center;margin-top:18px" href="{{ route('login') }}">Retour à la connexion</a>
</main>
</body>
</html>
