<?php

namespace App\Notifications;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComicReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Comic $comic
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Only send if user wants new release notifications
        if ($notifiable instanceof User) {
            $preferences = $notifiable->getPreferences();
            if ($preferences->wantsNewReleaseNotifications()) {
                return ['mail'];
            }
        }

        return [];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📚 New Comic Alert: ' . $this->comic->title . ' is now available!')
            ->view('emails.new-comic-notification', [
                'user' => $notifiable,
                'comic' => $this->comic
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'comic_id' => $this->comic->id,
            'comic_title' => $this->comic->title,
            'comic_author' => $this->comic->author,
            'comic_url' => route('comics.show', $this->comic->slug),
            'is_free' => $this->comic->is_free,
            'price' => $this->comic->price,
            'message' => 'New comic "' . $this->comic->title . '" has been released!'
        ];
    }
}
