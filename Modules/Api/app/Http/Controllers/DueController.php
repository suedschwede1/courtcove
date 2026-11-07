<?php

declare(strict_types=1);

namespace Modules\Api\Http\Controllers;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Api\Http\Requests\DueRequest;
use Modules\Api\Transformers\DueResource;

class DueController extends ApiController
{
    public function store(DueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        // A closed fiscal year is locked; checked with the year row locked (year first).
        return DB::transaction(function () use ($validated, $fiscalYear): JsonResponse {
            $closed = FiscalYear::lockForWrite($fiscalYear->id);
            if ($closed !== null) {
                return $this->error($closed->lockedMessage(), 422);
            }
            // No own due on top of household coverage in the same year.
            $covering = HouseholdDue::coveringFor((int) $validated['member_id'], $fiscalYear->id);
            if ($covering !== null) {
                return $this->error($covering->coveredMessage(), 422);
            }

            if (($validated['amount'] ?? null) === null) {
                $rate = FeeCategoryRate::where('fee_category_id', $validated['fee_category_id'])
                    ->where('fiscal_year_id', $validated['fiscal_year_id'])
                    ->first();

                if (! $rate) {
                    return $this->error(__('members::admin.dues.no_rate_for_category_year'), 422);
                }

                $validated['amount'] = $rate->amount;
            }

            $due = MemberDue::create([
                ...$validated,
                'status' => 'ausstehend',
                'faellig_am' => $fiscalYear->faellig_am ?? $fiscalYear->starts_on,
            ]);

            return $this->success(new DueResource($due->load(['member', 'fiscalYear', 'feeCategory'])), 201);
        });
    }

    public function update(DueRequest $request, MemberDue $due): JsonResponse
    {
        return DB::transaction(function () use ($request, $due): JsonResponse {
            $closed = FiscalYear::lockForWrite($due->fiscal_year_id);
            if ($closed !== null) {
                return $this->error($closed->lockedMessage(), 422);
            }

            $due->update($request->validated());

            return $this->success(new DueResource($due->fresh(['member', 'fiscalYear', 'feeCategory'])));
        });
    }
}
