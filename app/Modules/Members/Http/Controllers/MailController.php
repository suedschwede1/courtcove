<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Mail\MemberBulkMail;
use App\Models\Option;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MailCategory;
use App\Modules\Members\Models\MailSend;
use App\Modules\Members\Models\MailTemplate;
use App\Modules\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MailController
{
    public function index(): View
    {
        $audienceOptions = collect($this->audienceDefinitions())
            ->map(function (array $definition, string $key): array {
                $addresses = $this->recipientAddresses($key);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'count' => $addresses->count(),
                    'addresses' => $addresses->implode(', '),
                    'countLabel' => __('members::admin.mail.recipients_count', ['count' => $addresses->count()]),
                    'note' => isset($definition['note']) ? ($definition['note'])() : null,
                ];
            })
            ->values();

        return view('members::mail.index', [
            'templates' => MailTemplate::query()->orderBy('name')->get(),
            'fromAddress' => $this->fromAddress(),
            'fromName' => $this->fromName(),
            'audienceOptions' => $audienceOptions,
            'defaultAudience' => $audienceOptions->first()['key'] ?? 'all',
            'mailSends' => $this->mailSendHistory(),
        ]);
    }

    private function mailSendHistory(): Collection
    {
        if (! $this->mailSendsTableExists()) {
            return collect();
        }

        return MailSend::query()->with('sender')->latest()->limit(10)->get();
    }

    private function mailSendsTableExists(): bool
    {
        return Schema::hasTable('mail_sends');
    }

    public function config(): View
    {
        return view('members::mail.config', [
            'fromAddress' => $this->fromAddress(),
            'fromName' => $this->fromName(),
            'mailCategories' => MailCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'string', Rule::in($this->audienceKeys())],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $sender = $this->validatedSender();
        $addresses = $this->recipientAddresses($validated['audience']);

        $invalid = $addresses->filter(fn (string $address): bool => ! filter_var($address, FILTER_VALIDATE_EMAIL));
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages(['audience' => __('members::admin.mail.invalid_recipients', ['addresses' => $invalid->implode(', ')])]);
        }
        if ($addresses->isEmpty()) {
            throw ValidationException::withMessages(['audience' => __('members::admin.mail.no_recipients')]);
        }
        $send = null;

        $definition = $this->audienceDefinitions()[$validated['audience']];
        if ($this->mailSendsTableExists()) {
            $send = MailSend::create([
                'sent_by_user_id' => auth()->user()?->uid,
                'audience_key' => $validated['audience'],
                'audience_label' => $definition['label'],
                'recipient_count' => $addresses->count(),
                'from_name' => $sender['name'],
                'from_address' => $sender['address'],
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => 'queued',
            ]);
        }

        try {
            Mail::bcc($addresses->all())->queue((new MemberBulkMail($validated['subject'], $validated['body'], $send?->id))
                ->from($sender['address'], $sender['name']));
        } catch (\Throwable $exception) {
            $send?->update(['status' => 'failed']);
            report($exception);
            throw ValidationException::withMessages(['audience' => __('members::admin.mail.send_failed')]);
        }

        return redirect()->route('members.mail.index')
            ->with('success', __('members::admin.mail.queued', ['count' => $addresses->count()]));
    }

    public function updateSender(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_name' => ['required', 'string', 'max:128', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && preg_match('/[\x{10000}-\x{10FFFF}]/u', $value)) {
                    $fail(__('members::admin.mail.sender_characters'));
                }
            }],
            'from_address' => ['required', 'email', 'max:128'],
        ]);

        Option::updateOrCreate(['key' => 'members.mail.from_name', 'locale' => null], ['value' => $validated['from_name']]);
        Option::updateOrCreate(['key' => 'members.mail.from_address', 'locale' => null], ['value' => $validated['from_address']]);

        return redirect()->route('members.mail.config')
            ->with('success', __('members::admin.mail.sender_saved'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:128'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $sender = $this->validatedSender();

        Mail::to($validated['test_email'])
            ->queue((new MemberBulkMail($validated['subject'], $validated['body']))
                ->from($sender['address'], $sender['name']));

        return redirect()->route('members.mail.index')
            ->with('success', __('members::admin.mail.test_sent', ['email' => $validated['test_email']]));
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string'],
        ]);

        return response()->json([
            'html' => $this->renderBodyMarkdown((string) ($validated['body'] ?? '')),
        ]);
    }

    private function fromName(): string
    {
        return (string) Option::getValue('members.mail.from_name', config('mail.from.name'));
    }

    private function fromAddress(): string
    {
        return (string) Option::getValue('members.mail.from_address', config('mail.from.address'));
    }

    /** @return array{name: string, address: string} */
    private function validatedSender(): array
    {
        $name = trim($this->fromName());
        $address = trim($this->fromAddress());

        if ($name === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            abort(422, __('members::admin.mail.sender_required'));
        }

        return ['name' => $name, 'address' => $address];
    }

    private function renderBodyMarkdown(string $body): string
    {
        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        MailTemplate::create($validated);

        return redirect()->route('members.mail.index');
    }

    public function updateTemplate(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $mailTemplate->update($validated);

        return redirect()->route('members.mail.index');
    }

    public function destroyTemplate(MailTemplate $mailTemplate): RedirectResponse
    {
        $mailTemplate->delete();

        return redirect()->route('members.mail.index');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128', 'unique:mail_categories,name'],
        ]);

        MailCategory::create($validated);

        return redirect()->route('members.mail.config');
    }

    public function updateCategory(Request $request, MailCategory $mailCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128', Rule::unique('mail_categories', 'name')->ignore($mailCategory->id)],
        ]);

        $mailCategory->update($validated);

        return redirect()->route('members.mail.config');
    }

    public function destroyCategory(MailCategory $mailCategory): RedirectResponse
    {
        $mailCategory->delete();

        return redirect()->route('members.mail.config');
    }

    /**
     * Audiences: either 'apply' (a member query filter) or 'addresses' (the list itself) plus an optional 'note'.
     *
     * @return array<string, array{label: string, apply?: callable(Builder<Member>): Builder<Member>, addresses?: callable(): Collection<int, string>, note?: callable(): ?string}>
     */
    private function audienceDefinitions(): array
    {
        $definitions = [
            'all' => [
                'label' => __('members::admin.mail.to_all'),
                'apply' => fn (Builder $query): Builder => $query->scopes(['active']),
            ],
            'vorstand' => [
                'label' => __('members::admin.mail.to_board'),
                'apply' => fn (Builder $query): Builder => $query
                    ->scopes(['active'])
                    ->whereHas('clubRoles', fn (Builder $roles): Builder => $roles->where('is_board', true)),
            ],
            'ruhend' => [
                'label' => __('members::admin.mail.to_dormant'),
                'apply' => fn (Builder $query): Builder => $query->where('status', 'ruhend'),
            ],
        ];

        // "Beitrag {Jahr} offen" (decision 4): own open dues of active members, and for a household due only
        // the paying person; households without a paying person get no mail and are counted in the note.
        $year = FiscalYear::defaultFor(FiscalYear::query()->orderBy('starts_on')->get());
        if ($year !== null) {
            $definitions['open_dues'] = [
                'label' => __('members::admin.mail.to_open_dues', ['year' => $year->name]),
                'addresses' => fn (): Collection => self::openDuesAddresses($year),
                'note' => function () use ($year): ?string {
                    $withoutPayer = HouseholdDue::query()->open()->where('fiscal_year_id', $year->id)
                        ->whereHas('household', fn (Builder $h): Builder => $h->whereNull('payer_member_id'))->count();

                    return $withoutPayer > 0 ? __('members::admin.mail.households_without_payer', ['count' => $withoutPayer]) : null;
                },
            ];
        }

        if (Schema::hasTable('mail_categories')) {
            MailCategory::query()->orderBy('name')->get()->each(function (MailCategory $category) use (&$definitions): void {
                $definitions['category:'.$category->id] = [
                    'label' => __('members::admin.mail.to_mail_category', ['category' => $category->name]),
                    'apply' => fn (Builder $query): Builder => $query
                        ->scopes(['active'])
                        ->whereHas('clubRoles', fn (Builder $roles): Builder => $roles
                            ->whereHas('mailCategories', fn (Builder $categories): Builder => $categories->whereKey($category->id))),
                ];
            });
        }

        return $definitions;
    }

    /** @return array<int, string> */
    private function audienceKeys(): array
    {
        return array_keys($this->audienceDefinitions());
    }

    /** @return Collection<int, string> */
    private static function openDuesAddresses(FiscalYear $year): Collection
    {
        $own = Member::query()->scopes(['active'])->whereNotNull('email')->where('email', '!=', '')
            ->whereHas('dues', fn (Builder $d): Builder => $d->scopes(['open'])->where('fiscal_year_id', $year->id))
            ->pluck('email');
        $payerIds = HouseholdDue::query()->open()->where('fiscal_year_id', $year->id)
            ->with('household')->get()
            ->map(fn (HouseholdDue $due): ?int => $due->household->payer_member_id)
            ->filter()->unique()->values()->all();
        $payers = Member::query()->whereNull('anonymized_at')->whereNotNull('email')->where('email', '!=', '')
            ->whereKey($payerIds)->pluck('email');

        return $own->concat($payers)->map(fn ($email): string => (string) $email)->unique()->values();
    }

    private function recipientAddresses(string $audience): Collection
    {
        $definition = $this->audienceDefinitions()[$audience] ?? $this->audienceDefinitions()['all'];
        if (isset($definition['addresses'])) {
            return ($definition['addresses'])();
        }
        $query = Member::query()->whereNotNull('email')->where('email', '!=', '');
        $query = $definition['apply']($query);

        return $query->pluck('email')->unique()->values();
    }
}
