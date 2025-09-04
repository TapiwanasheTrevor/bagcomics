@component('mail::message')
<div style="text-align: center; margin-bottom: 30px;">
    <img src="{{ asset('/images/bagcomics.jpeg') }}" alt="BAG Comics" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
</div>

# 🔐 Password Reset Request

Hello **{{ $user->name }}**!

You recently requested to reset your password for your BAG Comics account. No worries, it happens to the best of us!

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;">
    <h3 style="margin: 0 0 10px 0; color: #1a1a1a;">Security Information</h3>
    <p style="margin: 0; color: #4b5563; font-size: 14px;">
        📧 <strong>Account:</strong> {{ $user->email }}<br>
        ⏰ <strong>Requested:</strong> {{ now()->format('M j, Y \a\t g:i A T') }}<br>
        ⌛ <strong>Expires:</strong> This link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes
    </p>
</div>

Click the button below to create a new password:

@component('mail::button', ['url' => $resetUrl, 'color' => 'red'])
🔓 Reset My Password
@endcomponent

<div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="margin: 0; color: #92400e; font-size: 14px;">
        <strong>⚠️ Security Notice:</strong><br>
        • If you did not request this password reset, please ignore this email<br>
        • This link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes<br>
        • Never share your password or reset link with anyone<br>
        • Our support team will never ask for your password
    </p>
</div>

---

<div style="color: #9ca3af; font-size: 14px; line-height: 1.6;">
    <p><strong>Having trouble?</strong></p>
    <p>If the button above doesn't work, copy and paste this URL into your browser:</p>
    <p style="word-break: break-all; background: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace;">
        {{ $resetUrl }}
    </p>
</div>

<p style="color: #9ca3af; font-size: 14px; line-height: 1.5;">
    Need help? Contact us at 
    <a href="mailto:support@bagcomics.com" style="color: #dc2626;">support@bagcomics.com</a>
    or visit our 
    <a href="{{ route('home') }}" style="color: #dc2626;">help center</a>.
</p>

Thanks for being part of our amazing community! 🌟

**The BAG Comics Team**

<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
    <p style="margin: 0; color: #9ca3af; font-size: 12px;">
        © {{ date('Y') }} BAG Comics. African Stories, Boldly Told.<br>
        This email was sent because a password reset was requested for your account.
    </p>
</div>
@endcomponent