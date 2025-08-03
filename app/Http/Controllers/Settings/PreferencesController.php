<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    /**
     * Show the preferences form.
     */
    public function edit(Request $request): Response
    {
        $preferences = $request->user()->getPreferences();

        return Inertia::render('settings/preferences', [
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update the user's preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reading_view_mode' => 'required|in:single,continuous',
            'reading_direction' => 'required|in:ltr,rtl',
            'reading_zoom_level' => 'required|numeric|min:0.5|max:3.0',
            'auto_hide_controls' => 'boolean',
            'control_hide_delay' => 'required|integer|min:1000|max:10000',
            'theme' => 'required|in:light,dark,auto',
            'reduce_motion' => 'boolean',
            'high_contrast' => 'boolean',
            'email_notifications' => 'boolean',
            'new_releases_notifications' => 'boolean',
            'reading_reminders' => 'boolean',
        ]);

        $preferences = $request->user()->getPreferences();
        $preferences->update($validated);

        return back()->with('status', 'preferences-updated');
    }
}
