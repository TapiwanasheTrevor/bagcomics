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
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ], [
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = strtolower(trim($request->email));

        // Block disposable/throwaway domains
        $domain = substr($email, strpos($email, '@') + 1);
        $blocked = ['mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email', 'yopmail.com'];
        if (in_array($domain, $blocked)) {
            return response()->json([
                'data' => ['message' => 'Please use a permanent email address.'],
            ], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return response()->json([
                    'data' => ['message' => 'You\'re already subscribed! We\'ll notify you when new comics drop.'],
                ]);
            }

            $subscriber->update(['is_active' => true, 'unsubscribed_at' => null]);
            $this->sendConfirmationEmail($email);

            return response()->json([
                'data' => ['message' => 'Welcome back! You\'ve been re-subscribed. Check your inbox for confirmation.'],
            ]);
        }

        NewsletterSubscriber::create(['email' => $email]);
        $this->sendConfirmationEmail($email);

        return response()->json([
            'data' => ['message' => 'You\'re subscribed! Check your inbox for a welcome email.'],
        ], 201);
    }

    private function sendConfirmationEmail(string $email): void
    {
        $homeUrl = url('/');
        $storeUrl = url('/store');
        $year = date('Y');

        $html = <<<HTML
<div style="font-family:'Helvetica Neue',Arial,sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;border-radius:16px;overflow:hidden;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#DC2626,#991B1B);padding:40px 30px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:28px;font-weight:800;">BAG<span style="font-weight:300;">Comics</span></h1>
        <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">Premium Digital Comics</p>
    </div>

    <!-- Body -->
    <div style="padding:40px 30px;">
        <h2 style="color:#fff;text-align:center;margin:0 0 16px;font-size:24px;">Welcome to the crew!</h2>
        <p style="color:#9ca3af;text-align:center;font-size:16px;line-height:1.7;margin:0 0 30px;">
            You're now on the list. We'll send you a heads-up whenever new chapters, series, or exclusive content drops.
        </p>

        <!-- What you'll get -->
        <div style="background:#111;border:1px solid #222;border-radius:12px;padding:24px;margin-bottom:30px;">
            <p style="color:#fff;font-weight:600;margin:0 0 12px;font-size:14px;">As a subscriber, you'll get:</p>
            <table style="width:100%;border:0;border-collapse:collapse;">
                <tr><td style="padding:6px 0;color:#9ca3af;font-size:14px;">&#10003; New chapter alerts</td></tr>
                <tr><td style="padding:6px 0;color:#9ca3af;font-size:14px;">&#10003; Early access announcements</td></tr>
                <tr><td style="padding:6px 0;color:#9ca3af;font-size:14px;">&#10003; Creator spotlights</td></tr>
                <tr><td style="padding:6px 0;color:#9ca3af;font-size:14px;">&#10003; Exclusive subscriber offers</td></tr>
            </table>
        </div>

        <!-- CTA -->
        <div style="text-align:center;margin-bottom:30px;">
            <a href="{$storeUrl}" style="display:inline-block;background:#DC2626;color:#fff;padding:16px 40px;border-radius:10px;text-decoration:none;font-weight:700;font-size:16px;">Browse Comics</a>
        </div>
    </div>

    <!-- Footer -->
    <div style="background:#050505;padding:24px 30px;text-align:center;border-top:1px solid #1a1a1a;">
        <p style="color:#4b5563;font-size:12px;margin:0 0 8px;">
            &copy; {$year} BAG Comics. All rights reserved.
        </p>
        <p style="color:#4b5563;font-size:11px;margin:0;">
            You're receiving this because you subscribed at <a href="{$homeUrl}" style="color:#6b7280;">bagcomics.shop</a>
        </p>
    </div>
</div>
HTML;

        try {
            Mail::html($html, function (Message $message) use ($email) {
                $message->to($email)
                    ->subject('Welcome to BAG Comics! You\'re in.');
            });

            Log::info('Newsletter confirmation sent', ['email' => $email]);
        } catch (\Exception $e) {
            Log::error('Newsletter confirmation email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
