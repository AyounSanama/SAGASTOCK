<li>
 <label class="mc-check"><input type="checkbox" name="care_level_ids[]" value="{{ $node['id'] }}" @checked(in_array($node['id'], old('care_level_ids', $selected)))> {{ $node['name'] }} <small>{{ $node['level_label'] }}</small></label>
 @if(!empty($node['children']))
  <ul>@foreach($node['children'] as $child)@include('projects.partials.care-level-checkbox', ['node' => $child, 'selected' => $selected])@endforeach</ul>
 @endif
</li>
