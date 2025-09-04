<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Comic;
use App\Notifications\NewComicReleased;

class TestSendGridEmail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:sendgrid-email 
                           {email? : Email address to send test to}
                           {--debug : Enable debug mode}
                           {--config : Show current mail configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Test SendGrid email sending functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing SendGrid Email Configuration');
        $this->line('');

        if ($this->option('config')) {
            $this->showMailConfiguration();
            return 0;
        }

        // Get email address
        $email = $this->argument('email') ?: $this->ask('Enter email address to test');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address format');
            return 1;
        }

        $this->line("Testing email sending to: {$email}");
        $this->line('');

        // Test basic mail sending
        $this->testBasicMail($email);

        // Test with notification system
        $this->testNotificationMail($email);

        return 0;
    }

    private function showMailConfiguration()
    {
        $this->info('📧 Current Mail Configuration:');
        $this->line('');
        
        $config = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_PASSWORD' => Str::mask(config('mail.mailers.smtp.password'), '*', 3, -3),
            'MAIL_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            'MAIL_FROM_NAME' => config('mail.from.name'),
        ];

        foreach ($config as $key => $value) {
            $this->line("<fg=cyan>{$key}:</fg=cyan> {$value}");
        }

        $this->line('');
        $this->info('Environment Variables:');
        
        $envVars = [
            'MAIL_MAILER',
            'MAIL_HOST', 
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
            'MAIL_ENCRYPTION',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME'
        ];

        foreach ($envVars as $var) {
            $value = env($var);
            if ($var === 'MAIL_PASSWORD') {
                $value = $value ? Str::mask($value, '*', 3, -3) : 'NOT SET';
            }
            $this->line("<fg=yellow>{$var}:</fg=yellow> " . ($value ?: 'NOT SET'));
        }
    }

    private function testBasicMail($email)
    {
        $this->info('1. Testing Basic Mail Sending...');
        
        try {
            $subject = 'BAG Comics - SendGrid Test Email';
            $message = 'This is a test email from BAG Comics to verify SendGrid configuration.';
            
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)
                     ->subject($subject);
            });

            $this->line('<fg=green>✓</fg=green> Basic mail test completed');
            
            if ($this->option('debug')) {
                $this->debugMailSending();
            }
            
        } catch (\Exception $e) {
            $this->line('<fg=red>✗</fg=red> Basic mail test failed');
            $this->error('Error: ' . $e->getMessage());
            
            if ($this->option('debug')) {
                $this->line('Full error trace:');
                $this->line($e->getTraceAsString());
            }
        }
        
        $this->line('');
    }

    private function testNotificationMail($email)
    {
        $this->info('2. Testing Notification System...');
        
        try {
            // Create a test user
            $testUser = User::where('email', $email)->first();
            
            if (!$testUser) {
                $this->line('Creating temporary test user...');
                $testUser = User::create([
                    'name' => 'Test User',
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now()
                ]);
            }

            // Get or create a test comic
            $testComic = Comic::where('title', 'LIKE', '%Test Comic%')->first();
            
            if (!$testComic) {
                $this->line('Creating test comic...');
                $testComic = Comic::create([
                    'title' => 'Test Comic - SendGrid Verification',
                    'slug' => 'test-comic-sendgrid-' . time(),
                    'author' => 'Test Author',
                    'description' => 'This is a test comic created for SendGrid email verification.',
                    'genre' => 'test',
                    'language' => 'en',
                    'is_free' => true,
                    'is_visible' => true,
                    'published_at' => now(),
                    'page_count' => 10
                ]);
            }

            $this->line("Sending notification for comic: {$testComic->title}");
            
            // Send notification
            $testUser->notify(new NewComicReleased($testComic));
            
            $this->line('<fg=green>✓</fg=green> Notification mail test completed');
            
            // Cleanup test user if it was created
            if ($testUser->name === 'Test User') {
                $testUser->delete();
                $this->line('Cleaned up test user');
            }
            
        } catch (\Exception $e) {
            $this->line('<fg=red>✗</fg=red> Notification mail test failed');
            $this->error('Error: ' . $e->getMessage());
            
            if ($this->option('debug')) {
                $this->line('Full error trace:');
                $this->line($e->getTraceAsString());
            }
        }
        
        $this->line('');
    }

    private function debugMailSending()
    {
        $this->line('');
        $this->info('🔍 Debug Information:');
        
        // Check if Swift Mailer or Symfony Mailer events
        if (class_exists('\Swift_Events_SimpleEventDispatcher')) {
            $this->line('Using Swift Mailer');
        } else {
            $this->line('Using Symfony Mailer');
        }
        
        // Check if SendGrid specific configuration
        if (config('mail.mailers.smtp.host') === 'smtp.sendgrid.net') {
            $this->line('<fg=green>✓</fg=green> SendGrid SMTP detected');
        } else {
            $this->line('<fg=yellow>!</fg=yellow> Not using SendGrid SMTP');
        }
        
        // Check environment
        $env = app()->environment();
        $this->line("Environment: {$env}");
        
        if ($env === 'production') {
            $this->line('<fg=yellow>⚠</fg=yellow>  Running in production - check Render logs for detailed email logs');
        }
    }
}