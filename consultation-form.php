<?php
declare(strict_types=1);

/* ========= CONFIG ========= */
const TO_EMAIL   = 'you@yourdomain.co.za';      // where enquiries go
const FROM_EMAIL = 'no-reply@yourdomain.co.za'; // MUST be an address on your own domain
const SITE_NAME  = 'Your Site Name';
const REDIRECT   = 'index.html#consultation';  // fallback page for non-JS submits
const MIN_SECONDS_BETWEEN_SUBMITS = 30;        // simple rate limit per visitor
/* ========================== */

session_start();

$wantsJson = (
    (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
);

function respond(bool $ok, string $message, int $code = 200): void {
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $ok, 'message' => $message]);
    } else {
        $sep = str_contains(REDIRECT, '?') ? '&' : '?';
        $url = preg_replace('/#.*$/', '', REDIRECT)
             . $sep . 'status=' . ($ok ? 'success' : 'error')
             . '&msg=' . urlencode($message)
             . (str_contains(REDIRECT, '#') ? '#' . explode('#', REDIRECT, 2)[1] : '');
        header('Location: ' . $url);
    }
    exit;
}

function clean(string $v): string {
    // strip control chars & newlines (prevents header injection)
    return trim(preg_replace('/[\r\n\0]+/', ' ', strip_tags($v)));
}

/* Only accept POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

/* Honeypot: bots fill this in. Pretend success so they don't retry. */
if (!empty($_POST['website_url'])) {
    respond(true, 'Thank you! We will be in touch shortly.');
}

/* Rate limit */
$now = time();
if (isset($_SESSION['last_submit']) && ($now - $_SESSION['last_submit']) < MIN_SECONDS_BETWEEN_SUBMITS) {
    respond(false, 'Please wait a moment before sending another message.', 429);
}

/* Collect & sanitise */
$name    = clean($_POST['name']    ?? '');
$email   = clean($_POST['email']   ?? '');
$website = clean($_POST['website'] ?? '');
$message = trim(strip_tags($_POST['message'] ?? ''));

/* Validate */
$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Please enter your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
    $errors[] = 'Please enter a valid email address.';
}
if ($website !== '') {
    if (!preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }
    if (!filter_var($website, FILTER_VALIDATE_URL) || mb_strlen($website) > 500) {
        $errors[] = 'Please enter a valid website URL.';
    }
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    $errors[] = 'Message must be between 10 and 5000 characters.';
}

if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

/* Build email */
$subject = 'New consultation request from ' . $name;

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$body  = "You have a new consultation request via " . SITE_NAME . ".\n\n";
$body .= "Name:    $name\n";
$body .= "Email:   $email\n";
$body .= "Website: " . ($website !== '' ? $website : 'Not provided') . "\n";
$body .= "IP:      $ip\n";
$body .= "Date:    " . date('Y-m-d H:i:s') . "\n\n";
$body .= "Message:\n--------------------\n$message\n";

$headers = [
    'From'         => SITE_NAME . ' <' . FROM_EMAIL . '>',
    'Reply-To'     => $name . ' <' . $email . '>',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer'     => 'PHP/' . phpversion(),
];

$sent = mail(
    TO_EMAIL,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    $headers
);

if ($sent) {
    $_SESSION['last_submit'] = $now;
    respond(true, 'Thank you! Your request has been sent. We will be in touch shortly.');
}

error_log('contact.php: mail() failed for ' . $email);
respond(false, 'Sorry, something went wrong sending your message. Please try again later.', 500);
