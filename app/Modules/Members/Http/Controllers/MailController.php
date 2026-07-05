<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Mail\MemberBulkMail;
use App\Models\Option;
use App\Modules\Members\Models\MailTemplate;
use App\Modules\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MailController
{
    public function index(): View
    {
        $allAddresses = $this->recipientAddresses('all');
        $vorstandAddresses = $this->recipientAddresses('vorstand');

        return view('members::mail.index', [
            'templates' => MailTemplate::query()->orderBy('name')->get(),
            'fromAddress' => $this->fromAddress(),
            'fromName' => $this->fromName(),
            'recipientCounts' => [
                'all' => $allAddresses->count(),
                'vorstand' => $vorstandAddresses->count(),
            ],
            'recipientAddresses' => [
                'all' => $allAddresses->implode(', '),
                'vorstand' => $vorstandAddresses->implode(', '),
            ],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'string', Rule::in(['all', 'vorstand'])],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $sender = $this->validatedSender();
        $addresses = $this->recipientAddresses($validated['audience']);

        if ($addresses->isNotEmpty()) {
            Mail::bcc($addresses->all())
                ->queue((new MemberBulkMail($validated['subject'], $validated['body']))
                    ->from($sender['address'], $sender['name']));
        }

        return redirect()->route('members.mail.index')
            ->with('success', __('members::admin.mail.sent', ['count' => $addresses->count()]));
    }

    public function updateSender(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_name' => ['required', 'string', 'max:128'],
            'from_address' => ['required', 'email', 'max:128'],
        ]);

        Option::updateOrCreate(['key' => 'members.mail.from_name', 'locale' => null], ['value' => $validated['from_name']]);
        Option::updateOrCreate(['key' => 'members.mail.from_address', 'locale' => null], ['value' => $validated['from_address']]);

        return redirect()->route('members.mail.index')
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

    /** Email addresses by audience: "all" = active members, "vorstand" = active members with a board role. */
    private function recipientAddresses(string $audience): Collection
    {
        $query = Member::query()->active()->whereNotNull('email')->where('email', '!=', '');

        if ($audience === 'vorstand') {
            $query->whereHas('clubRoles', fn ($q) => $q->where('is_board', true));
        }

        return $query->pluck('email')->unique()->values();
    }
}
