<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Accès refusé · PharmaCare</title>
    <style>
        body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: #F7F9FC;
            color: #24324A;
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 24px
        }

        .card {
            max-width: 520px;
            background: #fff;
            border: 1px solid #E8EDF3;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 16px 40px rgba(20, 39, 74, .08)
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px
        }

        p {
            margin: 0 0 16px;
            line-height: 1.6;
            color: #5C6F91
        }

        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 12px;
            background: #F57C00;
            color: #fff;
            text-decoration: none;
            font-weight: 700
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Accès refusé</h1>
        <p>{{ $message ?? 'Vous n’êtes pas autorisé à accéder à cette page.' }}</p>
        <a href="{{ route('dashboard') }}">Retour au tableau de bord</a>
    </div>
</body>

</html>
