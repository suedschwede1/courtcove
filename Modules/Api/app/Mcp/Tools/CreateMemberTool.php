<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\MemberResource;

class CreateMemberTool extends Tool
{
    protected string $description = 'Legt ein neues Vereinsmitglied (Stammdaten) an.';

    public function schema(JsonSchema $schema): array
    {
        return [
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
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.members')) {
            return Response::error('Keine Berechtigung (admin.members erforderlich).');
        }

        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number'],
            'nuliga_id' => ['nullable', 'integer', 'min:1', 'unique:members,nuliga_id'],
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', 'unique:members,user_id'],
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
        ]);

        $member = DB::transaction(function () use ($validated): Member {
            $member = Member::create($validated);
            MemberChange::create([
                'member_id' => $member->id,
                'admin_user_id' => auth()->id(),
                'action' => 'created',
                'before' => null,
                'after' => $member->fresh()->toArray(),
            ]);

            return $member;
        });

        return Response::json((new MemberResource($member))->resolve());
    }
}
