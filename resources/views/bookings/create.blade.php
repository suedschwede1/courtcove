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

<div class="booking-confirm-page">
    <div class="panel booking-confirm-card">

        <h2 class="booking-confirm-title">
            {{ $square->name }} buchen
        </h2>

        <table class="booking-confirm-summary">
            <tr>
                <td>Platz</td>
                <td>
                    {{ $square->name }}
                    @if($square->alias)
                        <span class="booking-confirm-summary__alias">– {{ $square->alias }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Datum</td>
                <td>
                    {{ $date->isoFormat('dddd, D. MMMM YYYY') }}
                </td>
            </tr>
            <tr>
                <td>Zeit</td>
                <td>
                    {{ $timeStartLabel }} – {{ $timeEndLabel }} Uhr
                </td>
            </tr>
        </table>

        <p class="booking-confirm-quote">
            {{ $spruch }}
        </p>

        @if($errors->has('booking'))
            <p class="booking-confirm-error">
                {{ $errors->first('booking') }}
            </p>
        @endif

        <form method="POST" action="{{ route('bookings.store') }}" class="booking-confirm-form">
            @csrf
            <input type="hidden" name="sid"        value="{{ $square->sid }}">
            <input type="hidden" name="date"       value="{{ $date->format('Y-m-d') }}">
            <input type="hidden" name="time_start" value="{{ $timeStartSeconds }}">
            <input type="hidden" name="time_end"   value="{{ $timeEndSeconds }}">
            <input type="hidden" name="quantity"   value="2">

            <label class="booking-confirm-field">
                2. Spielername
                <input type="text" name="player_name_2" value="{{ old('player_name_2') }}" maxlength="120" required>
            </label>

            <button type="submit" class="default-button booking-confirm-submit">
                Buchung abschließen
            </button>
        </form>

        <div class="booking-confirm-cancel">
            <a href="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">
                Abbrechen
            </a>
        </div>

    </div>
</div>
@endsection
