@extends('layouts.app')
@section('title', 'Buchungskalender')
@section('content')
<div class="toolbar">
    <a href="{{ route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}">&lt;</a>
    <strong data-date="{{ $date->format('Y-m-d') }}">{{ $date->format('d.m.Y') }}</strong>
    <a href="{{ route('calendar.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}">&gt;</a>
    <a href="{{ route('calendar.index') }}">Heute</a>
</div>
<table>
    <thead>
        <tr>
            <th>Zeit</th>
            @foreach($squares as $square)
                <th>{{ $square->name }}@if($square->alias) – {{ $square->alias }}@endif</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @for($h = 8; $h < 22; $h++)
            <tr>
                <td>{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</td>
                @foreach($squares as $square)
                    @php
                        $r = $reservationsBySquare[$square->sid]->first(fn($r) => $r->time_start === $h * 3600);
                    @endphp
                    <td class="{{ $r ? 'cc-single-future' : 'cc-free' }}">
                        @if($r && auth()->check())
                            {{ $r->booking->user->name ?? '—' }}
                        @elseif($r)
                            Gebucht
                        @endif
                    </td>
                @endforeach
            </tr>
        @endfor
    </tbody>
</table>
@endsection
