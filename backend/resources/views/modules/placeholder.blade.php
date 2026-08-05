@extends('layouts.portal')
@section('title', $moduleName.' · PharmaCare')
@section('page-title', $moduleName)
@section('content')
<section class="module-placeholder">
    <div class="module-placeholder-icon" aria-hidden="true">{{ strtoupper(mb_substr($moduleName,0,1)) }}</div>
    <h2>{{ $moduleName }}</h2>
    <p>Module en cours de développement.</p>
</section>
@endsection
