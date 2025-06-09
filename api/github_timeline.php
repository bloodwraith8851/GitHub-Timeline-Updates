<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class GitHubTimelineUpdates {
    private $api_base_url = 'https://api.github.com';
    private $github_token;

    public function __construct() {
        $this->github_token = getEnvVar('GITHUB_TOKEN');
        if (!$this->github_token) {
            throw new Exception('GitHub token not configured');
        }
    }

    private function makeRequest($endpoint) {
        $ch = curl_init($this->api_base_url . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->github_token,
                'User-Agent: GitHub-Timeline-Updates',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("GitHub API Error: " . curl_error($ch));
            return null;
        }
        
        curl_close($ch);
        
        if ($status !== 200) {
            error_log("GitHub API returned status $status");
            return null;
        }
        
        return json_decode($response, true);
    }

    private function getReceivedEvents($username) {
        return $this->makeRequest("/users/$username/received_events");
    }

    private function formatEvent($event) {
        $type = $event['type'];
        $actor = $event['actor']['login'];
        $repo = $event['repo']['name'];
        $created_at = date('Y-m-d H:i:s', strtotime($event['created_at']));
        $actor_avatar = $event['actor']['avatar_url'];

        $icon = '';
        $color = '';
        $action = '';

        switch ($type) {
            case 'PushEvent':
                $commits = count($event['payload']['commits']);
                $icon = '📦';
                $color = '#2ea44f';
                $action = "pushed $commits commit(s) to";
                break;
            case 'IssuesEvent':
                $action = $event['payload']['action'];
                $issue = $event['payload']['issue']['title'];
                $icon = '🔍';
                $color = '#8250df';
                break;
            case 'PullRequestEvent':
                $action = $event['payload']['action'];
                $pr = $event['payload']['pull_request']['title'];
                $icon = '🔄';
                $color = '#2ea44f';
                break;
            case 'WatchEvent':
                $action = 'starred';
                $icon = '⭐';
                $color = '#e3b341';
                break;
            case 'ForkEvent':
                $action = 'forked';
                $icon = '🍴';
                $color = '#8250df';
                break;
            default:
                $icon = '📋';
                $color = '#2ea44f';
                $action = $type;
        }

        return [
            'html' => $this->generateEventHTML($icon, $actor, $action, $repo, $created_at, $actor_avatar, $color),
            'text' => "$icon $actor $action $repo at $created_at"
        ];
    }

    private function generateEventHTML($icon, $actor, $action, $repo, $time, $avatar, $color) {
        return "
        <div style='background: #0d1117; border: 1px solid rgba(46,164,79,0.2); border-radius: 6px; padding: 16px; margin-bottom: 16px; font-family: -apple-system,BlinkMacSystemFont,\"Segoe UI\",Helvetica,Arial,sans-serif; color: #c9d1d9;'>
            <div style='display: flex; align-items: center;'>
                <img src='$avatar' style='width: 32px; height: 32px; border-radius: 50%; margin-right: 12px;' />
                <div style='flex: 1;'>
                    <div style='margin-bottom: 4px;'>
                        <span style='font-size: 20px; margin-right: 8px;'>$icon</span>
                        <a href='https://github.com/$actor' style='color: #58a6ff; text-decoration: none; font-weight: 600;'>$actor</a>
                        <span style='color: #8b949e;'>$action</span>
                        <a href='https://github.com/$repo' style='color: #58a6ff; text-decoration: none;'>$repo</a>
                    </div>
                    <div style='color: #8b949e; font-size: 12px;'>$time</div>
                </div>
            </div>
        </div>";
    }

    public function sendTimelineUpdates() {
        if (!file_exists(EMAILS_FILE)) {
            error_log("No registered emails file found.");
            return;
        }

        $lines = file(EMAILS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            error_log("No subscribers found.");
            return;
        }

        foreach ($lines as $line) {
            list($email, $github_username) = explode('|', $line);
            $events = $this->getReceivedEvents($github_username);
            
            if (!$events) {
                error_log("No events found for user: $github_username");
                continue;
            }

            $updates = [];
            foreach ($events as $event) {
                if (strtotime($event['created_at']) > strtotime('-5 minutes')) {
                    $formatted = $this->formatEvent($event);
                    $updates[] = $formatted['html'];
                }
            }

            if (!empty($updates)) {
                $html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>GitHub Timeline Updates</title>
                </head>
                <body style='background: #0d1117; margin: 0; padding: 20px;'>
                    <div style='max-width: 600px; margin: 0 auto;'>
                        <h1 style='color: #2ea44f; margin-bottom: 24px; text-align: center;'>GitHub Timeline Updates</h1>
                        " . implode("\n", $updates) . "
                        <div style='text-align: center; margin-top: 24px;'>
                            <a href='https://github.com/$github_username' style='color: #58a6ff; text-decoration: none;'>View your GitHub profile</a>
                        </div>
                    </div>
                </body>
                </html>";

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = getEnvVar('SMTP_USERNAME');
                    $mail->Password = getEnvVar('SMTP_PASSWORD');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom(getEnvVar('FROM_EMAIL'), getEnvVar('FROM_NAME'));
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'New GitHub Timeline Updates';
                    $mail->Body = $html;
                    $mail->AltBody = strip_tags(implode("\n\n", array_column($updates, 'text')));

                    $mail->send();
                    error_log("Timeline update sent to $email");
                } catch (Exception $e) {
                    error_log("Failed to send timeline update to $email: " . $e->getMessage());
                }
            }
        }
    }
}

// Execute updates
try {
    header('Content-Type: application/json');
    $updater = new GitHubTimelineUpdates();
    $updater->sendTimelineUpdates();
    echo json_encode(['status' => 'success', 'message' => 'Timeline updates processed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} 