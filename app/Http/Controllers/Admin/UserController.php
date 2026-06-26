<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(): View
    {
        $users = User::where('status', '!=', 'deleted')->orderBy('alias')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create', ['privileges' => User::PRIVILEGES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alias'        => ['required', 'string', 'max:128'],
            'email'        => ['nullable', 'email', 'max:128', 'unique:bs_users,email'],
            'status'       => ['required', 'in:admin,assist,enabled,disabled'],
            'password'     => ['required', 'string', 'min:6'],
            'firstname'    => ['nullable', 'string', 'max:128'],
            'lastname'     => ['nullable', 'string', 'max:128'],
            'phone'        => ['nullable', 'string', 'max:64'],
            'privileges'   => ['array'],
            'privileges.*' => ['in:' . implode(',', User::PRIVILEGES)],
        ]);

        $user = User::create([
            'alias'   => $data['alias'],
            'email'   => $data['email'] ?? null,
            'status'  => $data['status'],
            'pw'      => Hash::make($data['password']),
            'created' => now(),
        ]);

        foreach (['firstname', 'lastname', 'phone'] as $field) {
            if (!empty($data[$field])) {
                $user->setMeta($field, $data[$field]);
            }
        }
        $user->syncPrivileges($data['privileges'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'Benutzer angelegt.');
    }
}
