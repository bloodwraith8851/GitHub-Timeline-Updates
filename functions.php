<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/config.php'; // ✅ Use constants from here

function generateVerificationCode(): string {
    return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function registerEmail(string $email, string $github_username): bool {
    if (!validateGitHubUsername($github_username)) {
        return false;
    }

    if (!file_exists(EMAILS_FILE)) {
        file_put_contents(EMAILS_FILE, '');
    }

    $lines = file(EMAILS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    
    // Check if email already exists
    foreach ($lines as $line) {
        list($registered_email, $registered_username) = explode('|', $line);
        if ($registered_email === $email) {
            return false;
        }
    }

    // Add new email with GitHub username
    file_put_contents(EMAILS_FILE, $email . '|' . $github_username . PHP_EOL, FILE_APPEND);
    return true;
}

function unregisterEmail(string $email): bool {
    if (!file_exists(EMAILS_FILE)) {
        return false;
    }

    $lines = file(EMAILS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    $new_lines = [];

    foreach ($lines as $line) {
        list($registered_email, $registered_username) = explode('|', $line);
        if ($registered_email === $email) {
            $found = true;
            continue;
        }
        $new_lines[] = $line;
    }

    if (!$found) {
        return false;
    }

    // Save updated list
    file_put_contents(EMAILS_FILE, implode(PHP_EOL, $new_lines) . (empty($new_lines) ? '' : PHP_EOL));
    return true;
}

function sendVerificationEmail(string $email, string $code): bool {
    $subject = "Verify your GitHub Timeline Updates subscription";
    
    // Create a modern HTML email template
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verify Your Email</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #0d1117; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Helvetica, Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; background: linear-gradient(180deg, #1a2433 0%, #0d1117 100%); border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); margin-top: 40px; margin-bottom: 40px; overflow: hidden;'>
            <!-- Header -->
            <div style='text-align: center; padding: 40px 20px; background: linear-gradient(180deg, #1a2433 0%, rgba(26,36,51,0.8) 100%); position: relative; overflow: hidden;'>
                <div style='position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #2ea043, #238636, #2ea043);'></div>
                <div style='position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at center, rgba(46,160,67,0.15), transparent 70%);'></div>
                <div style='position: relative; display: inline-block; margin-bottom: 20px;'>
                    <div style='width: 80px; height: 80px; background: linear-gradient(135deg, #238636, #2ea043); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 8px 24px rgba(46,160,67,0.3);'>
                        <svg style='width: 40px; height: 40px; fill: #ffffff;' viewBox='0 0 16 16'>
                            <path d='M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z'/>
                        </svg>
                    </div>
                </div>
                <h1 style='color: #ffffff; margin: 0 0 12px; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2);'>Verify Your Email</h1>
                <p style='color: #8b949e; margin: 0; font-size: 16px; max-width: 400px; margin: 0 auto;'>You're almost ready to receive GitHub Timeline Updates!</p>
            </div>

            <!-- Content -->
            <div style='padding: 40px 30px; text-align: center;'>
                <p style='color: #c9d1d9; font-size: 16px; line-height: 1.5; margin: 0 0 25px;'>
                    Thanks for signing up! To complete your registration and start receiving GitHub Timeline Updates, please enter this verification code on the website:
                </p>
                
                <!-- Verification Code Box -->
                <div style='background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 12px; padding: 20px; margin: 0 auto 30px; max-width: 300px; border: 1px solid rgba(240,246,252,0.1);'>
                    <div style='font-family: monospace; font-size: 32px; letter-spacing: 4px; color: #2ea043; font-weight: bold; text-shadow: 0 0 10px rgba(46,160,67,0.3);'>
                        $code
                    </div>
                </div>

                <p style='color: #8b949e; font-size: 14px; margin: 25px 0 0;'>
                    This code will expire in 10 minutes for security purposes.<br>
                    If you didn't request this verification, please ignore this email.
                </p>
            </div>

            <!-- Footer -->
            <div style='text-align: center; padding: 30px; background: linear-gradient(0deg, #1a2433 0%, transparent 100%); border-top: 1px solid rgba(240,246,252,0.1);'>
                <p style='color: #8b949e; margin: 0; font-size: 14px;'>
                    GitHub Timeline Updates<br>
                    Stay updated with your latest GitHub activities
                </p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($email, $subject, $message);
}

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getEnvVar('SMTP_USERNAME');
        $mail->Password = getEnvVar('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom(getEnvVar('FROM_EMAIL'), getEnvVar('FROM_NAME'));
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</div>'], ["\n", "\n"], $message));

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

function verifyCode(string $email, string $code): bool {
    $codeFile = CODES_DIR . '/' . $email . '.txt';
    if (!file_exists($codeFile)) {
        error_log("Code file not found for email: $email");
        return false;
    }

    $storedCode = trim(file_get_contents($codeFile));
    error_log("Comparing codes - Stored: $storedCode, Received: $code");
    
    // Clean up the code file regardless of verification result
    unlink($codeFile);
    
    return $code === $storedCode;
}

function validateGitHubUsername($username) {
    try {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: GitHub-Timeline-Updates',
                    'Accept: application/vnd.github.v3+json',
                    'Authorization: token ' . getEnvVar('GITHUB_TOKEN')
                ]
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents("https://api.github.com/users/" . urlencode($username), false, $context);
        
        if ($response === false) {
            error_log("Failed to validate GitHub username: $username");
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['login']) && strtolower($data['login']) === strtolower($username);
    } catch (Exception $e) {
        error_log("Error validating GitHub username: " . $e->getMessage());
        return false;
    }
}

function fetchAndFormatXKCDData(): string {
    $latestComicInfo = @file_get_contents("https://xkcd.com/info.0.json");
    if ($latestComicInfo === false) throw new Exception("Could not fetch latest XKCD comic ID.");
    $latestComicData = json_decode($latestComicInfo, true);
    if (!isset($latestComicData['num'])) throw new Exception("Invalid data for latest XKCD comic ID.");
    $maxComicId = $latestComicData['num'];
    $randomComicId = rand(1, $maxComicId);
    $url = "https://xkcd.com/{$randomComicId}/info.0.json";
    $response = @file_get_contents($url);
    if ($response === false) throw new Exception("Could not fetch XKCD comic data.");
    $data = json_decode($response, true);
    if (!$data || !isset($data['img'], $data['title'], $data['alt'])) throw new Exception("Invalid XKCD comic data.");

    return "<h2>XKCD Comic</h2>
            <img src=\"{$data['img']}\" alt=\"{$data['alt']}\">";
}


function sendXKCDUpdatesToSubscribers(): bool {
    $file = __DIR__ . '/registered_emails.txt';
    if (!file_exists($file)) {
        error_log("No registered emails file found.");
        return false;
    }
    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($emails)) {
        error_log("No subscribers found.");
        return true;
    }
    $overallSuccess = true;
    try {
        $comicHtml = fetchAndFormatXKCDData();
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("Skipping invalid email: {$email}");
                continue;
            }
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Your XKCD Comic';
                $unsubscribeLink = "https://xkcd-comic-app.onrender.com/unsubscribe.php?email=" . urlencode($email);
                $mailBody = $comicHtml . "<p><a href=\"$unsubscribeLink\" id=\"unsubscribe-button\">Unsubscribe</a></p>";
                $mail->Body = $mailBody;
                $mail->AltBody = "View today's XKCD comic. To unsubscribe, visit: " . $unsubscribeLink;
                $mail->send();
            } catch (Exception $e) {
                error_log("Error sending to {$email}: " . $e->getMessage());
                $overallSuccess = false;
            }
        }
        return $overallSuccess;
    } catch (Exception $e) {
        error_log("Error fetching comic: " . $e->getMessage());
        return false;
    }
}
