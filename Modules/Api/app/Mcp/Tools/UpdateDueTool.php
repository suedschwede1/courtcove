<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Modules\Members\Models\MemberDue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\DueResource;

class UpdateDueTool extends Tool
{
    protected string $description = 'Bearbeitet einen bestehenden Mitgliedsbeitrag (Status, Betrag, Teilbetrag, Kommentar).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'due_id' => $schema->integer()->description('ID des Beitrags (member_dues.id)')->required(),
            'fee_category_id' => $schema->integer()->description('ID der Beitragskategorie'),
            'amount' => $schema->number()->description('Betrag in EUR'),
            'teilbetrag' => $schema->number()->description('Bereits geleisteter Teilbetrag'),
            'kommentar' => $schema->string()->description('Kommentar'),
            'status' => $schema->string()->description('ausstehend|bezahlt|ueberfaellig'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.members')) {
            return Response::error('Keine Berechtigung (admin.members erforderlich).');
        }

        $validated = $request->validate([
            'due_id' => ['required', 'integer', 'exists:member_dues,id'],
            'fee_category_id' => ['sometimes', 'exists:fee_categories,id'],
            'amount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(MemberDue::STATUSES)],
        ]);

        $due = MemberDue::findOrFail($validated['due_id']);
        unset($validated['due_id']);

        $due->update($validated);

        return Response::json((new DueResource($due->fresh(['member', 'fiscalYear', 'feeCategory'])))->resolve());
    }
}
