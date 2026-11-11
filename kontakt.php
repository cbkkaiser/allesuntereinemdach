<?php
// Contact form handler for allesuntereinemdach.ch (Hostpoint, PHP mail()).
// Recipients are fixed here on the server; the visitor can only choose a key.

const RECIPIENTS = [
    'signerdach' => ['info@signerdach.ch'],
    'tsdach'     => ['info@tsdachundfinanzenag.ch'],
    'both'       => ['info@signerdach.ch', 'info@tsdachundfinanzenag.ch'],
];
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

respond(true, 'Vielen Dank! Ihre Nachricht wurde gesendet. Wir melden uns so bald wie möglich bei Ihnen.', 200, $wantsJson);
