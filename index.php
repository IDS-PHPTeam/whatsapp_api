<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/src/WhatsAppClient.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($action === 'health') {
    $client = new WhatsAppClient($config);
    respond(200, array(
        'ok' => true,
        'configured' => $client->isConfigured(),
    ));
}

if ($action === 'webhook') {
    handleWebhook($config, $method);
}

if (!in_array($action, array('send-text', 'send-template', 'send-otp'), true)) {
    respond(404, array('ok' => false, 'error' => 'Unknown action'));
}

if ($method !== 'POST') {
    respond(405, array('ok' => false, 'error' => 'POST required'));
}

requireApiKey($config);

$client = new WhatsAppClient($config);
if (!$client->isConfigured()) {
    respond(503, array('ok' => false, 'error' => 'WhatsApp is not configured. Copy config.example.php to config.local.php.'));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$to = normalizePhone(isset($input['to']) ? $input['to'] : '');
if ($to === '') {
    respond(422, array('ok' => false, 'error' => 'A valid phone number is required in "to".'));
}

if ($action === 'send-text') {
    $message = isset($input['message']) ? trim($input['message']) : '';
    if ($message === '') {
        respond(422, array('ok' => false, 'error' => 'message is required'));
    }
    $result = $client->sendText($to, $message);
    respond($result['ok'] ? 200 : 502, $result);
}

if ($action === 'send-template') {
    $template = isset($input['template']) ? trim($input['template']) : '';
    $language = isset($input['language']) ? trim($input['language']) : 'ar';
    $parameters = isset($input['parameters']) && is_array($input['parameters']) ? $input['parameters'] : array();
    $buttons = isset($input['buttons']) && is_array($input['buttons']) ? $input['buttons'] : array();
    if ($template === '') {
        respond(422, array('ok' => false, 'error' => 'template is required'));
    }
    $result = $client->sendTemplate($to, $template, $language, $parameters, $buttons);
    respond($result['ok'] ? 200 : 502, $result);
}

$code = isset($input['code']) ? trim((string) $input['code']) : '';
if ($code === '') {
    respond(422, array('ok' => false, 'error' => 'code is required'));
}
$language = isset($input['language']) ? trim($input['language']) : $config['otp_language'];
$buttons = !empty($config['otp_authentication_template']) ? array($code) : array();
$result = $client->sendTemplate($to, $config['otp_template'], $language, array($code), $buttons);
respond($result['ok'] ? 200 : 502, $result);

function handleWebhook($config, $method)
{
    if ($method === 'GET') {
        $mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
        $token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
        $challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
        if ($mode === 'subscribe' && $token !== '' && hash_equals((string) $config['verify_token'], (string) $token)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $challenge;
            exit;
        }
        respond(403, array('ok' => false, 'error' => 'Webhook verification failed'));
    }

    if ($method !== 'POST') {
        respond(405, array('ok' => false, 'error' => 'Method not allowed'));
    }

    $raw = file_get_contents('php://input');
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @file_put_contents($dir . '/webhook.log', date('c') . ' ' . $raw . PHP_EOL, FILE_APPEND);
    respond(200, array('ok' => true));
}

function requireApiKey($config)
{
    $provided = '';
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $provided = $_SERVER['HTTP_X_API_KEY'];
    }
    $expected = (string) $config['api_key'];
    if ($expected === '' || $expected === 'change-me' || !hash_equals($expected, (string) $provided)) {
        respond(401, array('ok' => false, 'error' => 'Invalid API key'));
    }
}

function normalizePhone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === null) {
        return '';
    }
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 8) {
        $digits = '961' . $digits;
    }
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        return '';
    }
    return $digits;
}

function respond($status, $body)
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}
