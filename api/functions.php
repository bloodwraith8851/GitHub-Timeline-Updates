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
    $message = "Thank you for subscribing to GitHub Timeline Updates!\n\n";
    $message .= "Your verification code is: $code\n\n";
    $message .= "Please enter this code on the website to complete your subscription.\n";
    
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
