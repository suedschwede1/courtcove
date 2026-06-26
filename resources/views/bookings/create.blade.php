@extends('layouts.app')
@section('title', $square->name . ' buchen')

@section('content')

@php
$sprueche = [
    '🎾 Möge der erste Aufschlag sitzen!',
    '🏆 Heute gehört der Platz dir!',
    '💪 Gut aufgewärmt ist halb gewonnen.',
    '🌞 Schönes Wetter? Na dann los!',
    '🎯 Fokus, Kraft, Topspin – viel Spaß!',
    '🚀 Aufschlag wie Federer, Beine wie Nadal?',
    '😎 Wer bucht, der spielt. Wer spielt, der gewinnt.',
    '🏃 Laufschuhe schnüren, Schläger greifen, los geht\'s!',
    '⚡ Der Platz wartet schon auf dich!',
    '🎉 Gleich kann der Spaß beginnen!',
    '🎾 Der Ball ist rund – und der Spaß erst recht!',
    '😂 Doppelfehler? Kenn ich nicht – ich kenn nur Doubletten!',
    '🌟 Heute spielst du wie ein Profi. Morgen auch.',
    '🍀 Viel Glück – obwohl du es gar nicht brauchst!',
    '🎸 Rock\'n\'Roll auf dem Tennisplatz!',
    '🧠 Tennis ist 90% mental – die anderen 10% auch.',
    '☕ Kaffee danach schmeckt nach Sieg!',
    '🐢 Langsam anlaufen, schnell werden!',
    '🌈 Jeder Aufschlag ist eine neue Chance!',
    '😤 Der Gegner zittert schon – zumindest in deiner Vorstellung.',
    '🏅 Medaillen gibt\'s keine – aber Spaß umso mehr!',
    '🎪 Zeig dem Platz, wer hier der Chef ist!',
    '🦁 Brüll nicht – spiel einfach gut!',
    '🌊 Fließe wie Wasser, triff wie ein Hammer!',
    '🤸 Dehnen nicht vergessen – der Arzt dankt es dir!',
    '👟 Neue Schuhe? Heute läufst du allen davon!',
    '🎭 Drama auf dem Platz gehört dazu – aber nur bei den anderen!',
    '🏖️ Nach dem Spiel ist vor dem Eis!',
    '🔥 Heiß wie dein Vorhandtopspin!',
    '😇 Möge der Wind immer in deinem Rücken sein!',
];
$spruch = $sprueche[array_rand($sprueche)];

$timeStartSeconds = $timeStart * 3600;
$timeEndSeconds   = $timeEnd   * 3600;

$timeStartLabel = str_pad((string) $timeStart, 2, '0', STR_PAD_LEFT) . ':00';
$timeEndLabel   = str_pad((string) $timeEnd,   2, '0', STR_PAD_LEFT) . ':00';
@endphp

<div style="display:flex; justify-content:center; align-items:flex-start; padding:40px 16px;">
    <div class="panel" style="max-width:460px; width:100%; padding:32px 36px;">

        <h2 style="margin:0 0 24px 0; font-size:18px; color:#C84B11; text-align:center;">
            {{ $square->name }} buchen
        </h2>

        <table style="width:100%; border:none; margin-bottom:24px; border-collapse:collapse;">
            <tr>
                <td style="border:none; padding:8px 0; color:#888; font-size:13px; width:60px;">Platz</td>
                <td style="border:none; padding:8px 0; font-size:13px; font-weight:bold;">
                    {{ $square->name }}
                    @if($square->alias)
                        <span style="font-weight:normal; color:#888;">– {{ $square->alias }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border:none; padding:8px 0; color:#888; font-size:13px;">Datum</td>
                <td style="border:none; padding:8px 0; font-size:13px;">
                    {{ $date->isoFormat('dddd, D. MMMM YYYY') }}
                </td>
            </tr>
            <tr>
                <td style="border:none; padding:8px 0; color:#888; font-size:13px;">Zeit</td>
                <td style="border:none; padding:8px 0; font-size:13px;">
                    {{ $timeStartLabel }} – {{ $timeEndLabel }} Uhr
                </td>
            </tr>
        </table>

        <p style="text-align:center; font-style:italic; color:#999; font-size:12px; margin:0 0 28px 0;">
            {{ $spruch }}
        </p>

        @if($errors->has('booking'))
            <p style="color:#c0392b; font-size:13px; margin:0 0 16px 0; text-align:center;">
                {{ $errors->first('booking') }}
            </p>
        @endif

        <form method="POST" action="{{ route('bookings.store') }}" style="text-align:center;">
            @csrf
            <input type="hidden" name="sid"        value="{{ $square->sid }}">
            <input type="hidden" name="date"       value="{{ $date->format('Y-m-d') }}">
            <input type="hidden" name="time_start" value="{{ $timeStartSeconds }}">
            <input type="hidden" name="time_end"   value="{{ $timeEndSeconds }}">
            <input type="hidden" name="quantity"   value="1">

            <button type="submit" class="default-button"
                    style="padding:10px 28px; font-size:14px; cursor:pointer; background:#C84B11; color:#fff; border:none; border-radius:3px;">
                Buchung abschließen
            </button>
        </form>

        <div style="text-align:center; margin-top:16px;">
            <a href="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}"
               style="font-size:12px; color:#888;">
                Abbrechen
            </a>
        </div>

    </div>
</div>

@endsection
