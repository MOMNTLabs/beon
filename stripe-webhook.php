<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function webhookResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function stripeSignatureForPayload(string $payload, string $secret, int $timestamp): string
{
    return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
}

function stripeVerifyWebhookSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $parts = [];
    foreach (explode(',', $header) as $piece) {
        $tokens = explode('=', trim($piece), 2);
        if (count($tokens) !== 2) {
            continue;
        }
        $parts[trim($tokens[0])][] = trim($tokens[1]);
    }

    $timestamp = (int) ($parts['t'][0] ?? 0);
    if ($timestamp <= 0 || abs(time() - $timestamp) > $tolerance) {
        return false;
    }

    $expected = stripeSignatureForPayload($payload, $secret, $timestamp);
    foreach (($parts['v1'] ?? []) as $signature) {
        if (hash_equals($expected, (string) $signature)) {
            return true;
        }
    }

    return false;
}

function stripeObjectUserId(array $object): ?int
{
    $metadata = $object['metadata'] ?? [];
    $metadataUserId = (int) ($metadata['bexon_user_id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhookResponse(405, ['ok' => false, 'error' => 'Method not allowed']);
}

$webhookSecret = trim((string) (envValue('STRIPE_WEBHOOK_SECRET') ?? ''));
if ($webhookSecret === '') {
    webhookResponse(500, ['ok' => false, 'error' => 'Missing STRIPE_WEBHOOK_SECRET']);
}

$payload = file_get_contents('php://input');
if (!is_string($payload) || $payload === '') {
    webhookResponse(400, ['ok' => false, 'error' => 'Empty payload']);
}

$signatureHeader = trim((string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
if ($signatureHeader === '' || !stripeVerifyWebhookSignature($payload, $signatureHeader, $webhookSecret)) {
    webhookResponse(400, ['ok' => false, 'error' => 'Invalid signature']);
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    webhookResponse(400, ['ok' => false, 'error' => 'Invalid payload']);
}

$eventType = trim((string) ($event['type'] ?? ''));
$object = $event['data']['object'] ?? null;
if (!is_array($object)) {
    webhookResponse(400, ['ok' => false, 'error' => 'Invalid event object']);
}

$pdo = db();
$userId = stripeObjectUserId($object);

if ($userId === null && str_starts_with($eventType, 'customer.subscription.')) {
    $customerId = trim((string) ($object['customer'] ?? ''));
    if ($customerId !== '') {
        $userId = userIdByStripeCustomerId($customerId);
    }
}

if ($userId === null || $userId <= 0) {
    webhookResponse(200, ['ok' => true, 'ignored' => 'user_not_found']);
}

$rawPayload = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

switch ($eventType) {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
    case 'checkout.session.expired':
        $subscription = $object['subscription'] ?? null;
        if (is_array($subscription)) {
            $subscriptionId = (string) ($subscription['id'] ?? '');
            $subscriptionStatus = (string) ($subscription['status'] ?? 'inactive');
            $trialEnd = stripeTimestampToIso($subscription['trial_end'] ?? null);
            $currentPeriodEnd = stripeTimestampToIso($subscription['current_period_end'] ?? null);
            $cancelAt = stripeTimestampToIso($subscription['cancel_at'] ?? null);
        } else {
            $subscriptionId = is_string($subscription) ? $subscription : '';
            $subscriptionStatus = $eventType === 'checkout.session.completed' ? 'active' : 'inactive';
            $trialEnd = null;
            $currentPeriodEnd = null;
            $cancelAt = null;
        }

        $checkoutStatus = $eventType === 'checkout.session.expired'
            ? 'expired'
            : trim((string) ($object['status'] ?? 'completed'));

        upsertUserSubscription($pdo, $userId, [
            'stripe_customer_id' => trim((string) ($object['customer'] ?? '')),
            'stripe_subscription_id' => $subscriptionId,
            'stripe_checkout_session_id' => trim((string) ($object['id'] ?? '')),
            'subscription_status' => $subscriptionStatus,
            'checkout_status' => $checkoutStatus,
            'trial_end' => $trialEnd,
            'current_period_end' => $currentPeriodEnd,
            'cancel_at' => $cancelAt,
            'raw_payload_json' => $rawPayload,
        ]);
        break;

    case 'customer.subscription.created':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        $status = trim((string) ($object['status'] ?? 'inactive'));
        if ($eventType === 'customer.subscription.deleted') {
            $status = 'canceled';
        }

        upsertUserSubscription($pdo, $userId, [
            'stripe_customer_id' => trim((string) ($object['customer'] ?? '')),
            'stripe_subscription_id' => trim((string) ($object['id'] ?? '')),
            'subscription_status' => $status,
            'checkout_status' => 'completed',
            'trial_end' => stripeTimestampToIso($object['trial_end'] ?? null),
            'current_period_end' => stripeTimestampToIso($object['current_period_end'] ?? null),
            'cancel_at' => stripeTimestampToIso($object['cancel_at'] ?? null),
            'raw_payload_json' => $rawPayload,
        ]);
        break;

    default:
        webhookResponse(200, ['ok' => true, 'ignored' => 'event_not_handled']);
}

webhookResponse(200, ['ok' => true]);
