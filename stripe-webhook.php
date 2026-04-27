<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function stripeWebhookJsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function stripeWebhookResolveUserId(array $object): ?int
{
    $metadataUserId = (int) (($object['metadata']['bexon_user_id'] ?? 0));
    if ($metadataUserId > 0) {
        return $metadataUserId;
    }

    $clientReferenceId = (int) ($object['client_reference_id'] ?? 0);
    if ($clientReferenceId > 0) {
        return $clientReferenceId;
    }

    $customerId = trim((string) ($object['customer'] ?? ''));
    if ($customerId !== '') {
        return userIdByStripeCustomerId($customerId);
    }

    return null;
}

function stripeWebhookHandleCheckoutSession(PDO $pdo, array $session): void
{
    $userId = stripeWebhookResolveUserId($session);
    if ($userId === null || $userId <= 0) {
        return;
    }

    upsertUserSubscription($pdo, $userId, [
        'stripe_customer_id' => trim((string) ($session['customer'] ?? '')),
        'stripe_subscription_id' => is_string($session['subscription'] ?? null)
            ? trim((string) $session['subscription'])
            : trim((string) (($session['subscription']['id'] ?? ''))),
        'stripe_checkout_session_id' => trim((string) ($session['id'] ?? '')),
        'checkout_status' => trim((string) ($session['status'] ?? '')),
        'subscription_status' => trim((string) ($session['payment_status'] ?? 'pending_checkout')),
        'raw_payload_json' => json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ]);
}

function stripeWebhookHandleSubscription(PDO $pdo, array $subscription): void
{
    $userId = stripeWebhookResolveUserId($subscription);
    if (($userId === null || $userId <= 0) && !empty($subscription['customer'])) {
        $userId = userIdByStripeCustomerId((string) $subscription['customer']);
    }
    if ($userId === null || $userId <= 0) {
        return;
    }

    upsertUserSubscription($pdo, $userId, [
        'stripe_customer_id' => trim((string) ($subscription['customer'] ?? '')),
        'stripe_subscription_id' => trim((string) ($subscription['id'] ?? '')),
        'subscription_status' => trim((string) ($subscription['status'] ?? 'inactive')),
        'trial_end' => stripeTimestampToIso($subscription['trial_end'] ?? null),
        'current_period_end' => stripeTimestampToIso($subscription['current_period_end'] ?? null),
        'cancel_at' => stripeTimestampToIso($subscription['cancel_at'] ?? null),
        'raw_payload_json' => json_encode($subscription, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripeWebhookJsonResponse(405, [
        'ok' => false,
        'error' => 'Method Not Allowed',
    ]);
}

$webhookSecret = trim((string) envValue('STRIPE_WEBHOOK_SECRET', ''));
if ($webhookSecret === '') {
    stripeWebhookJsonResponse(500, [
        'ok' => false,
        'error' => 'STRIPE_WEBHOOK_SECRET não configurado.',
    ]);
}

$payload = file_get_contents('php://input');
$payload = is_string($payload) ? $payload : '';
$signatureHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($payload === '' || $signatureHeader === '') {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Payload ou assinatura Stripe ausente.',
    ]);
}

$parts = [];
foreach (explode(',', $signatureHeader) as $pair) {
    $pair = trim($pair);
    if ($pair === '' || !str_contains($pair, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $pair, 2);
    $parts[trim($key)][] = trim($value);
}

$timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
$v1Signatures = $parts['v1'] ?? [];
if ($timestamp <= 0 || !$v1Signatures) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Assinatura Stripe inválida.',
    ]);
}

if (abs(time() - $timestamp) > 300) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Assinatura Stripe expirada.',
    ]);
}

$signedPayload = $timestamp . '.' . $payload;
$expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
$signatureValid = false;
foreach ($v1Signatures as $signature) {
    if (hash_equals($expectedSignature, (string) $signature)) {
        $signatureValid = true;
        break;
    }
}

if (!$signatureValid) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Validação de assinatura Stripe falhou.',
    ]);
}

try {
    $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Payload JSON inválido.',
    ]);
}

if (!is_array($event)) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Evento Stripe inválido.',
    ]);
}

$eventType = trim((string) ($event['type'] ?? ''));
$object = $event['data']['object'] ?? null;
if (!is_array($object)) {
    stripeWebhookJsonResponse(400, [
        'ok' => false,
        'error' => 'Objeto do evento Stripe ausente.',
    ]);
}

$pdo = db();

switch ($eventType) {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
    case 'checkout.session.expired':
        stripeWebhookHandleCheckoutSession($pdo, $object);
        break;

    case 'customer.subscription.created':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        stripeWebhookHandleSubscription($pdo, $object);
        break;

    default:
        break;
}

stripeWebhookJsonResponse(200, [
    'ok' => true,
    'received' => $eventType,
]);
