# Member review integration

The reviewed member fixes were developed on `feature/provider-cutover` and integrated
into the newer `integration/stage` application without replacing its household billing,
account departure handling, closed fiscal years, payment cancellation or responsive UI.

The integration retains the existing `cancelled_at` / `cancelled_by` / `cancel_reason`
payment schema. No second reversal scheme is introduced. An explicit manual status reset
remains available; effective paid status, filters and outstanding amounts still account
for linked, non-cancelled payments. Payment-backed due amounts cannot be changed until
the payment is corrected through the existing cancellation workflow.

The latest prior fiscal year supplies a carried-forward fee category. First contributions
use the member category, respecting the existing household, inactive-category and closed-year
rules. Annual preview runs the same selection logic without creating dues or coverage links.

The two review migrations permanently redact defined identity fields and member notes from
already anonymized records/history. They align payment-backed member-due statuses and add
mail outcome fields. Historical mail is `unknown`; enqueueing does not mean inbox delivery.
Apply migrations with the release before serving member requests and restart persistent
queue workers using the installation's normal procedure.

`php artisan members:reconcile-dues` is a dry run. `--apply` corrects safe statuses and
missing due dates in open years, with a member-history record. Closed years stay locked.
Zero amounts are reported, not automatically overwritten: use `--repair-zero=12,34` only
for explicitly reviewed, unpaid placeholder IDs after setting tariffs. Payment/allocation
mismatches are reported for reviewed cancellation/correction; original money is preserved.
Household allocations are included in the payment total.

Regression coverage includes privacy across web/REST/MCP, import and payment consistency,
role replacement/omission/history, yearly filtering, preview, mail outcomes, charset checks
and existing-data repair. Integration tests run against disposable SQLite/MySQL databases.
