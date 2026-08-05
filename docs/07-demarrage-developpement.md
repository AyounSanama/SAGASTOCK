# Démarrage du développement

## Structure

- `backend` : Laravel 12, Livewire 4, Sanctum et API REST.
- `mobile` : Flutter Android/iOS, Riverpod, GoRouter, Dio et Drift.
- `docs` : exigences, architecture et décisions.

Laravel 12 est utilisé parce que l'environnement actuel fournit PHP 8.2.

## Backend

Copier `backend/.env.example` vers `backend/.env`, configurer PostgreSQL, puis :

```powershell
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan test
```

L'endpoint de contrôle est `GET /api/v1/health`.

## Mobile

```powershell
cd mobile
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` désigne la machine hôte depuis l'émulateur Android. Sur un appareil
physique, remplacez cette adresse par l'IP de l'ordinateur sur le réseau local,
par exemple :

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
```
