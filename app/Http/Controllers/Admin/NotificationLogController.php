<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Notifications\Types\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only send log of the WhatsApp channel. Shows IDs, status and cost only: never a message text,
 * a full phone number or a provider SID.
 */
final class NotificationLogController extends Controller
{
    private const PER_PAGE = 25;

    private const MONTHS = 12;

    /** Status values offered as chips, in the order of a message's life. */
    private const STATUSES = [
        NotificationLogEntry::PENDING,
        NotificationLogEntry::SENDING,
        NotificationLogEntry::SENT,
        NotificationLogEntry::DELIVERED,
        NotificationLogEntry::READ,
        NotificationLogEntry::FAILED,
        NotificationLogEntry::UNDELIVERED,
        NotificationLogEntry::SKIPPED,
    ];

    public function index(Request $request): View
    {
        $status = $this->status($request);
        $type = $this->type($request);
        $month = $this->month($request);

        $query = NotificationLogEntry::query()->where('channel', 'whatsapp');
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($type !== null) {
            $query->where('type', $type);
        }
        if ($month !== null) {
            $this->inMonth($query, $month);
        }

        $entries = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $users = User::query()
            ->whereIn('uid', $entries->getCollection()->pluck('user_id')->filter()->unique()->all())
            ->pluck('alias', 'uid');

        $months = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $months[$date->format('Y-m')] = $date->format('m.Y');
        }

        return view('admin.whatsapp.log', [
            'entries' => $entries,
            'users' => $users,
            'filters' => ['status' => $status, 'type' => $type, 'month' => $month],
            'statuses' => self::STATUSES,
            'types' => array_map(static fn (NotificationType $t): array => ['value' => $t->value, 'key' => $t->templateKey()], NotificationType::cases()),
            'months' => $months,
        ]);
    }

    private function status(Request $request): ?string
    {
        $value = $request->query('status');

        return is_string($value) && in_array($value, self::STATUSES, true) ? $value : null;
    }

    private function type(Request $request): ?string
    {
        $value = $request->query('type');

        return is_string($value) && NotificationType::tryFrom($value) !== null ? $value : null;
    }

    private function month(Request $request): ?string
    {
        $value = $request->query('month');

        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? $value : null;
    }

    /** @param  Builder<NotificationLogEntry>  $query */
    private function inMonth(Builder $query, string $month): void
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $query->where('created_at', '>=', $start)->where('created_at', '<', $start->copy()->addMonth());
    }
}
