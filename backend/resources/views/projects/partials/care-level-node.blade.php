<li>
 <div class="med-node">
  <span class="{{ $node['is_active'] ? '' : 'inactive' }}"><strong>{{ $node['name'] }}</strong><small>{{ $node['code'] }} · {{ $node['level_label'] }}</small></span>
  <span class="tags">
   @if($node['organization_id'] === null)<x-app-badge variant="neutral">Référentiel global</x-app-badge>@endif
   @unless($node['is_active'])<x-app-badge variant="warning">Inactif</x-app-badge>@endunless
   @if(($canManage ?? false) && $node['organization_id'] !== null && empty($node['children']))
    <form method="post" action="{{ route('projects.medical-references.care-levels.archive', $node['id']) }}" onsubmit="return confirm('Archiver cet élément ?')">@csrf @method('DELETE')<button type="submit" class="app-icon-button danger" title="Archiver"><span class="material-symbols-outlined">archive</span></button></form>
   @endif
  </span>
 </div>
 @if(!empty($node['children']))
  <ul>@foreach($node['children'] as $child)@include('projects.partials.care-level-node', ['node' => $child])@endforeach</ul>
 @endif
</li>
