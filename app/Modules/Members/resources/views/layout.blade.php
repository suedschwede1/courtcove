@extends('layouts.base')

@section('layout-title')
    {{ __('booking.nav.members') }} - @yield('admin-title', __('members::admin.dashboard.title'))
@endsection

@section('body')
<div class="flex min-h-screen bg-[#f0f0f0]">
    <aside style="width:180px; height:100vh; position:sticky; top:0; overflow-y:auto; background:#151515; flex-shrink:0; display:flex; flex-direction:column;">
        <a href="{{ route('calendar.index') }}" onmouseover="this.style.color='#7fb3e8';" onmouseout="this.style.color='#4d8fdb';" style="display:block; padding:14px 14px 10px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:8px; font-family:var(--font-body); font-size:16px; font-weight:600; text-decoration:none; color:#4d8fdb; transition:color 0.15s ease;">
            ← {{ __('booking.nav.back_to_calendar') }}
        </a>

        <div style="padding:14px 14px 10px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:8px;">
            <span style="font-family:var(--font-body); font-size:10px; color:#6a6e73; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
                {{ __('booking.nav.members') }}
            </span>
        </div>

        <nav style="flex:1; padding:6px 0; display:flex; flex-direction:column;">
            @foreach([
                ['route' => 'members.dashboard', 'pattern' => 'members.dashboard', 'label' => __('members::admin.dashboard.title')],
                ['route' => 'members.index', 'pattern' => 'members.index,members.create,members.edit', 'label' => __('members::admin.members.title')],
                ['route' => 'members.dues.index', 'pattern' => 'members.dues.*', 'label' => __('members::admin.dues.title')],
                ['route' => 'members.mail.index', 'pattern' => 'members.mail.index,members.mail.templates.*', 'label' => __('members::admin.mail.title')],
            ] as $item)
                @php $active = request()->routeIs(...explode(',', $item['pattern'])); @endphp
                <a href="{{ route($item['route']) }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ $active ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ $active ? '11px' : '17px' }}; font-family:var(--font-body); font-size:16px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ $active ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ $active ? '700' : '400' }}; background:{{ $active ? '#34363b' : 'transparent' }}; border-left:{{ $active ? '3px solid #0066cc' : '3px solid transparent' }};">
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div style="margin-top:auto; padding:14px 14px 10px; border-top:1px solid rgba(255,255,255,0.08);">
                <span style="font-family:var(--font-body); font-size:10px; color:#6a6e73; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
                    {{ __('members::admin.dashboard.configuration') }}
                </span>
            </div>

            @foreach([
                ['route' => 'members.fiscal-years.index', 'pattern' => 'members.fiscal-years.*', 'label' => __('members::admin.fiscal_years.title')],
                ['route' => 'members.fee-categories.index', 'pattern' => 'members.fee-categories.*', 'label' => __('members::admin.fee_categories.title')],
                ['route' => 'members.functions.index', 'pattern' => 'members.functions.*', 'label' => __('members::admin.functions.title')],
                ['route' => 'members.mail.config', 'pattern' => 'members.mail.config', 'label' => __('members::admin.mail.config_title')],
                ['route' => 'members.dsgvo.index', 'pattern' => 'members.dsgvo.*', 'label' => __('members::admin.dsgvo.title')],
            ] as $item)
                @php $active = request()->routeIs(...explode(',', $item['pattern'])); @endphp
                <a href="{{ route($item['route']) }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ $active ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ $active ? '11px' : '17px' }}; font-family:var(--font-body); font-size:16px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ $active ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ $active ? '700' : '400' }}; background:{{ $active ? '#34363b' : 'transparent' }}; border-left:{{ $active ? '3px solid #0066cc' : '3px solid transparent' }};">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="ui-admin-main">
        <header class="flex items-center justify-between border-b border-[#e7e3da] bg-white px-6 py-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">{{ __('booking.nav.members') }}</p>
                <h1 class="mt-1 text-lg font-bold text-[#151515]" style="font-family: var(--font-display)">@yield('admin-title', __('members::admin.dashboard.title'))</h1>
            </div>
            <div class="flex items-center gap-4 text-sm text-[#6a6e73]">
                @auth
                    <span>{{ auth()->user()->alias }}</span>
                    @can('admin.see-menu')
                        <a href="{{ route('admin.dashboard') }}" class="ui-btn ui-btn-outline">{{ __('booking.nav.admin') }}</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="ui-btn ui-btn-outline">{{ __('booking.nav.logout') }}</button>
                    </form>
                @endauth
            </div>
        </header>

        @if(session('success'))
            <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('success') }}</div>
        @endif
        @if(session('status'))
            <div class="mx-6 mt-6 ui-flash ui-flash-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-6 ui-flash ui-flash-error">{{ $errors->first() }}</div>
        @endif
        <main class="flex-1 p-6">@yield('admin-content')</main>
    </div>
</div>
@endsection
