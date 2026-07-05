@extends('members::layout')
@section('admin-title', __('members::admin.mail.title'))
@section('admin-content')
<div class="ui-page" x-data="{ audience: 'all', subject: '', body: '', templateName: '' }">
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
                <div class="flex gap-2">
                    <input type="text" name="from_name" form="mail-send-form" value="{{ old('from_name', $fromName) }}" required class="ui-input" placeholder="{{ __('members::admin.mail.from_name') }}" style="flex:1">
                    <input type="email" name="from_address" form="mail-send-form" value="{{ old('from_address', $fromAddress) }}" required class="ui-input" placeholder="{{ __('members::admin.mail.from_address') }}" style="flex:1">
                </div>
            </div>
            <div class="ui-field">
                <label class="ui-label">BCC</label>
                <div class="ui-readonly-box ui-readonly-box-muted"
                    x-text="audience === 'all' ? {{ Js::from($recipientAddresses['all']) }} : {{ Js::from($recipientAddresses['vorstand']) }}"></div>
            </div>

            <div class="ui-field">
                <label class="ui-label" for="mail-subject">{{ __('members::admin.mail.subject') }}</label>
                <input id="mail-subject" type="text" x-model="subject" class="ui-input" form="mail-send-form">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mail-body">{{ __('members::admin.mail.message') }}</label>
                <textarea id="mail-body" rows="18" x-model="body" class="ui-input" form="mail-send-form" style="min-height:22rem"></textarea>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <form id="mail-send-form" method="POST" action="{{ route('members.mail.send') }}" style="display:contents">
                    @csrf
                    <input type="hidden" name="audience" x-model="audience">
                    <input type="hidden" name="subject" x-model="subject">
                    <input type="hidden" name="body" x-model="body">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.send') }}</button>
                </form>
                <span class="text-sm text-[#6a6e73]" x-text="audience === 'all' ? {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['all']])) }} : {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['vorstand']])) }}"></span>

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
