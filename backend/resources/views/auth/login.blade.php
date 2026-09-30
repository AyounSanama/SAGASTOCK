<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connexion · PharmaCare</title>


    @include('components.auth-styles')
@include('components.assets')
</head>
<body class="auth-page">
<main class="auth-card" aria-labelledby="login-title">
    <header class="auth-brand">
        <img class="auth-logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare">
        <h1 id="login-title" class="auth-wordmark"><span>Pharma</span><strong>Care</strong></h1>
        <p>Putting Patients at the Heart of Every Supply.</p>
    </header>

    @if(session('status'))
        <div class="auth-alert auth-alert--success" role="status"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>{{ session('status') }}</span></div>
    @endif
    @if($errors->any())
        <div class="auth-alert auth-alert--error" role="alert"><span class="material-symbols-outlined" aria-hidden="true">error</span><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="auth-form" method="post" action="{{ route('login.store') }}">
        @csrf
        <x-app-input name="login" label="Adresse e-mail ou identifiant" icon="mail" :value="old('login')" required autofocus autocomplete="username" />

        <label class="app-field {{ $errors->has('password') ? 'is-error' : '' }}" for="password">
            <span class="app-field__label">Mot de passe <span class="app-field__required" aria-hidden="true">*</span></span>
            <span class="app-field__control app-field__control--icon auth-password-control">
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                <input class="app-field__input" id="password" name="password" type="password" required autocomplete="current-password">
                <button id="toggle-password" class="auth-password-toggle" type="button" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><span class="material-symbols-outlined" aria-hidden="true">visibility</span></button>
            </span>
        </label>

        <div class="auth-options">
            <label class="auth-remember"><input type="checkbox" name="remember"> <span>Rester connecté</span></label>
            <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>
        <x-app-button type="submit" icon="login" expanded>Se connecter</x-app-button>
    </form>
</main>
<script>
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    toggle.addEventListener('click', () => {
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        toggle.querySelector('.material-symbols-outlined').textContent = visible ? 'visibility' : 'visibility_off';
        const label = visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        password.focus();
        password.setSelectionRange(password.value.length, password.value.length);
    });
</script>
</body>
</html>
