<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connexion · PharmaCare</title>
    <style>
        *,*::before,*::after{box-sizing:border-box}
        body{margin:0;padding:16px;font-family:system-ui;background:#fff7f0;color:#30231a;display:grid;place-items:center;min-height:100vh}
        .card{background:#fff;width:100%;max-width:420px;padding:16px 30px 18px;border-radius:16px;box-shadow:0 14px 38px #7a350018}
        .logo{display:block;width:52px;height:52px;object-fit:contain;margin:0 auto 2px}.brand{color:#f47a20;text-align:center;font-size:26px;line-height:1.1;font-weight:900}.sub{text-align:center;color:#705b4c;font-size:14px;line-height:1.3;margin:2px 0 8px}
        label{font-weight:650;display:block;margin:6px 0 4px}input{width:100%;height:48px;padding:0 14px;border:1px solid #d8c8bb;border-radius:12px;font-size:15px}
        .password-field{position:relative}.password-field input{padding-right:52px}.toggle-password{position:absolute;right:3px;top:3px;width:42px;height:42px;margin:0;padding:0;border:0;background:transparent;color:#705b4c;font-size:21px;cursor:pointer}
        .submit{width:100%;height:48px;margin-top:8px;padding:0 16px;border:0;border-radius:12px;background:#f47a20;color:#fff;font-weight:800;font-size:15px}
        .message{padding:11px;border-radius:8px;background:#eef8ee;color:#176b3a}.error{background:#fff0ee;color:#b42318}
        .links{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:6px;font-size:13px}.links a{color:#ad4a00;font-weight:700;text-decoration:none;text-align:right}
        .check{display:flex;gap:8px;align-items:center;font-weight:400}.check input{width:auto}
        @media(max-width:480px){.card{padding:14px 28px 16px}.links{align-items:flex-start}}
    </style>
    @include('components.auth-styles')
</head>
<body>
<main class="card">
    <img class="logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare">
    <div class="brand"><span style="color:#FF7A00">Pharma</span><span style="color:#16A34A">Care</span></div>
    <p class="sub">Putting Patients at the Heart of Every Supply.</p>
    @if(session('status'))<p class="message">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="message error">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label for="login">Adresse e-mail ou identifiant</label>
        <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
        <label for="password">Mot de passe</label>
        <div class="password-field">
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button id="toggle-password" class="toggle-password" type="button" aria-label="Afficher le mot de passe" title="Afficher le mot de passe">👁</button>
        </div>
        <div class="links">
            <label class="check"><input type="checkbox" name="remember"> Rester connecté</label>
            <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>
        <button class="submit">Se connecter</button>
    </form>
</main>
<script>
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    toggle.addEventListener('click', () => {
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        toggle.textContent = visible ? '👁' : '🙈';
        toggle.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
        toggle.title = visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe';
        password.focus();
        password.setSelectionRange(password.value.length, password.value.length);
    });
</script>
</body>
</html>
