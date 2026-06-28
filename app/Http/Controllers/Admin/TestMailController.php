<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class TestMailController extends Controller
{
    public function index(): View
    {
        return view('admin.testmail.index');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:128'],
        ]);

        Mail::to($data['email'])->queue(new TestMail());

        return redirect()->route('admin.testmail.index')
            ->with('success', 'Testmail wurde an '.$data['email'].' gesendet.');
    }
}
