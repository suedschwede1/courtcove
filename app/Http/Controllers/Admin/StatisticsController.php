<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class StatisticsController extends Controller
{
    public function index(): View
    {
        return view('admin.statistics.index', [
            'stats' => collect(),
            'summary' => [
                'total' => 0,
                'single' => 0,
                'double' => 0,
                'lastMonth' => 0,
                'cancellationRate' => 0.0,
            ],
        ]);
    }
}
