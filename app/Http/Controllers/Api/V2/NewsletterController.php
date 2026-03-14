<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = NewsletterSubscriber::where('email', $request->email)->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return response()->json([
                    'data' => ['message' => 'You are already subscribed!'],
                ]);
            }

            $subscriber->update(['is_active' => true, 'unsubscribed_at' => null]);
            $this->sendConfirmationEmail($request->email);

            return response()->json([
                'data' => ['message' => 'Welcome back! You have been re-subscribed.'],
            ]);
        }

        NewsletterSubscriber::create(['email' => $request->email]);
        $this->sendConfirmationEmail($request->email);

        return response()->json([
            'data' => ['message' => 'Thank you for subscribing! Check your email for confirmation.'],
        ], 201);
    }

    private function sendConfirmationEmail(string $email): void
    {
        $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#fff;padding:40px;border-radius:12px;">'
            . '<div style="text-align:center;margin-bottom:30px;">'
            . '<h1 style="color:#DC2626;margin:0;">BAG<span style="font-weight:300;">Comics</span></h1>'
            . '</div>'
            . '<h2 style="color:#fff;text-align:center;">You\'re In!</h2>'
            . '<p style="color:#9ca3af;text-align:center;font-size:16px;line-height:1.6;">'
            . 'Thanks for subscribing to BAG Comics. You\'ll be the first to know when new chapters drop.'
            . '</p>'
            . '<div style="text-align:center;margin-top:30px;">'
            . '<a href="' . url('/') . '" style="display:inline-block;background:#DC2626;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;">Start Reading</a>'
            . '</div>'
            . '<p style="color:#6b7280;text-align:center;font-size:12px;margin-top:40px;">'
            . 'You received this because you subscribed at bagcomics.shop'
            . '</p>'
            . '</div>';

        try {
            Mail::html($html, function (Message $message) use ($email) {
                $message->to($email)
                    ->subject('Welcome to BAG Comics!');
            });

            Log::info('Newsletter confirmation sent', ['email' => $email]);
        } catch (\Exception $e) {
            Log::error('Newsletter confirmation email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'username' => config('mail.mailers.smtp.username') ? 'SET' : 'EMPTY',
            ]);
        }
    }
}
