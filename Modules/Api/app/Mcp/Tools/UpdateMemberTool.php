<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\MemberResource;

class UpdateMemberTool extends Tool
{
    protected string $description = 'Bearbeitet die Stammdaten eines bestehenden Vereinsmitglieds.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'member_id' => $schema->integer()->description('ID des Mitglieds (members.id)')->required(),
            'member_number' => $schema->integer()->description('Fortlaufende Mitgliedsnummer')->required(),
            'nuliga_id' => $schema->integer()->description('ID im NuLiga-System (optional)'),
            'user_id' => $schema->integer()->description('Verknüpfter Login-Account (bs_users.uid, optional)'),
            'firstname' => $schema->string()->description('Vorname')->required(),
            'lastname' => $schema->string()->description('Nachname')->required(),
            'birthdate' => $schema->string()->description('Geburtsdatum, Format YYYY-MM-DD'),
            'gender' => $schema->string()->description('m|w|d'),
            'email' => $schema->string()->description('E-Mail-Adresse'),
            'phone' => $schema->string()->description('Telefonnummer'),
            'street' => $schema->string()->description('Straße'),
            'zip' => $schema->string()->description('PLZ'),
            'city' => $schema->string()->description('Ort'),
            'fee_category_id' => $schema->integer()->description('ID der Beitragskategorie'),
            'joined_at' => $schema->string()->description('Eintrittsdatum, Format YYYY-MM-DD')->required(),
            'left_at' => $schema->string()->description('Austrittsdatum, Format YYYY-MM-DD (leer lassen für aktive Mitglieder)'),
            'notiz' => $schema->string()->description('Interne Notiz'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.members')) {
            return Response::error('Keine Berechtigung (admin.members erforderlich).');
        }

        $memberId = (int) $request->get('member_id');

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'member_number' => ['required', 'integer', 'min:1', Rule::unique('members', 'member_number')->ignore($memberId)],
            'nuliga_id' => ['nullable', 'integer', 'min:1', Rule::unique('members', 'nuliga_id')->ignore($memberId)],
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', Rule::unique('members', 'user_id')->ignore($memberId)],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:m,w,d'],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $member = Member::findOrFail($memberId);
        $leftAt = $validated['left_at'] ?? null;
        unset($validated['member_id'], $validated['left_at']);

        $before = $member->toArray();
        $departs = $before['left_at'] === null && $leftAt !== null;

        DB::transaction(function () use ($member, $validated, $before, $leftAt, $departs): void {
            $member->update($validated);
            $member->setLeftAt($leftAt);
            MemberChange::create([
                'member_id' => $member->id,
                'admin_user_id' => auth()->id(),
                'action' => $departs ? 'departed' : 'updated',
                'before' => $before,
                'after' => $member->fresh()->toArray(),
            ]);
        });

        return Response::json((new MemberResource($member->fresh()))->resolve());
    }
}
