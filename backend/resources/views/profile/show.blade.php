@extends('layouts.portal')
@section('title','Mon profil · PharmaCare')
@section('page-title','Mon profil')
@section('content')
<main class="profile-page">
 <x-app-page-header title="Mon profil" description="Gérez vos informations personnelles, votre langue et votre mot de passe." />
 @if(session('status'))<div class="profile-alert profile-alert--success" role="status"><span class="material-symbols-outlined">check_circle</span><span>{{ session('status') }}</span></div>@endif
 @if($errors->any())<div class="profile-alert profile-alert--error" role="alert"><span class="material-symbols-outlined">error</span><span>{{ $errors->first() }}</span></div>@endif
 <div class="profile-grid">
  <section class="profile-card"><header><span class="material-symbols-outlined">person</span><div><h2>Informations personnelles</h2><p>Informations autorisées de votre compte.</p></div></header>
   <form class="profile-form" method="post" action="{{ route('profile.update') }}">@csrf @method('PUT')
    <div class="profile-form-grid"><x-app-input name="first_name" label="Prénom" :value="$user->first_name ?: explode(' ',$user->name)[0]" required /><x-app-input name="last_name" label="Nom" :value="$user->last_name ?: trim(str_replace(explode(' ',$user->name)[0],'',$user->name))" required /><x-app-input name="username" label="Identifiant" icon="alternate_email" :value="$user->username" required /><x-app-input name="email" label="Adresse e-mail" type="email" icon="mail" :value="$user->email" required autocomplete="email" /><x-app-input name="phone" label="Téléphone" icon="phone" :value="$user->phone" autocomplete="tel" /></div>
    <label class="app-field" for="preferred_locale"><span class="app-field__label">Langue de l’interface</span><span class="app-field__control"><select class="app-field__input" id="preferred_locale" name="preferred_locale" required>@foreach($supportedLanguages as $locale=>$label)<option value="{{ $locale }}" @selected(old('preferred_locale',$user->preferred_locale ?: 'fr')===$locale)>{{ $label }}</option>@endforeach</select></span><span class="app-field__help">Seules les langues entièrement prises en charge sont proposées.</span></label>
    <div class="profile-actions"><x-app-button type="submit" icon="save">Enregistrer les modifications</x-app-button></div>
   </form>
  </section>
  @if(config('pharmacare_v1.features.dark_mode'))
  {{-- Le bouton lune / soleil de la barre du haut bascule Clair / Sombre ; « Système » se choisit ici. --}}
  @php($themePreference = $user->theme_preference ?: 'light')
  <section class="profile-card"><header><span class="material-symbols-outlined">contrast</span><div><h2>Mode d’affichage</h2><p>« Système » suit le réglage de votre ordinateur ou de votre téléphone.</p></div></header>
   <form class="profile-form profile-theme" method="post" action="{{ route('profile.theme') }}" data-theme-switch data-theme-preference="{{ $themePreference }}">@csrf
    <div class="profile-theme-options" role="group" aria-label="Mode d’affichage">
     @foreach(['light' => ['light_mode', 'Clair'], 'dark' => ['dark_mode', 'Sombre'], 'system' => ['contrast', 'Système']] as $value => [$icon, $label])
      <button class="profile-theme-option {{ $themePreference === $value ? 'is-active' : '' }}" type="submit" name="theme" value="{{ $value }}" data-theme-option aria-pressed="{{ $themePreference === $value ? 'true' : 'false' }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>{{ $label }}</button>
     @endforeach
    </div>
   </form>
  </section>
  @endif
  <section class="profile-card"><header><span class="material-symbols-outlined">lock</span><div><h2>Modifier le mot de passe</h2><p>Utilisez un mot de passe robuste et personnel.</p></div></header>
   <form class="profile-form" method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')
    @foreach([['current_password','Mot de passe actuel','current-password'],['password','Nouveau mot de passe','new-password'],['password_confirmation','Confirmation','new-password']] as [$name,$label,$autocomplete])
     <label class="app-field {{ $errors->has($name)?'is-error':'' }}" for="{{ $name }}"><span class="app-field__label">{{ $label }} <span class="app-field__required">*</span></span><span class="app-field__control app-field__control--icon profile-password"><span class="material-symbols-outlined">lock</span><input class="app-field__input" id="{{ $name }}" name="{{ $name }}" type="password" required minlength="6" autocomplete="{{ $autocomplete }}"><button type="button" data-password-toggle="{{ $name }}" aria-label="Afficher le mot de passe"><span class="material-symbols-outlined">visibility</span></button></span></label>
    @endforeach
    <p class="profile-help">Au moins 6 caractères avec majuscule, minuscule, chiffre et symbole.</p><div class="profile-actions"><x-app-button type="submit" icon="key">Modifier le mot de passe</x-app-button></div>
   </form>
  </section>
 </div>
</main>
@endsection
@push('styles')<style>
.profile-page{display:grid;gap:18px}.profile-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:18px;align-items:start}.profile-card{padding:22px;background:var(--pc-color-surface);border:1px solid var(--pc-color-border);border-radius:var(--pc-radius-lg);box-shadow:var(--pc-shadow-card)}.profile-card>header{display:flex;align-items:center;gap:12px;margin-bottom:20px}.profile-card>header>span{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:var(--pc-color-primary-soft);color:var(--pc-color-primary-soft-text)}.profile-card h2,.profile-card p{margin:0}.profile-card h2{font-size:18px}.profile-card header p,.profile-help{margin-top:3px;color:var(--pc-color-text-muted);font-size:12px}.profile-form{display:grid;gap:16px}.profile-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.profile-actions{display:flex;justify-content:flex-end}.profile-alert{display:flex;align-items:center;gap:8px;padding:12px 14px;border-radius:12px}.profile-alert--success{background:var(--pc-status-success-bg);color:var(--pc-color-success)}.profile-alert--error{background:var(--pc-status-danger-bg);color:var(--pc-color-danger)}.profile-password .app-field__input{padding-right:52px}.profile-password button{position:absolute;right:4px;top:50%;translate:0 -50%;width:40px;height:40px;display:grid;place-items:center;border:0;border-radius:10px;background:transparent;color:var(--pc-color-text-muted);cursor:pointer}.profile-password button:hover{background:var(--pc-color-primary-soft);color:var(--pc-color-primary-soft-text)}
@media(max-width:960px){.profile-grid{grid-template-columns:1fr}}@media(max-width:620px){.profile-form-grid{grid-template-columns:1fr}.profile-card{padding:17px}.profile-actions .app-button{width:100%}}
</style>@endpush
@push('scripts')<script>document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.passwordToggle);const visible=input.type==='text';input.type=visible?'password':'text';button.querySelector('span').textContent=visible?'visibility':'visibility_off';button.setAttribute('aria-label',visible?'Afficher le mot de passe':'Masquer le mot de passe');input.focus()}));</script>@endpush
