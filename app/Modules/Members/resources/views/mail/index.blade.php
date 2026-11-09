@extends('members::layout')
@section('members-title', __('members::admin.mail.title'))
@section('members-content')
@php
    $mailSendItems = $mailSends ?? collect();
    $audiences = $audienceOptions->values();
    $templateItems = $templates->map(fn ($template) => [
        'id' => $template->id,
        'name' => $template->name,
        'subject' => $template->subject,
        'body' => $template->body,
        'updateUrl' => route('members.mail.templates.update', $template),
    ])->values();
@endphp
<x-members::page-header :title="__('members::admin.mail.title')" />

<div class="contents" x-data="{
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
    showPreview: false,
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
        if (this.showPreview) {
            this.refreshPreview();
        }
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
    <div class="mbr-audiences" role="group" aria-label="{{ __('members::admin.mail.audience') }}">
        @foreach($audiences as $audience)
            <button type="button" class="ds-chip" aria-pressed="{{ $audience['key'] === $defaultAudience ? 'true' : 'false' }}" x-bind:aria-pressed="audience === {{ Js::from($audience['key']) }} ? 'true' : 'false'" @click="audience = {{ Js::from($audience['key']) }}">{{ $audience['label'] }}</button>
        @endforeach
    </div>

    <div class="mbr-mail-grid">
        <x-ui.card as="div" padding="lg">
            <div class="flex flex-col gap-4">
                <div class="ds-field">
                    <span class="ds-field__label">{{ __('members::admin.mail.from') }}</span>
                    <div class="rounded-md border border-line bg-surface-sunk px-3 py-2 flex flex-wrap items-center justify-between gap-3">
                        <span class="break-all">{{ $fromName }} &lt;{{ $fromAddress }}&gt;</span>
                        <x-ui.button variant="secondary" :block="false" :href="route('members.mail.config')">{{ __('members::admin.mail.change_sender') }}</x-ui.button>
                    </div>
                </div>

                <div class="ds-field">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="ds-field__label">{{ __('members::admin.mail.bcc') }}</span>
                        <x-ui.button variant="secondary" :block="false" aria-controls="mail-recipients" x-bind:aria-expanded="showRecipients.toString()" x-on:click="showRecipients = ! showRecipients" x-text="showRecipients ? {{ Js::from(__('members::admin.mail.hide_recipients')) }} : {{ Js::from(__('members::admin.mail.show_recipients')) }}">{{ __('members::admin.mail.show_recipients') }}</x-ui.button>
                    </div>
                    <div id="mail-recipients" x-show="showRecipients" x-cloak class="rounded-md border border-line bg-surface-sunk px-3 py-2 break-words text-ink-2" x-text="currentAudience().addresses"></div>
                </div>

                <x-ui.field :label="__('members::admin.mail.template_name')" for="mail-template-name" :error="''">
                    <input id="mail-template-name" type="text" x-model="templateName" class="ds-input">
                </x-ui.field>

                <x-ui.field :label="__('members::admin.mail.subject')" for="mail-subject" :error="$errors->first('subject')">
                    <input id="mail-subject" type="text" x-model="subject" @class(['ds-input', 'ds-input--invalid' => $errors->has('subject')]) form="mail-send-form"@error('subject') aria-invalid="true" aria-describedby="mail-subject-error"@enderror>
                </x-ui.field>

                <x-ui.field :label="__('members::admin.mail.message')" for="mail-body" :help="__('members::admin.mail.formatting_hint')" :error="$errors->first('body')">
                    <textarea id="mail-body" rows="18" x-model="body" @class(['ds-textarea', 'min-h-88', 'ds-textarea--invalid' => $errors->has('body')]) form="mail-send-form" aria-describedby="mail-body-help{{ $errors->has('body') ? ' mail-body-error' : '' }}"@error('body') aria-invalid="true"@enderror @input.debounce.600ms="if (showPreview) refreshPreview()"></textarea>
                </x-ui.field>

                <div class="mbr-format-help rounded-md border border-line bg-surface-sunk px-3 py-2 flex flex-col gap-1 text-sm text-ink-2">
                    <div><code>**{{ __('members::admin.mail.formatting_bold') }}**</code> -&gt; <strong>{{ __('members::admin.mail.formatting_bold') }}</strong></div>
                    <div><code>*{{ __('members::admin.mail.formatting_italic') }}*</code> -&gt; <em>{{ __('members::admin.mail.formatting_italic') }}</em></div>
                    <div><code>- {{ __('members::admin.mail.formatting_list_item') }}</code></div>
                    <div><code>[{{ __('members::admin.mail.formatting_link_text') }}](https://example.com)</code></div>
                </div>

                <div>
                    <button type="button" class="ds-btn ds-btn--secondary" aria-controls="mail-preview" x-bind:aria-expanded="showPreview.toString()" @click="showPreview = ! showPreview; if (showPreview) refreshPreview()" x-text="showPreview ? {{ Js::from(__('members::admin.mail.hide_preview')) }} : {{ Js::from(__('members::admin.mail.show_preview')) }}">{{ __('members::admin.mail.show_preview') }}</button>
                </div>

                <section id="mail-preview" class="flex flex-col gap-3 rounded-lg border border-line bg-surface-sunk p-4" x-show="showPreview" x-cloak aria-labelledby="mail-preview-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="mail-preview-title" class="m-0 text-base font-bold">{{ __('members::admin.mail.preview') }}</h2>
                        <button type="button" class="ds-btn ds-btn--text" @click="refreshPreview()" x-text="previewLoading ? {{ Js::from(__('members::admin.mail.preview_loading')) }} : {{ Js::from(__('members::admin.mail.refresh_preview')) }}">{{ __('members::admin.mail.refresh_preview') }}</button>
                    </div>
                    <x-ui.notice tone="problem" x-show="previewError" x-cloak x-text="previewError" />
                    <p x-show="!previewHtml && !previewError" class="m-0 text-ink-2">{{ __('members::admin.mail.preview_empty') }}</p>
                    <div x-show="previewHtml" x-cloak class="min-h-32 rounded-md border border-line bg-surface px-3 py-2" x-html="previewHtml"></div>
                </section>

                <div class="flex flex-wrap items-center gap-3">
                    <form id="mail-send-form" method="POST" action="{{ route('members.mail.send') }}" class="contents">
                        @csrf
                        <input type="hidden" name="audience" x-model="audience">
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                    </form>
                    <button type="button" class="ds-btn ds-btn--primary" x-ref="sendButton" @click="confirmOpen = true">{{ __('members::admin.mail.send') }}</button>
                    <span class="text-sm text-ink-2" x-text="currentAudience().countLabel"></span>
                    {{-- e.g. "Haushalte ohne zahlende Person (nicht angeschrieben)" for the open-dues audience. --}}
                    <span class="text-sm text-ink-2" x-show="currentAudience().note" x-cloak x-text="currentAudience().note" data-testid="mail-audience-note">{{ $audienceOptions->firstWhere('key', $defaultAudience)['note'] ?? '' }}</span>

                    <form method="POST" action="{{ route('members.mail.test') }}" class="flex flex-1 basis-full flex-wrap items-center gap-2 border-t border-line pt-3 md:basis-72 md:border-t-0 md:border-l md:pt-0 md:pl-4">
                        @csrf
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <input type="email" name="test_email" x-model="testEmail" placeholder="{{ __('members::admin.mail.test_recipient') }}" aria-label="{{ __('members::admin.mail.test_recipient') }}" class="ds-input mbr-test-email">
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.mail.send_test') }}</x-ui.button>
                    </form>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                    <form method="POST" action="{{ route('members.mail.templates.store') }}" class="contents">
                        @csrf
                        <input type="hidden" name="name" x-model="templateName">
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.mail.save_as_template') }}</x-ui.button>
                    </form>
                    <form method="POST" x-bind:action="selectedTemplateUpdateUrl" class="contents" x-show="selectedTemplateId" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" x-model="templateName">
                        <input type="hidden" name="subject" x-model="subject">
                        <input type="hidden" name="body" x-model="body">
                        <x-ui.button type="submit" :block="false">{{ __('members::admin.mail.save_template_changes') }}</x-ui.button>
                    </form>
                    <x-ui.button variant="text" :block="false" x-show="selectedTemplateId" x-cloak x-on:click="clearTemplateSelection()">{{ __('members::admin.mail.new_template') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('members::admin.mail.templates')" padding="lg">
            @if($templateItems->isEmpty())
                <p class="m-0 text-ink-2">{{ __('members::admin.mail.no_templates') }}</p>
            @else
                <ul class="mbr-templates">
                    @foreach($templateItems as $template)
                        <li class="mbr-template rounded-md border border-line bg-surface-sunk px-3 py-2" :class="{ 'mbr-template--selected': selectedTemplateId === {{ $template['id'] }} }" x-bind:aria-current="selectedTemplateId === {{ $template['id'] }} ? 'true' : 'false'">
                            <div class="mbr-template__text">
                                <div class="font-bold">{{ $template['name'] }}</div>
                                <div class="text-sm text-ink-2">{{ $template['subject'] }}</div>
                            </div>
                            <div class="mbr-template__actions">
                                <button type="button" class="ds-btn ds-btn--text" @click="loadTemplate({{ Js::from($template) }})">{{ __('members::admin.mail.load') }}</button>
                                <form method="POST" action="{{ route('members.mail.templates.destroy', $template['id']) }}" onsubmit="return confirm({{ Js::from(__('members::admin.mail.confirm_delete_template')) }})">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="text" :block="false">{{ __('members::admin.common.delete') }}</x-ui.button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card :title="__('members::admin.mail.history')" padding="none" class="mbr-table-card">
        <x-ui.table :caption="__('members::admin.mail.history')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('members::admin.mail.sent_at') }}</th>
                    <th scope="col">{{ __('members::admin.mail.sent_by') }}</th>
                    <th scope="col">{{ __('members::admin.mail.audience') }}</th>
                    <th scope="col">{{ __('members::admin.mail.subject') }}</th>
                    <th scope="col">{{ __('members::admin.mail.recipients') }}</th>
                    <th scope="col">{{ __('members::admin.review.mail_status') }}</th>
                </tr>
            </x-slot:head>
            @forelse($mailSendItems as $mailSend)
                <tr>
                    <td class="tabular-nums" data-label="{{ __('members::admin.mail.sent_at') }}">{{ $mailSend->created_at?->format('d.m.Y H:i') }}</td>
                    <td class="text-ink-2" data-label="{{ __('members::admin.mail.sent_by') }}">{{ $mailSend->sender?->alias ?? '—' }}</td>
                    <td class="text-ink-2" data-label="{{ __('members::admin.mail.audience') }}">{{ $mailSend->audience_label }}</td>
                    <td>{{ __('members::admin.review.mail_'.($mailSend->status ?? 'unknown')) }}</td>
                    <td class="ds-cell--title" data-label="{{ __('members::admin.mail.subject') }}">{{ $mailSend->subject }}</td>
                    <td class="tabular-nums" data-label="{{ __('members::admin.mail.recipients') }}">{{ $mailSend->recipient_count }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">{{ __('members::admin.mail.no_history') }}</td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <div x-show="confirmOpen" x-cloak x-transition.opacity class="mbr-dialog-backdrop fixed inset-0 z-50 flex items-center justify-center bg-chrome/55 p-4"
         @click.self="confirmOpen = false; $refs.sendButton.focus()"
         @keydown.escape.window="if (confirmOpen) { confirmOpen = false; $refs.sendButton.focus() }"
         @keydown.tab="const f = [...$el.querySelectorAll('a[href], button:not([disabled])')].filter(e => e.offsetParent !== null); if (f.length) { if ($event.shiftKey && document.activeElement === f[0]) { $event.preventDefault(); f[f.length - 1].focus() } else if (!$event.shiftKey && document.activeElement === f[f.length - 1]) { $event.preventDefault(); f[0].focus() } }"
         x-effect="if (confirmOpen) $nextTick(() => $refs.confirmCancel.focus())">
        <x-ui.card as="div" padding="lg" class="mbr-dialog" role="dialog" aria-modal="true" aria-labelledby="mail-confirm-title">
            <div class="flex flex-col gap-4">
                <h2 id="mail-confirm-title" class="ds-card__title mbr-dialog__title">{{ __('members::admin.mail.confirm_send_title') }}</h2>
                <div class="rounded-md border border-line bg-surface-sunk px-3 py-2 flex flex-col gap-1">
                    <div><strong>{{ __('members::admin.mail.from') }}:</strong> {{ $fromName }} &lt;{{ $fromAddress }}&gt;</div>
                    <div><strong>{{ __('members::admin.mail.subject') }}:</strong> <span x-text="subject"></span></div>
                    <div><strong>{{ __('members::admin.mail.recipients') }}:</strong> <span x-text="currentAudience().countLabel"></span></div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="ds-btn ds-btn--secondary" x-ref="confirmCancel" @click="confirmOpen = false; $refs.sendButton.focus()">{{ __('members::admin.common.cancel') }}</button>
                    <x-ui.button type="submit" form="mail-send-form" :block="false">{{ __('members::admin.mail.confirm_send') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
