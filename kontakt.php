<?php
// Contact form handler for allesuntereinemdach.ch (Hostpoint, PHP mail()).
// Recipients are fixed here on the server; the visitor can only choose a key.
// For now every topic goes to TS Dach und Finanzen AG; the chosen topic is in the email.

const RECIPIENTS = [
    'signerdach' => ['info@tsdachundfinanzenag.ch'],
    'tsdach'     => ['info@tsdachundfinanzenag.ch'],
    'both'       => ['info@tsdachundfinanzenag.ch'],
];
const MAX_MESSAGES_PER_HOUR = 5;
const TOPIC_LABELS = [
    'signerdach' => 'Dach – Signer Dach AG',
    'tsdach'     => 'Dach & Finanzen – TS Dach und Finanzen AG',
    'both'       => 'Nicht sicher / beides',
];
const SENDER_ADDRESS = 'website@allesuntereinemdach.ch';
const SENDER_NAME    = 'Website Alles unter einem Dach';

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond(bool $ok, string $message, int $status, bool $wantsJson): void
{
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: kontakt.html?status=' . ($ok ? 'ok' : 'error') . '#contact-form', true, 303);
    }
    exit;
}

function field(string $name, int $maxLength): string
{
    $value = isset($_POST[$name]) && is_string($_POST[$name]) ? trim($_POST[$name]) : '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
}

function singleLine(string $value): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $value));
}

function encodeHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

// Simple flood protection: at most MAX_MESSAGES_PER_HOUR per IP (stored as a hash).
// If the temp directory is not writable, the form still works.
function rateLimited(): bool
{
    $file = sys_get_temp_dir() . '/aued_form_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    $times = [];
    if (is_readable($file)) {
        foreach (explode(',', (string) @file_get_contents($file)) as $t) {
            if ((int) $t > $now - 3600) {
                $times[] = (int) $t;
            }
        }
    }
    if (count($times) >= MAX_MESSAGES_PER_HOUR) {
        return true;
    }
    $times[] = $now;
    @file_put_contents($file, implode(',', $times), LOCK_EX);
    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: kontakt.html', true, 303);
    exit;
}

// Spam trap: real visitors never see or fill this field.
if (field('website', 200) !== '') {
    respond(true, 'Vielen Dank für Ihre Nachricht.', 200, $wantsJson);
}

$firstname = singleLine(field('firstname', 100));
$lastname  = singleLine(field('lastname', 100));
$email     = singleLine(field('email', 200));
$phone     = singleLine(field('phone', 50));
$topic     = field('topic', 20);
$message   = field('message', 5000);
$privacy   = isset($_POST['privacy']);

if ($firstname === '' || $lastname === '' || $message === '' || !$privacy
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(RECIPIENTS[$topic])) {
    respond(false, 'Bitte füllen Sie alle Pflichtfelder korrekt aus.', 422, $wantsJson);
}

if (rateLimited()) {
    respond(false, 'Sie haben bereits mehrere Nachrichten gesendet. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 429, $wantsJson);
}

$subject = 'Kontaktanfrage Website: ' . $firstname . ' ' . $lastname;
$body = "Neue Nachricht über das Kontaktformular auf allesuntereinemdach.ch\n\n"
      . "Name:     $firstname $lastname\n"
      . "E-Mail:   $email\n"
      . "Telefon:  " . ($phone !== '' ? $phone : '–') . "\n"
      . "Anliegen: " . TOPIC_LABELS[$topic] . "\n\n"
      . "Nachricht:\n$message\n\n"
      . "--\nAntworten Sie direkt auf diese E-Mail, um der Person zu antworten.\n";

$headers = [
    'From: ' . encodeHeader(SENDER_NAME) . ' <' . SENDER_ADDRESS . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = mail(
    implode(', ', RECIPIENTS[$topic]),
    encodeHeader($subject),
    $body,
    implode("\r\n", $headers),
    '-f' . SENDER_ADDRESS
);

if (!$sent) {
    respond(false, 'Die Nachricht konnte leider nicht gesendet werden. Bitte schreiben Sie uns direkt per E-Mail oder rufen Sie uns an.', 500, $wantsJson);
}

// Confirmation to the visitor. It deliberately contains no text the visitor typed,
// so the form cannot be misused to send someone else arbitrary content.
$confirmationBody = "Guten Tag\n\n"
    . "Vielen Dank für Ihre Nachricht über das Kontaktformular auf allesuntereinemdach.ch. "
    . "Wir haben sie erhalten und melden uns so bald wie möglich bei Ihnen.\n\n"
    . "Bei dringenden Anliegen erreichen Sie uns telefonisch:\n"
    . "Signer Dach AG: 055 244 61 37\n"
    . "TS Dach und Finanzen AG: +41 79 808 24 02\n\n"
    . "Freundliche Grüsse\n"
    . "Alles unter einem Dach\n"
    . "Signer Dach AG & TS Dach und Finanzen AG\n\n"
    . "--\nDiese E-Mail wurde automatisch versendet. Falls Sie uns nicht geschrieben haben, können Sie sie ignorieren.\n";

$confirmationHeaders = [
    'From: ' . encodeHeader(SENDER_NAME) . ' <' . SENDER_ADDRESS . '>',
    'Reply-To: ' . RECIPIENTS[$topic][0],
    'Auto-Submitted: auto-replied',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$confirmed = mail(
    $email,
    encodeHeader('Ihre Anfrage an Alles unter einem Dach'),
    $confirmationBody,
    implode("\r\n", $confirmationHeaders),
    '-f' . SENDER_ADDRESS
);

respond(
    true,
    $confirmed
        ? 'Vielen Dank! Ihre Nachricht wurde gesendet. Sie erhalten in Kürze eine Bestätigung per E-Mail.'
        : 'Vielen Dank! Ihre Nachricht wurde gesendet. Wir melden uns so bald wie möglich bei Ihnen.',
    200,
    $wantsJson
);
