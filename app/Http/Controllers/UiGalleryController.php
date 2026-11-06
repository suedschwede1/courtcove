<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class UiGalleryController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(app()->environment(['local', 'testing', 'dusk.local']), 404);

        return view('dev.ui-gallery');
    }
}
