@extends('members::layout')
@section('admin-title', __('members::admin.mail.title'))
@section('admin-content')
@php
    $audiences = $audienceOptions->values();
    $templateItems = $templates->map(fn ($template) => [
        'id' => $template->id,
        'name' => $template->name,
        'subject' => $template->subject,
        'body' => $template->body,
        'updateUrl' => route('members.mail.templates.update', $template),
    ])->values();
@endphp
<div class="ui-page" x-data="{
    audience: {{ Js::from($defaultAudience) }},
    audiences: {{ Js::from($audiences) }},
    subject: '',
    body: '',
    templateName: '',
    selectedTemplateId: null,
    selectedTemplateUpdateUrl: '',
    testEmail: {{ Js::from(auth()->user()?->email ?? '') }},
    showRecipients: false,
    confirmOpen: false,
    previewHtml: '',
    previewLoading: false,
    previewError: '',
    async refreshPreview() {
        this.previewLoading = true;
        this.previewError = '';
        try {
            const response = await fetch({{ Js::from(route('members.mail.preview')) }}, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }},
                },
                body: JSON.stringify({ body: this.body }),
            });
            const data = await response.json();
            this.previewHtml = data.html || '';
        } catch (error) {
            this.previewError = {{ Js::from(__('members::admin.mail.preview_failed')) }};
        } finally {
            this.previewLoading = false;
        }
    },
    loadTemplate(template) {
        this.selectedTemplateId = template.id;
        this.selectedTemplateUpdateUrl = template.updateUrl;
        this.templateName = template.name;
        this.subject = template.subject;
        this.body = template.body;
        this.refreshPreview();
    },
    clearTemplateSelection() {
        this.selectedTemplateId = null;
        this.selectedTemplateUpdateUrl = '';
        this.templateName = '';
    },
    currentAudience() {
        return this.audiences.find((item) => item.key === this.audience) || this.audiences[0];
    }
}">
    <div class="ui-page-header"><h1>{{ __('members::admin.mail.title') }}</h1></div>

    <div class="flex gap-2 mb-4 flex-wrap">
        @foreach($audiences as $audience)
            <button type="button" class="ui-btn" :class="audience === {{ Js::from($audience['key']) }} ? 'ui-btn-primary' : 'ui-btn-outline'" @click="audience = {{ Js::from($audience['key']) }}">
                {{ $audience['label'] }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
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
                    <div x-show="showRecipients" x-cloak class="ui-readonly-box ui-readonly-box-muted" x-text="currentAudience().addresses"></div>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="mail-template-name">{{ __('members::admin.mail.template_name') }}</label>
                    <input id="mail-template-name" type="text" x-model="templateName" class="ui-input">
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="mail-subject">{{ __('members::admin.mail.subject') }}</label>
                    <input id="mail-subject" type="text" x-model="subject" class="ui-input" form="mail-send-form">
                    @error('subject')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="mail-body">{{ __('members::admin.mail.message') }}</label>
                    <textarea id="mail-body" rows="18" x-model="body" class="ui-input" form="mail-send-form" style="min-height:22rem" @input.debounce.600ms="refreshPreview()"></textarea>
                    <p class="ui-help">{{ __('members::admin.mail.formatting_hint') }}</p>
                    <div class="ui-readonly-box ui-readonly-box-muted text-sm">
                        <div><code>**{{ __('members::admin.mail.formatting_bold') }}**</code> -> <strong>{{ __('members::admin.mail.formatting_bold') }}</strong></div>
                        <div><code>*{{ __('members::admin.mail.formatting_italic') }}*</code> -> <em>{{ __('members::admin.mail.formatting_italic') }}</em></div>
                        <div><code>- {{ __('members::admin.mail.formatting_list_item') }}</code></div>
                        <div><code>[{{ __('members::admin.mail.formatting_link_text') }}](https://example.com)</code></div>
                    </div>
                    <div class="ui-card mt-3">
                        <div class="ui-card-header flex items-center justify-between gap-3">
                            <h2>{{ __('members::admin.mail.preview') }}</h2>
                            <button type="button" class="ui-btn ui-btn-ghost" @click="refreshPreview()" x-text="previewLoading ? {{ Js::from(__('members::admin.mail.preview_loading')) }} : {{ Js::from(__('members::admin.mail.refresh_preview')) }}"></button>
                        </div>
                        <div class="ui-card-body">
                            <div x-show="previewError" x-cloak class="ui-flash ui-flash-error" x-text="previewError"></div>
                            <div x-show="!previewHtml && !previewError" class="ui-readonly-box ui-readonly-box-muted">{{ __('members::admin.mail.preview_empty') }}</div>
                            <div x-show="previewHtml" x-cloak class="ui-readonly-box bg-white" style="min-height:8rem" x-html="previewHtml"></div>
                        </div>
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
                    <span class="text-sm text-[#6a6e73]" x-text="currentAudience().countLabel"></span>

                    <form method="POST" action="{{ route('members.mail.test') }}" class="flex items-center gap-2 flex-1 min-w-[18rem] pl-4" style="border-left:1px solid var(--ui-border)">
                        @csrf
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <input type="email" name="test_email" x-model="testEmail" placeholder="{{ __('members::admin.mail.test_recipient') }}" class="ui-input flex-1">
                        <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.mail.send_test') }}</button>
                    </form>
                </div>

                <div class="flex items-center gap-2 flex-wrap pt-2" style="border-top:1px solid var(--ui-border)">
                    <form method="POST" action="{{ route('members.mail.templates.store') }}" class="flex items-center gap-2 flex-wrap">
                        @csrf
                        <input type="hidden" name="name" x-model="templateName">
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.mail.save_as_template') }}</button>
                    </form>
                    <form method="POST" x-bind:action="selectedTemplateUpdateUrl" class="flex items-center gap-2 flex-wrap" x-show="selectedTemplateId" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" x-model="templateName">
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.save_template_changes') }}</button>
                    </form>
                    <button type="button" class="ui-btn ui-btn-ghost" x-show="selectedTemplateId" x-cloak @click="clearTemplateSelection()">{{ __('members::admin.mail.new_template') }}</button>
                </div>
            </div>
        </div>

        <div class="ui-card">
            <div class="ui-card-header"><h2>{{ __('members::admin.mail.templates') }}</h2></div>
            <div class="ui-card-body ui-stack">
                @forelse($templateItems as $template)
                    <div class="ui-readonly-box" :style="selectedTemplateId === {{ $template['id'] }} ? 'outline:2px solid #0066cc; outline-offset:2px;' : ''">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold">{{ $template['name'] }}</div>
                                <div class="text-sm text-[#6a6e73]">{{ $template['subject'] }}</div>
                            </div>
                            <div class="flex items-center gap-1 whitespace-nowrap">
                                <button type="button" class="ui-btn ui-btn-ghost" @click="loadTemplate({{ Js::from($template) }})">{{ __('members::admin.mail.load') }}</button>
                                <form method="POST" action="{{ route('members.mail.templates.destroy', $template['id']) }}" onsubmit="return confirm({{ Js::from(__('members::admin.mail.confirm_delete_template')) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="ui-readonly-box ui-readonly-box-muted">{{ __('members::admin.mail.no_templates') }}</div>
                @endforelse
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
                    <div><strong>{{ __('members::admin.mail.recipients') }}:</strong> <span x-text="currentAudience().countLabel"></span></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="ui-btn ui-btn-ghost" @click="confirmOpen = false">{{ __('members::admin.common.cancel') }}</button>
                    <button type="submit" form="mail-send-form" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.confirm_send') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
