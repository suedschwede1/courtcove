<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Square;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SquareController extends Controller
{
    public function index(): View
    {
        $squares = Square::with('meta')->orderBy('priority')->orderBy('sid')->get();

        return view('admin.squares.index', compact('squares'));
    }
}
