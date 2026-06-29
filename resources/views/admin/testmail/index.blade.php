@extends('layouts.admin')

@section('admin-title', 'Testmail')

@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">Testmail</h1>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">Testmail senden</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">

            <p class="text-sm text-[#6a6e73]">Sendet eine Test-E-Mail um zu prüfen ob der E-Mail-Versand korrekt konfiguriert ist.</p>

            <form method="POST" action="{{ route('admin.testmail.send') }}">
                @csrf
                <div class="flex flex-col gap-4">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="email">Empfänger-Adresse</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email', auth()->user()?->email) }}"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent"
                            required
                            autofocus
                        >
                        @error('email')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">Testmail senden</button>
                    </div>

                </div>
            </form>

        </div>
    </div>

</div>
@endsection
