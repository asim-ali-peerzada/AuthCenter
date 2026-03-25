<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class SendJobfinderEmailVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    protected string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        try {
            $verificationUrl = URL::temporarySignedRoute(
                'verify.jobfinder.email',
                now()->addMinutes(60),
                ['email' => $this->email]
            );

            $apiKey = config('services.brevo.api_key');
            if (! $apiKey) {
                Log::error('Brevo API key not configured.');
                return;
            }

            $senderEmail = config('mail.from.address');
            $senderName = config('mail.from.name');

            $subject = 'Verify your email - JobFinder';

            // Professional email template
            $html = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Verify your email - JobFinder</title>
            </head>
            <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f6f9fc; color: #333;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center" style="padding: 20px 0 20px 0;">
                            <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); overflow: hidden;">
                                <!-- Header -->
                                <tr>
                                    <td align="center" style="background-color: #1a73e8; padding: 24px 0;">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">JobFinder</h1>
                                        <p style="margin: 8px 0 0 0; color: rgba(255, 255, 255, 0.8); font-size: 16px;">Find Your Dream Career</p>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px 30px;">
                                        <h2 style="margin: 0 0 20px 0; color: #1a73e8; font-size: 24px;">Verify Your Email Address</h2>
                                        <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.5;">Hello,</p>
                                        <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.5;">Thank you for signing up for JobFinder! To complete your registration and access all features of our platform, please verify your email address by clicking the button below:</p>
                                        
                                        <!-- CTA Button -->
                                        <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td align="center" style="padding: 20px 0;">
                                                    <a href="{$verificationUrl}" target="_blank" rel="noopener" style="background-color: #1a73e8; color: #ffffff; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;">Verify Email Address</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style="margin: 24px 0 16px 0; font-size: 16px; line-height: 1.5;">Or copy and paste this link into your browser:</p>
                                        <p style="margin: 0 0 24px 0; padding: 12px; background-color: #f1f3f4; border-radius: 4px; word-break: break-all; font-size: 14px; color: #5f6368;">{$verificationUrl}</p>
                                        
                                        <p style="margin: 0 0 16px 0; font-size: 14px; color: #5f6368;">This link will expire in 60 minutes for security reasons.</p>
                                        
                                        <div style="border-top: 1px solid #e8eaed; margin: 30px 0; padding-top: 20px;">
                                            <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.5;">If you didn't create an account with JobFinder, you can safely ignore this email.</p>
                                            <p style="margin: 0; font-size: 16px; line-height: 1.5;">If you have any questions, please visit our Login page and use the support options available there.</p>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="background-color: #f8f9fa; padding: 24px 30px; text-align: center;">
                                        <p style="margin: 0 0 16px 0; font-size: 14px; color: #5f6368;">© 2023 JobFinder. All rights reserved.</p>
                                        <p style="margin: 0; font-size: 14px; color: #5f6368;">
                                            <a href="#" style="color: #1a73e8; text-decoration: none;">Privacy Policy</a> | 
                                            <a href="#" style="color: #1a73e8; text-decoration: none;">Terms of Service</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'api-key' => $apiKey,
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => $senderName,
                    'email' => $senderEmail,
                ],
                'to' => [[
                    'email' => $this->email,
                ]],
                'subject' => $subject,
                'htmlContent' => $html,
            ]);

            if ($response->failed()) {
                Log::error('Failed to send verification email via Brevo', [
                    'email' => $this->email,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } else {
                Log::info('Verification email sent via Brevo', ['email' => $this->email]);
            }
        } catch (\Throwable $e) {
            Log::error('Error in SendJobfinderEmailVerificationJob', [
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
