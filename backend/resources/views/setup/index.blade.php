<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configuration initiale · PharmaCare</title>
    <style>
        .setup-head{display:flex;justify-content:space-between;gap:20px;align-items:end;margin-bottom:22px}.setup-head h1{margin:0 0 7px}.setup-head p{margin:0;color:#667085}
        .setup-progress{min-width:210px}.setup-progress div{height:9px;border-radius:99px;background:#E8EDF4;overflow:hidden}.setup-progress i{display:block;height:100%;background:#FF7A00}.setup-progress b{display:block;text-align:right;margin-bottom:7px;color:#152033}
        .setup-list{display:grid;gap:12px}.setup-step{display:grid;grid-template-columns:46px minmax(0,1fr) auto;align-items:center;gap:15px;padding:17px;background:#fff;border:1px solid #E4E9F0;border-radius:15px;box-shadow:0 8px 22px rgba(20,39,74,.05)}
        .setup-number{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#FFF1E5;color:#FF7A00;font-weight:900}.setup-step.done .setup-number{background:#EAF8EF;color:#16A34A}.setup-step h2{font-size:15px!important;margin:0 0 4px!important}.setup-step p{font-size:12px;margin:0;color:#667085}.setup-actions{display:flex;gap:8px;align-items:center}.setup-actions form{margin:0}
        @media(max-width:700px){.setup-head{display:block}.setup-progress{margin-top:18px}.setup-step{grid-template-columns:42px 1fr}.setup-actions{grid-column:1/-1}.setup-actions>*{flex:1}}
    </style>
</head>
<body class="pc-app ">
@include('components.app-sidebar')
<main>
    @php($completed = collect($progress->completed_steps ?? [])->map(fn($value)=>(int)$value))
    <section class="setup-head">
        <div><h1>Assistant de configuration</h1><p>Configurez PharmaCare dans l’ordre, avant son utilisation opérationnelle.</p></div>
        <div class="setup-progress"><b>{{ $completed->count() }} / 12 étapes</b><div><i style="width:{{ ($completed->count()/12)*100 }}%"></i></div></div>
    </section>
    @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    @if(session('message'))<div class="notice">{{ session('message') }}</div>@endif
    <section class="setup-list">
        @foreach($steps as $number => [$title,$description,$route])
            @php($done = $completed->contains($number))
            <article class="setup-step {{ $done ? 'done' : '' }}">
                <span class="setup-number">{{ $done ? '✓' : $number }}</span>
                <div><h2>{{ $title }}</h2><p>{{ $description }}</p></div>
                <div class="setup-actions">
                    @if($number < 12)
                        <a class="button secondary" href="{{ route($route) }}">Ouvrir</a>
                        @unless($done)<form method="post" action="{{ route('setup.steps.complete',$number) }}">@csrf<button class="success">Valider</button></form>@endunless
                    @else
                        <form method="post" action="{{ route('setup.finish') }}">@csrf<button class="success" @disabled($done)>Finaliser</button></form>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
</main>
</body>
</html>
