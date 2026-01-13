<?php

// Test Mail Configuration
echo "=== Mail Configuration ===\n";
echo "Mailer: " . config('mail.default') . "\n";
echo "Host: " . config('mail.mailers.smtp.host') . "\n";
echo "Port: " . config('mail.mailers.smtp.port') . "\n";
echo "Username: " . config('mail.mailers.smtp.username') . "\n";
echo "Encryption: " . config('mail.mailers.smtp.encryption') . "\n";
echo "From: " . config('mail.from.address') . "\n\n";

// Check failed jobs
echo "=== Failed Jobs ===\n";
$failedJobs = DB::table('failed_jobs')->count();
echo "Total failed jobs: " . $failedJobs . "\n\n";

if ($failedJobs > 0) {
    try {
        $lastFailed = DB::table('failed_jobs')->orderBy('id', 'desc')->first();
        echo "Last failed job exception:\n";
        echo substr($lastFailed->exception, 0, 800) . "...\n\n";
    } catch (\Exception $e) {
        echo "Could not fetch failed job details\n\n";
    }
}

// Test email sending
echo "=== Testing Email Send ===\n";
try {
    Mail::raw('Test email from HRMS', function ($message) {
        $message->to('ehdohwah007@gmail.com')
                ->subject('SMTP Test - ' . now());
    });
    echo "✅ Email sent successfully!\n";
    echo "Check your inbox at: ehdohwah007@gmail.com\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
