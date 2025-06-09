<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

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
        error_log("GitHub Token configured: " . (!empty($this->github_token) ? 'Yes' : 'No'));
    }

    private function makeRequest($endpoint) {
        $url = $this->api_base_url . $endpoint;
        error_log("Making request to: " . $url);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->github_token,
                'User-Agent: GitHub-Timeline-Updates',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,  // Temporarily disable SSL verification for testing
            CURLOPT_SSL_VERIFYHOST => 0,      // Temporarily disable host verification for testing
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
            error_log("Response: " . $response);
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
        // Convert color to RGB for gradient
        $gradientColor = str_replace('#', '', $color);
        $r = hexdec(substr($gradientColor, 0, 2));
        $g = hexdec(substr($gradientColor, 2, 2));
        $b = hexdec(substr($gradientColor, 4, 2));
        
        return "
        <div style='background: linear-gradient(145deg, #1a2433 0%, #161b22 100%); border: 1px solid rgba(240,246,252,0.1); border-radius: 12px; padding: 20px; margin-bottom: 20px; font-family: -apple-system,BlinkMacSystemFont,\"Segoe UI\",Helvetica,Arial,sans-serif; color: #c9d1d9; box-shadow: 0 8px 24px rgba(0,0,0,0.2); transition: all 0.3s ease; position: relative; overflow: hidden;'>
            <div style='position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, $color, rgba($r,$g,$b,0.2));'></div>
            <div style='display: flex; align-items: center; position: relative; z-index: 1;'>
                <div style='position: relative; margin-right: 20px;'>
                    <div style='width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(45deg, $color 0%, rgba($r,$g,$b,0.6) 100%); padding: 2px;'>
                        <img src='$avatar' style='width: 100%; height: 100%; border-radius: 50%; border: 2px solid #161b22;' />
                    </div>
                    <div style='position: absolute; bottom: -2px; right: -2px; background: #161b22; border-radius: 50%; padding: 4px; border: 2px solid #1a2433;'>
                        <span style='font-size: 16px; line-height: 1;'>$icon</span>
                    </div>
                </div>
                <div style='flex: 1;'>
                    <div style='margin-bottom: 8px; line-height: 1.5;'>
                        <a href='https://github.com/$actor' style='color: #58a6ff; text-decoration: none; font-weight: 600; font-size: 16px; background: linear-gradient(90deg, #58a6ff, #a371f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;'>$actor</a>
                        <span style='color: #8b949e; margin: 0 8px;'>$action</span>
                        <a href='https://github.com/$repo' style='display: inline-block; color: #58a6ff; text-decoration: none; background: rgba(56,139,253,0.1); padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; transition: all 0.2s ease;'>
                            <svg style='width: 14px; height: 14px; margin-right: 6px; vertical-align: middle; fill: currentColor;' viewBox='0 0 16 16'>
                                <path d='M2 2.5A2.5 2.5 0 014.5 0h8.75a.75.75 0 01.75.75v12.5a.75.75 0 01-.75.75h-2.5a.75.75 0 110-1.5h1.75v-2h-8a1 1 0 00-.714 1.7.75.75 0 01-1.072 1.05A2.495 2.495 0 012 11.5v-9zm10.5-1V9h-8c-.356 0-.694.074-1 .208V2.5a1 1 0 011-1h8zM5 12.25v3.25a.25.25 0 00.4.2l1.45-1.087a.25.25 0 01.3 0L8.6 15.7a.25.25 0 00.4-.2v-3.25a.25.25 0 00-.25-.25h-3.5a.25.25 0 00-.25.25z'/>
                            </svg>
                            $repo
                        </a>
                    </div>
                    <div style='color: #8b949e; font-size: 12px; display: flex; align-items: center;'>
                        <svg style='width: 14px; height: 14px; margin-right: 6px; fill: currentColor;' viewBox='0 0 16 16'>
                            <path d='M8 0a8 8 0 100 16A8 8 0 008 0zm0 14.5a6.5 6.5 0 110-13 6.5 6.5 0 010 13zm.5-6.5V4.75a.75.75 0 00-1.5 0v3.5c0 .27.144.518.378.651l2.5 1.5a.75.75 0 00.771-1.284L8.5 8z'/>
                        </svg>
                        $time
                    </div>
                </div>
            </div>
        </div>";
    }

    public function sendTimelineUpdates() {
        error_log("Starting timeline updates process...");
        
        if (!file_exists(EMAILS_FILE)) {
            error_log("No registered emails file found at: " . EMAILS_FILE);
            return;
        }

        $lines = file(EMAILS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            error_log("No subscribers found in emails file.");
            return;
        }

        error_log("Found " . count($lines) . " subscribers");

        foreach ($lines as $line) {
            list($email, $github_username) = explode('|', $line);
            error_log("Processing updates for $email ($github_username)");
            
            $events = $this->getReceivedEvents($github_username);
            
            if (!$events) {
                error_log("No events found for user: $github_username");
                continue;
            }

            error_log("Found " . count($events) . " events for $github_username");
            
            $updates = [];
            foreach ($events as $event) {
                // Changed from 5 minutes to 24 hours for testing
                if (strtotime($event['created_at']) > strtotime('-24 hours')) {
                    $formatted = $this->formatEvent($event);
                    $updates[] = $formatted['html'];
                    error_log("Added event: " . $formatted['text']);
                }
            }

            if (!empty($updates)) {
                error_log("Sending " . count($updates) . " updates to $email");
                
                $html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>GitHub Timeline Updates</title>
                </head>
                <body style='background: #0d1117; margin: 0; padding: 20px; -webkit-font-smoothing: antialiased;'>
                    <div style='max-width: 600px; margin: 0 auto; background: linear-gradient(180deg, #1a2433 0%, #0d1117 100%); border-radius: 16px; box-shadow: 0 12px 36px rgba(0,0,0,0.3); overflow: hidden;'>
                        <!-- Header -->
                        <div style='text-align: center; padding: 40px 20px; background: linear-gradient(180deg, #1a2433 0%, rgba(26,36,51,0.8) 100%); position: relative; overflow: hidden;'>
                            <div style='position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, #2ea043, #238636, #2ea043);'></div>
                            <div style='position: relative; display: inline-block; margin-bottom: 16px;'>
                                <div style='width: 80px; height: 80px; background: linear-gradient(135deg, #238636, #2ea043); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;'>
                                    <svg style='width: 48px; height: 48px; fill: #ffffff;' viewBox='0 0 16 16'>
                                        <path d='M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z'/>
                                    </svg>
                                </div>
                            </div>
                            <h1 style='color: #ffffff; margin: 0 0 8px; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2);'>GitHub Timeline Updates</h1>
                            <p style='color: #8b949e; margin: 0; font-size: 16px; max-width: 400px; margin: 0 auto;'>Stay updated with your latest GitHub activities and contributions</p>
                        </div>

                        <!-- Timeline Updates -->
                        <div style='padding: 30px; background: linear-gradient(180deg, rgba(26,36,51,0.3) 0%, transparent 100%);'>
                            " . implode("\n", $updates) . "
                        </div>

                        <!-- Footer -->
                        <div style='text-align: center; padding: 30px; background: linear-gradient(0deg, #1a2433 0%, transparent 100%); border-top: 1px solid rgba(240,246,252,0.1);'>
                            <a href='https://github.com/$github_username' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #238636, #2ea043); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(46,160,67,0.2);'>
                                <svg style='width: 16px; height: 16px; margin-right: 8px; vertical-align: middle; fill: currentColor;' viewBox='0 0 16 16'>
                                    <path d='M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z'/>
                                </svg>
                                View Your GitHub Profile
                            </a>
                            <div style='margin-top: 24px; padding-top: 24px; border-top: 1px solid rgba(240,246,252,0.1);'>
                                <p style='color: #8b949e; margin: 0 0 12px; font-size: 13px;'>
                                    You're receiving this email because you're subscribed to GitHub Timeline Updates
                                </p>
                                <a href='http://localhost:8000/unsubscribe.php?email=$email' style='color: #58a6ff; text-decoration: none; font-size: 13px; display: inline-block; padding: 6px 12px; border: 1px solid rgba(88,166,255,0.2); border-radius: 6px; transition: all 0.2s ease;'>Unsubscribe</a>
                            </div>
                        </div>
                    </div>
                </body>
                </html>";

                $mail = new PHPMailer(true);
                try {
                    error_log("Setting up email with SMTP settings...");
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = getEnvVar('SMTP_USERNAME');
                    $mail->Password = getEnvVar('SMTP_PASSWORD');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;
                    
                    error_log("SMTP Settings configured:");
                    error_log("Host: " . SMTP_HOST);
                    error_log("Username configured: " . (!empty(getEnvVar('SMTP_USERNAME')) ? 'Yes' : 'No'));
                    error_log("Password configured: " . (!empty(getEnvVar('SMTP_PASSWORD')) ? 'Yes' : 'No'));

                    $mail->setFrom(getEnvVar('FROM_EMAIL'), getEnvVar('FROM_NAME'));
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'New GitHub Timeline Updates';
                    $mail->Body = $html;
                    $mail->AltBody = strip_tags(implode("\n\n", array_column($updates, 'text')));

                    error_log("Attempting to send email...");
                    $mail->send();
                    error_log("Timeline update sent successfully to $email");
                } catch (Exception $e) {
                    error_log("Failed to send timeline update to $email: " . $e->getMessage());
                    error_log("Mail error info: " . print_r($mail->ErrorInfo, true));
                }
            } else {
                error_log("No recent updates found for $github_username in the last 24 hours");
            }
        }
    }
}

// Create and run the updates
try {
    error_log("Starting GitHub Timeline Updates script");
    $updater = new GitHubTimelineUpdates();
    $updater->sendTimelineUpdates();
    echo json_encode(['status' => 'success', 'message' => 'Timeline updates processed']);
} catch (Exception $e) {
    error_log("Error in main script: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} 