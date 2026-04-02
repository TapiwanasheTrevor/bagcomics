<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    /**
     * Show the change password page (for users with temporary passwords).
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_reset_password) {
            return redirect('/');
        }

        return Inertia::render('auth/change-password');
    }

    /**
     * Handle the new password submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($request->password),
            'must_reset_password' => false,
        ])->save();

        return redirect()->route('login')
            ->with('status', 'Password updated successfully. Please sign in with your new password.');
    }
}
