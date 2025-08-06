<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferences extends Model
{
    use HasFactory;
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

    /**
     * Update multiple preferences at once
     */
    public function updatePreferences(array $preferences): void
    {
        $validPreferences = array_intersect_key($preferences, array_flip($this->fillable));
        $this->fill($validPreferences);
        $this->save();
    }

    /**
     * Reset preferences to defaults
     */
    public function resetToDefaults(): void
    {
        $this->fill(self::getDefaults());
        $this->save();
    }

    /**
     * Get reading preferences only
     */
    public function getReadingPreferences(): array
    {
        return [
            'reading_view_mode' => $this->reading_view_mode,
            'reading_direction' => $this->reading_direction,
            'reading_zoom_level' => $this->reading_zoom_level,
            'auto_hide_controls' => $this->auto_hide_controls,
            'control_hide_delay' => $this->control_hide_delay,
        ];
    }

    /**
     * Get accessibility preferences only
     */
    public function getAccessibilityPreferences(): array
    {
        return [
            'reduce_motion' => $this->reduce_motion,
            'high_contrast' => $this->high_contrast,
            'theme' => $this->theme,
        ];
    }

    /**
     * Get notification preferences only
     */
    public function getNotificationPreferences(): array
    {
        return [
            'email_notifications' => $this->email_notifications,
            'new_releases_notifications' => $this->new_releases_notifications,
            'reading_reminders' => $this->reading_reminders,
        ];
    }

    /**
     * Check if user wants email notifications
     */
    public function wantsEmailNotifications(): bool
    {
        return $this->email_notifications;
    }

    /**
     * Check if user wants new release notifications
     */
    public function wantsNewReleaseNotifications(): bool
    {
        return $this->new_releases_notifications && $this->email_notifications;
    }

    /**
     * Check if user wants reading reminders
     */
    public function wantsReadingReminders(): bool
    {
        return $this->reading_reminders && $this->email_notifications;
    }
}
