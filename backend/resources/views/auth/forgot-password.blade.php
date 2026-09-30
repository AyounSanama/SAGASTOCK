<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mot de passe oublié · PharmaCare</title>@include('components.auth-styles')@include('components.assets')
</head>
<body class="auth-page"><main class="auth-card" aria-labelledby="forgot-title">
 <header class="auth-brand"><img class="auth-logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare"><h1 id="forgot-title" class="auth-wordmark"><span>Pharma</span><strong>Care</strong></h1><p>Récupérez l’accès à votre compte en toute sécurité.</p></header>
 @if(session('status'))<div class="auth-alert auth-alert--success" role="status"><span class="material-symbols-outlined">check_circle</span><span>{{ session('status') }}</span></div>@endif
 @if($errors->any())<div class="auth-alert auth-alert--error" role="alert"><span class="material-symbols-outlined">error</span><span>{{ $errors->first() }}</span></div>@endif
 <form class="auth-form" method="post" action="{{ route('password.email') }}">@csrf
  <x-app-input name="email" label="Adresse e-mail" type="email" icon="mail" :value="old('email')" required autofocus autocomplete="email" hint="Un lien sécurisé sera envoyé si cette adresse est associée à un compte." />
  <x-app-button type="submit" icon="mark_email_read" expanded>Envoyer le lien</x-app-button>
  <x-app-button variant="text" :href="route('login')" icon="arrow_back" expanded>Retour à la connexion</x-app-button>
 </form>
</main></body></html>
