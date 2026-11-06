<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\DueResource;

class CreateDueTool extends Tool
{
    protected string $description = 'Legt einen neuen Mitgliedsbeitrag für ein Mitglied und ein Fiskaljahr an.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'member_id' => $schema->integer()->description('ID des Mitglieds (members.id)')->required(),
            'fiscal_year_id' => $schema->integer()->description('ID des Fiskaljahrs')->required(),
            'fee_category_id' => $schema->integer()->description('ID der Beitragskategorie')->required(),
            'amount' => $schema->number()->description('Betrag in EUR; leer lassen, um den Satz der Beitragskategorie zu übernehmen'),
            'teilbetrag' => $schema->number()->description('Bereits geleisteter Teilbetrag (optional)'),
            'kommentar' => $schema->string()->description('Kommentar (optional)'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.members')) {
            return Response::error('Keine Berechtigung (admin.members erforderlich).');
        }

        $validated = $request->validate([
            'member_id' => [
                'required',
                'exists:members,id',
                Rule::unique('member_dues', 'member_id')->where('fiscal_year_id', (int) $request->get('fiscal_year_id')),
            ],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'fee_category_id' => ['required', 'exists:fee_categories,id'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        // A closed fiscal year is locked; checked with the year row locked (year first).
        return DB::transaction(function () use ($validated, $fiscalYear): Response {
            $closed = FiscalYear::lockForWrite($fiscalYear->id);
            if ($closed !== null) {
                return Response::error($closed->lockedMessage());
            }
            // No own due on top of household coverage in the same year.
            $covering = HouseholdDue::coveringFor((int) $validated['member_id'], $fiscalYear->id);
            if ($covering !== null) {
                return Response::error($covering->coveredMessage());
            }

            if (($validated['amount'] ?? null) === null) {
                $rate = FeeCategoryRate::where('fee_category_id', $validated['fee_category_id'])
                    ->where('fiscal_year_id', $validated['fiscal_year_id'])
                    ->first();

                if (! $rate) {
                    return Response::error(__('members::admin.dues.no_rate_for_category_year'));
                }

                $validated['amount'] = $rate->amount;
            }

            $due = MemberDue::create([
                ...$validated,
                'status' => 'ausstehend',
                'faellig_am' => $fiscalYear->faellig_am ?? $fiscalYear->starts_on,
            ]);

            return Response::json((new DueResource($due->load(['member', 'fiscalYear', 'feeCategory'])))->resolve());
        });
    }
}
