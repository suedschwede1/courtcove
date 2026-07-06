<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

final class ApiTokenController extends Controller
{
    public function index(): View
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->with('tokenable')
            ->latest('created_at')
            ->get();

        $users = User::orderBy('alias')->get();

        return view('admin.api-tokens.index', compact('tokens', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'uid' => ['required', 'integer', 'exists:bs_users,uid'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($data['uid']);
        $plainTextToken = $user->createToken($data['device_name'])->plainTextToken;

        return redirect()->route('admin.api-tokens.index')
            ->with('success', __('booking.admin.api_tokens.created_message', ['token' => $plainTextToken]));
    }

    public function destroy(PersonalAccessToken $token): RedirectResponse
    {
        $token->delete();

        return redirect()->route('admin.api-tokens.index')->with('success', __('booking.admin.api_tokens.revoked_message'));
    }
}
