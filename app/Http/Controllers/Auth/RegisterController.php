<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class RegisterController extends Controller
{
    public function showForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'             => ['required', 'email', 'max:200', 'unique:bs_users,email'],
            'email_confirm'     => ['required', 'email', 'same:email'],
            'password'          => ['required', 'min:8'],
            'password_confirm'  => ['required', 'same:password'],
            'firstname'         => ['required', 'string', 'max:100'],
            'lastname'          => ['required', 'string', 'max:100'],
            'gender'            => ['nullable', 'string', 'in:m,f,d'],
            'phone'             => ['required', 'string', 'max:50'],
            'street'            => ['nullable', 'string', 'max:200'],
            'zip'               => ['nullable', 'string', 'max:20'],
            'city'              => ['nullable', 'string', 'max:100'],
            'privacy'           => ['accepted'],
        ]);

        $alias = trim($data['firstname'].' '.$data['lastname']);

        $user = User::create([
            'alias'   => $alias,
            'email'   => $data['email'],
            'pw'      => Hash::make($data['password']),
            'status'  => 'disabled',
            'created' => now()->toDateTimeString(),
        ]);

        foreach (['firstname', 'lastname', 'gender', 'phone', 'street', 'zip', 'city'] as $key) {
            if (! empty($data[$key])) {
                $user->setMeta($key, $data[$key]);
            }
        }

        return redirect()->route('login')
            ->with('registered', true);
    }
}
