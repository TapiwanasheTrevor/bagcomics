<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'email_notifications' => 'boolean',
            'new_releases_notifications' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create user preferences with notification settings
        $user->preferences()->create([
            'email_notifications' => $request->boolean('email_notifications', true),
            'new_releases_notifications' => $request->boolean('new_releases_notifications', true),
            'reading_view_mode' => 'single',
            'reading_direction' => 'ltr',
            'reading_zoom_level' => 1.20,
            'auto_hide_controls' => true,
            'control_hide_delay' => 3000,
            'theme' => 'dark',
            'reduce_motion' => false,
            'high_contrast' => false,
            'reading_reminders' => false,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
