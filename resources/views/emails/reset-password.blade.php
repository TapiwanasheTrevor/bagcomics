<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - BAG Comics</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            padding: 30px;
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .content {
            padding: 30px;
        }
        .title {
            color: white;
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }
        .greeting {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #dc2626;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            color: #1a1a1a;
        }
        .info-box p {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white !important;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
        }
        .warning-box {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
        .warning-box p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        .url-box {
            word-break: break-all;
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ config('app.url') }}/images/bagcomics.jpeg" alt="BAG Comics" class="logo">
            <h1 class="title">🔐 Password Reset Request</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Hello <strong>{{ $user->name }}</strong>!</p>
            
            <p>You recently requested to reset your password for your BAG Comics account. No worries, it happens to the best of us!</p>
            
            <div class="info-box">
                <h3>Security Information</h3>
                <p>
                    📧 <strong>Account:</strong> {{ $user->email }}<br>
                    ⏰ <strong>Requested:</strong> {{ now()->format('M j, Y \a\t g:i A T') }}<br>
                    ⌛ <strong>Expires:</strong> This link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes
                </p>
            </div>
            
            <p>Click the button below to create a new password:</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">🔓 Reset My Password</a>
            </div>
            
            <div class="warning-box">
                <p>
                    <strong>⚠️ Security Notice:</strong><br>
                    • If you did not request this password reset, please ignore this email<br>
                    • This link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes<br>
                    • Never share your password or reset link with anyone<br>
                    • Our support team will never ask for your password
                </p>
            </div>
            
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
            
            <div style="color: #9ca3af; font-size: 14px;">
                <p><strong>Having trouble?</strong></p>
                <p>If the button above doesn't work, copy and paste this URL into your browser:</p>
                <div class="url-box">{{ $resetUrl }}</div>
            </div>
            
            <p style="color: #9ca3af; font-size: 14px; line-height: 1.5;">
                Need help? Contact us at 
                <a href="mailto:support@bagcomics.com" style="color: #dc2626;">support@bagcomics.com</a>
                or visit our 
                <a href="{{ config('app.url') }}" style="color: #dc2626;">website</a>.
            </p>
            
            <p>Thanks for being part of our amazing community! 🌟</p>
            
            <p><strong>The BAG Comics Team</strong></p>
        </div>
        
        <div class="footer">
            © {{ date('Y') }} BAG Comics. African Stories, Boldly Told.<br>
            This email was sent because a password reset was requested for your account.
        </div>
    </div>
</body>
</html>