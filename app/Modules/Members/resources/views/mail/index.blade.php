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
                <input type="text" value="{{ $fromName }} <{{ $fromAddress }}>" class="ui-input" disabled>
            </div>
            <div class="ui-field">
                <label class="ui-label">BCC</label>
                <input type="text" class="ui-input" disabled
                    :value="audience === 'all' ? {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['all']])) }} : {{ Js::from(__('members::admin.mail.recipients_count', ['count' => $recipientCounts['vorstand']])) }}">
            </div>

            <form method="POST" action="{{ route('members.mail.send') }}" class="ui-stack">
                @csrf
                <input type="hidden" name="audience" x-model="audience">
                <div class="ui-field">
                    <label class="ui-label" for="mail-subject">{{ __('members::admin.mail.subject') }}</label>
                    <input id="mail-subject" type="text" name="subject" x-model="subject" class="ui-input">
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="mail-body">{{ __('members::admin.mail.message') }}</label>
                    <textarea id="mail-body" name="body" rows="8" x-model="body" class="ui-input"></textarea>
                </div>
                <div class="ui-form-actions justify-between">
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.send') }}</button>
                </div>
            </form>

            <form method="POST" action="{{ route('members.mail.templates.store') }}" class="flex items-end gap-2 pt-2 border-t border-[#e7e3da]">
                @csrf
                <input type="hidden" name="subject" x-model="subject">
                <input type="hidden" name="body" x-model="body">
                <div class="ui-field flex-1">
                    <label class="ui-label" for="mail-template-name">{{ __('members::admin.mail.template_name') }}</label>
                    <input id="mail-template-name" type="text" name="name" x-model="templateName" class="ui-input">
                </div>
                <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.mail.save_as_template') }}</button>
            </form>
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
