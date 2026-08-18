@extends('layouts.app')
@section('title', $title)
@section('content')
<h1>{{ $title }}</h1>
<table class="table"><thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
<tbody>@foreach($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table>
@endsection
