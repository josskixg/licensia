<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/LicenseCryptoService.php';
require_once __DIR__ . '/../../app/Http/Controllers/LicenseServerController.php';

use App\Http\Controllers\LicenseServerController;
use App\Services\LicenseCryptoService;

$crypto = new LicenseCryptoService();
$controller = new LicenseServerController($crypto);

// 1. Real PHP 8.2 libsodium Ed25519 key generation
$keys = $crypto->generateKeyPair();
assert(strlen($keys['public_key_hex']) === 64, 'Public key must be 32 bytes (64 hex chars)');
assert(strlen($keys['secret_key_hex']) === 128, 'Secret key must be 64 bytes (128 hex chars)');

// 2. License signing & offline verification
$futureExpiry = time() + 86400 * 365;
$certificate = $crypto->issueCertificate([
    'license_id' => 'lic_test_001',
    'product_id' => 'larasaas-pro-v4',
    'customer' => 'founder@acme.id',
    'domain' => 'app.acme.id',
    'tier' => 'pro',
    'max_seats' => 2,
    'expires_at' => $futureExpiry,
], $keys['secret_key_hex']);

assert(str_starts_with($certificate, 'LIC-v1.'), 'Certificate must start with LIC-v1.');

$validCheck = $crypto->verifyCertificateOffline($certificate, $keys['public_key_hex'], 'https://app.acme.id/');
assert($validCheck['valid'] === true, 'Valid certificate must pass offline verification');
assert($validCheck['status'] === 'VALID_CRYPTOGRAPHIC_SEAL', 'Status must be VALID_CRYPTOGRAPHIC_SEAL');
assert($validCheck['payload']['prd'] === 'larasaas-pro-v4', 'Product claim must match');

// 3. Tamper detection when payload is altered by 1 char
$parts = explode('.', $certificate);
$payloadChars = $parts[1];
$lastChar = substr($payloadChars, -1);
$mutatedChar = $lastChar === 'A' ? 'B' : 'A';
$tamperedCertificate = $parts[0] . '.' . substr($payloadChars, 0, -1) . $mutatedChar . '.' . $parts[2];

$tamperCheck = $crypto->verifyCertificateOffline($tamperedCertificate, $keys['public_key_hex'], 'app.acme.id');
assert($tamperCheck['valid'] === false, 'Tampered payload must fail Ed25519 verification');
assert($tamperCheck['status'] === 'TAMPERED_SIGNATURE', 'Tampered payload must report TAMPERED_SIGNATURE');

// 4. Expiry check
$expiredCertificate = $crypto->issueCertificate([
    'license_id' => 'lic_expired_002',
    'product_id' => 'larasaas-pro-v4',
    'customer' => 'expired@acme.id',
    'domain' => 'app.acme.id',
    'tier' => 'starter',
    'max_seats' => 1,
    'expires_at' => time() - 3600,
], $keys['secret_key_hex']);

$expiryCheck = $crypto->verifyCertificateOffline($expiredCertificate, $keys['public_key_hex'], 'app.acme.id');
assert($expiryCheck['valid'] === false, 'Expired certificate must be rejected');
assert($expiryCheck['status'] === 'EXPIRED_LICENSE', 'Expired certificate must report EXPIRED_LICENSE');

// 5. Domain mismatch check
$domainMismatchCheck = $crypto->verifyCertificateOffline($certificate, $keys['public_key_hex'], 'pirated-clone.xyz');
assert($domainMismatchCheck['valid'] === false, 'Mismatched domain must be rejected');
assert($domainMismatchCheck['status'] === 'DOMAIN_MISMATCH', 'Mismatched domain must report DOMAIN_MISMATCH');

// 6. Seat quota & controller lifecycle check
$seat1 = $controller->activateSeat([
    'certificate' => $certificate,
    'domain_or_hwid' => 'app.acme.id',
    'active_seats' => ['node-1.acme.id'],
], $keys['public_key_hex']);
assert($seat1['ok'] === true && $seat1['status'] === 'SEAT_ACTIVATED', 'Second seat within max_seats=2 must activate');

$seatOverflow = $controller->activateSeat([
    'certificate' => $certificate,
    'domain_or_hwid' => 'app.acme.id',
    'active_seats' => ['node-1.acme.id', 'node-2.acme.id'],
], $keys['public_key_hex']);
assert($seatOverflow['ok'] === false && $seatOverflow['status'] === 'SEAT_QUOTA_EXCEEDED', 'Third seat exceeding max_seats=2 must fail');

echo "PASS: All 6 Licensia Ed25519 cryptographic licensing & offline verification assertions succeeded.\n";
