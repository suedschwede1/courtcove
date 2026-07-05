@extends('members::layout')
@section('admin-title', __('members::admin.mail.title'))
@section('admin-content')
<div class="ui-page" x-data="{ audience: 'all', subject: '', body: '', templateName: '', testEmail: {{ Js::from(auth()->user()?->email ?? '') }}, showRecipients: false, confirmOpen: false }">
    <div class="ui-page-header"><h1>{{ __('members::admin.mail.title') }}</h1></div>

    <div class="flex gap-2 mb-4">
        <button type="button" class="ui-btn" :class="audience === 'all' ? 'ui-btn-primary' : 'ui-btn-outline'" @click="audience = 'all'">
            {{ __('members::admin.mail.to_all') }}
        </button>
        <button type="button" class="ui-btn" :class="audience === 'vorstand' ? 'ui-btn-primary' : 'ui-btn-outline'" @click="audience = 'vorstand'">
            {{ __('members::admin.mail.to_board') }}
        </button>
    </div>

    <div class="ui-card">
        <div class="ui-card-body ui-stack">
            <div class="ui-field">
                <label class="ui-label">{{ __('members::admin.mail.from') }}</label>
                <div class="ui-readonly-box flex items-center justify-between gap-3 flex-wrap"><span>{{ $fromName }} &lt;{{ $fromAddress }}&gt;</span><a href="{{ route('members.mail.config') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.mail.change_sender') }}</a></div>
            </div>

            <div class="ui-field">
                <div class="flex items-center justify-between gap-3">
                    <label class="ui-label">BCC</label>
                    <button type="button" class="ui-btn ui-btn-ghost" @click="showRecipients = ! showRecipients" x-text="showRecipients ? {{ Js::from(__('members::admin.mail.hide_recipients')) }} : {{ Js::from(__('members::admin.mail.show_recipients')) }}"></button>
                </div>
                <div x-show="showRecipients" x-cloak class="ui-readonly-box ui-readonly-box-muted"
                    x-text="audience === 'all' ? {{ Js::from($recipientAddresses['all']) }} : {{ Js::from($recipientAddresses['vorstand']) }}"></div>
            </div>

            <div class="ui-field">
                <label class="ui-label" for="mail-subject">{{ __('members::admin.mail.subject') }}</label>
                <input id="mail-subject" type="text" x-model="subject" class="ui-input" form="mail-send-form">
                @error('subject')<p class="ui-error">{{ $message }}</p>@enderror
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mail-body">{{ __('members::admin.mail.message') }}</label>
                <textarea id="mail-body" rows="18" x-model="body" class="ui-input" form="mail-send-form" style="min-height:22rem"></textarea>
                <p class="ui-help">{{ __('members::admin.mail.formatting_hint') }}</p>
                <div class="ui-readonly-box ui-readonly-box-muted text-sm">
                    <div><code>**{{ __('members::admin.mail.formatting_bold') }}**</code> -> <strong>{{ __('members::admin.mail.formatting_bold') }}</strong></div>
                    <div><code>*{{ __('members::admin.mail.formatting_italic') }}*</code> -> <em>{{ __('members::admin.mail.formatting_italic') }}</em></div>
                    <div><code>- {{ __('members::admin.mail.formatting_list_item') }}</code></div>
                    <div><code>[{{ __('members::admin.mail.formatting_link_text') }}](https://example.com)</code></div>
                </div>
                @error('body')<p class="ui-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <form id="mail-send-form" method="POST" action="{{ route('members.mail.send') }}" style="display:contents">
                    @csrf
                    <input type="hidden" name="audience" x-model="audience">
                    <input type="hidden" name="subject" x-model="subject">
                    <input type="hidden" name="body" x-model="body">
                </form>
                <button type="button" class="ui-btn ui-btn-primary" @click="confirmOpen = true">{{ __('members::admin.mail.send') }}</button>
                <span class="text-sm text-[#6a6e73]" x-text="audience === 'all' ? {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['all']])) }} : {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['vorstand']])) }}"></span>

                <form method="POST" action="{{ route('members.mail.test') }}" class="flex items-center gap-2 flex-1 min-w-[18rem] pl-4" style="border-left:1px solid var(--ui-border)">
                    @csrf
                    <input type="hidden" name="subject" x-model="subject">
                    <input type="hidden" name="body" x-model="body">
                    <input type="email" name="test_email" x-model="testEmail" placeholder="{{ __('members::admin.mail.test_recipient') }}" class="ui-input flex-1">
                    <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.mail.send_test') }}</button>
                </form>

                <div class="flex items-center gap-2 flex-1 min-w-[16rem] pl-4" style="border-left:1px solid var(--ui-border)">
                    <form method="POST" action="{{ route('members.mail.templates.store') }}" style="display:contents">
                        @csrf
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <input type="text" name="name" x-model="templateName" placeholder="{{ __('members::admin.mail.template_name') }}" class="ui-input flex-1">
                        <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.mail.save_as_template') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4">
        <div class="ui-card w-full max-w-lg bg-white">
            <div class="ui-card-header"><h2>{{ __('members::admin.mail.confirm_send_title') }}</h2></div>
            <div class="ui-card-body ui-stack">
                <div class="ui-readonly-box">
                    <div><strong>{{ __('members::admin.mail.from') }}:</strong> {{ $fromName }} &lt;{{ $fromAddress }}&gt;</div>
                    <div><strong>{{ __('members::admin.mail.subject') }}:</strong> <span x-text="subject"></span></div>
                    <div><strong>{{ __('members::admin.mail.recipients') }}:</strong> <span x-text="audience === 'all' ? {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['all']])) }} : {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['vorstand']])) }}"></span></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="ui-btn ui-btn-ghost" @click="confirmOpen = false">{{ __('members::admin.common.cancel') }}</button>
                    <button type="submit" form="mail-send-form" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.confirm_send') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('members::admin.mail.templates') }}</h2></div>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.mail.template_name') }}</th>
                        <th>{{ __('members::admin.mail.subject') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        <tr x-data="{ editing: false }">
                            <td class="font-medium" x-show="!editing">{{ $template->name }}</td>
                            <td x-show="!editing">{{ $template->subject }}</td>
                            <td class="text-right" x-show="!editing">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <button type="button" class="ui-btn ui-btn-ghost" @click="subject = {{ Js::from($template->subject) }}; body = {{ Js::from($template->body) }}">{{ __('members::admin.mail.load') }}</button>
                                    <button type="button" class="ui-btn ui-btn-ghost" @click="editing = true">{{ __('members::admin.common.edit') }}</button>
                                    <form method="POST" action="{{ route('members.mail.templates.destroy', $template) }}" onsubmit="return confirm({{ Js::from(__('members::admin.mail.confirm_delete_template')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                            <td colspan="3" x-show="editing">
                                <form method="POST" action="{{ route('members.mail.templates.update', $template) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="ui-field">
                                        <input type="text" name="name" value="{{ $template->name }}" class="ui-input">
                                    </div>
                                    <div class="ui-field">
                                        <input type="text" name="subject" value="{{ $template->subject }}" class="ui-input">
                                    </div>
                                    <div class="ui-field flex-1 min-w-[16rem]">
                                        <textarea name="body" rows="2" class="ui-input">{{ $template->body }}</textarea>
                                    </div>
                                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
                                    <button type="button" class="ui-btn ui-btn-ghost" @click="editing = false">{{ __('members::admin.common.cancel') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
