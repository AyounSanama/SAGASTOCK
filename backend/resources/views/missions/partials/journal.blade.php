<section class="mc-card mc-journal">
    <header><h2>Journal des actions</h2>@if($compact)<span class="mc-muted">7 derniers jours</span>@endif</header>
    @if($entries->isEmpty())
        <p class="mc-muted">Aucune action enregistrée{{ $compact ? ' ces 7 derniers jours' : '' }}.</p>
    @else
        <ul>
            @foreach($entries as $entry)
                <li><i></i><div><strong>{{ $entry['actor'] }}</strong> {{ $entry['is_self'] ? preg_replace('/^a /', 'avez ', $entry['action']) : $entry['action'] }} {{ $entry['subject'] ?? '' }}@if($entry['reason']) : {{ $entry['reason'] }}@endif
                    <small>{{ $entry['created_at']->isToday() ? 'Aujourd’hui '.$entry['created_at']->format('H:i') : ($entry['created_at']->isYesterday() ? 'Hier '.$entry['created_at']->format('H:i') : $entry['created_at']->format('d/m H:i')) }}</small></div></li>
            @endforeach
        </ul>
    @endif
    @if($compact)<a class="mc-btn sm" href="{{ route('organizations.missions.show', [$organization, $mission, 'tab' => 'journal']) }}">Voir tout le journal</a>@endif
</section>
