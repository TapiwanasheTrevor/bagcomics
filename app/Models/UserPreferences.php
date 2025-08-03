<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferences extends Model
{
    protected $fillable = [
        'user_id',
        'reading_view_mode',
        'reading_direction',
        'reading_zoom_level',
        'auto_hide_controls',
        'control_hide_delay',
        'theme',
        'reduce_motion',
        'high_contrast',
        'email_notifications',
        'new_releases_notifications',
        'reading_reminders',
    ];

    protected $casts = [
        'reading_zoom_level' => 'decimal:2',
        'auto_hide_controls' => 'boolean',
        'control_hide_delay' => 'integer',
        'reduce_motion' => 'boolean',
        'high_contrast' => 'boolean',
        'email_notifications' => 'boolean',
        'new_releases_notifications' => 'boolean',
        'reading_reminders' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default preferences for a new user
     */
    public static function getDefaults(): array
    {
        return [
            'reading_view_mode' => 'single',
            'reading_direction' => 'ltr',
            'reading_zoom_level' => 1.20,
            'auto_hide_controls' => true,
            'control_hide_delay' => 3000,
            'theme' => 'dark',
            'reduce_motion' => false,
            'high_contrast' => false,
            'email_notifications' => true,
            'new_releases_notifications' => true,
            'reading_reminders' => false,
        ];
    }
}
