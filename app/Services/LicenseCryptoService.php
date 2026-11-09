<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Licensia Cryptographic Licensing & Offline Activation Engine.
 *
 * Uses native PHP 8.2 ext-sodium (Ed25519 / Curve25519 asymmetric signatures)
 * so buyer applications can verify license authenticity and domain/HWID binding
 * 100% offline using only the 32-byte public key.
 */
final class LicenseCryptoService
{
    public const PREFIX = 'LIC-v1';

    /**
     * Generate a fresh Ed25519 asymmetric keypair in hexadecimal format.
     *
     * @return array{public_key_hex: string, secret_key_hex: string}
     */
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'public_key_hex' => sodium_bin2hex($publicKey),
            'secret_key_hex' => sodium_bin2hex($secretKey),
        ];
    }

    /**
     * Deterministically derive an Ed25519 keypair from a 32-byte hex seed.
     *
     * @return array{public_key_hex: string, secret_key_hex: string}
     */
    public function generateKeyPairFromSeed(string $seedHex): array
    {
        $seed = sodium_hex2bin($seedHex);
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new InvalidArgumentException('Seed must be exactly ' . SODIUM_CRYPTO_SIGN_SEEDBYTES . ' bytes (64 hex chars).');
        }

        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return [
            'public_key_hex' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'secret_key_hex' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    /**
     * Normalize and hash a domain or hardware fingerprint (HWID) using keyed BLAKE2b / SHA-256.
     */
    public function hashEnvironmentBinding(string $domainOrHwid): string
    {
        $normalized = strtolower(trim($domainOrHwid));
        $normalized = (string) preg_replace('#^https?://#', '', $normalized);
        $normalized = rtrim($normalized, '/');

        return substr(hash('sha256', 'licensia:v1:' . $normalized), 0, 24);
    }

    /**
     * Issue and sign an Ed25519 offline-verifiable license certificate.
     *
     * @param array{
     *   product_id: string,
     *   customer: string,
     *   domain?: string,
     *   hwid?: string,
     *   expires_at: int,
     *   tier?: string,
     *   max_seats?: int,
     *   license_id?: string
     * } $claims
     */
    public function issueCertificate(array $claims, string $secretKeyHex): string
    {
        foreach (['product_id', 'customer', 'expires_at'] as $required) {
            if (!isset($claims[$required]) || $claims[$required] === '') {
                throw new InvalidArgumentException("Missing required license claim: {$required}");
            }
        }

        $secretKey = $this->decodeHexKey($secretKeyHex, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'secret');
        $bindingTarget = (string) ($claims['domain'] ?? $claims['hwid'] ?? '*');

        $payload = [
            'ver' => 1,
            'lid' => (string) ($claims['license_id'] ?? ('lic_' . bin2hex(random_bytes(6)))),
            'prd' => (string) $claims['product_id'],
            'cst' => (string) $claims['customer'],
            'bnd' => $bindingTarget === '*' ? '*' : $this->hashEnvironmentBinding($bindingTarget),
            'env' => $bindingTarget,
            'tir' => strtolower((string) ($claims['tier'] ?? 'pro')),
            'sts' => max(1, (int) ($claims['max_seats'] ?? 5)),
            'iat' => (int) ($claims['issued_at'] ?? time()),
            'exp' => (int) $claims['expires_at'],
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encodedPayload = $this->base64UrlEncode($jsonPayload);
        $signingInput = self::PREFIX . '.' . $encodedPayload;

        $signature = sodium_crypto_sign_detached($signingInput, $secretKey);
        $encodedSignature = $this->base64UrlEncode($signature);

        return $signingInput . '.' . $encodedSignature;
    }

    /**
     * Verify an Ed25519-signed license certificate offline using only the 32-byte public key.
     *
     * @return array{
     *   valid: bool,
     *   status: string,
     *   reason: string|null,
     *   payload: array<string, mixed>|null,
     *   signature_hex: string|null
     * }
     */
    public function verifyCertificateOffline(
        string $certificate,
        string $publicKeyHex,
        ?string $expectedDomainOrHwid = null,
        ?int $currentTimestamp = null
    ): array {
        $parts = explode('.', trim($certificate));
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return [
                'valid' => false,
                'status' => 'MALFORMED_CERTIFICATE',
                'reason' => 'Certificate must match LIC-v1.<payload>.<signature> structure.',
                'payload' => null,
                'signature_hex' => null,
            ];
        }

        [$prefix, $encodedPayload, $encodedSignature] = $parts;
        $publicKey = $this->decodeHexKey($publicKeyHex, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, 'public');

        $rawSignature = $this->base64UrlDecode($encodedSignature);
        if ($rawSignature === false || strlen($rawSignature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return [
                'valid' => false,
                'status' => 'TAMPERED_SIGNATURE',
                'reason' => 'Ed25519 signature must be exactly 64 bytes.',
                'payload' => null,
                'signature_hex' => null,
            ];
        }

        $signingInput = $prefix . '.' . $encodedPayload;
        $isSignatureValid = sodium_crypto_sign_verify_detached($rawSignature, $signingInput, $publicKey);

        $rawPayloadJson = $this->base64UrlDecode($encodedPayload);
        $decodedPayload = is_string($rawPayloadJson) ? json_decode($rawPayloadJson, true) : null;

        if (!$isSignatureValid) {
            return [
                'valid' => false,
                'status' => 'TAMPERED_SIGNATURE',
                'reason' => 'Ed25519 detached signature verification failed. Payload or signature was altered.',
                'payload' => is_array($decodedPayload) ? $decodedPayload : null,
                'signature_hex' => sodium_bin2hex($rawSignature),
            ];
        }

        if (!is_array($decodedPayload) || !isset($decodedPayload['exp'], $decodedPayload['bnd'], $decodedPayload['prd'])) {
            return [
                'valid' => false,
                'status' => 'CORRUPTED_PAYLOAD',
                'reason' => 'Payload JSON is missing mandatory claims (prd, bnd, exp).',
                'payload' => null,
                'signature_hex' => sodium_bin2hex($rawSignature),
            ];
        }

        $now = $currentTimestamp ?? time();
        if ((int) $decodedPayload['exp'] < $now) {
            return [
                'valid' => false,
                'status' => 'EXPIRED_LICENSE',
                'reason' => sprintf('License expired at UNIX timestamp %d (current: %d).', (int) $decodedPayload['exp'], $now),
                'payload' => $decodedPayload,
                'signature_hex' => sodium_bin2hex($rawSignature),
            ];
        }

        if ($expectedDomainOrHwid !== null && $decodedPayload['bnd'] !== '*') {
            $expectedHash = $this->hashEnvironmentBinding($expectedDomainOrHwid);
            if (!hash_equals((string) $decodedPayload['bnd'], $expectedHash)) {
                return [
                    'valid' => false,
                    'status' => 'DOMAIN_MISMATCH',
                    'reason' => sprintf(
                        'Environment binding hash mismatch (expected %s for %s, certificate bound to %s).',
                        $expectedHash,
                        $expectedDomainOrHwid,
                        (string) $decodedPayload['bnd']
                    ),
                    'payload' => $decodedPayload,
                    'signature_hex' => sodium_bin2hex($rawSignature),
                ];
            }
        }

        return [
            'valid' => true,
            'status' => 'VALID_CRYPTOGRAPHIC_SEAL',
            'reason' => null,
            'payload' => $decodedPayload,
            'signature_hex' => sodium_bin2hex($rawSignature),
        ];
    }

    /**
     * Evaluate whether a new domain/HWID seat activation is permitted under the license quota.
     *
     * @param list<string> $activeFingerprints
     * @return array{allowed: bool, status: string, seats_used: int, max_seats: int}
     */
    public function validateSeatAllocation(array $payload, array $activeFingerprints, string $requestFingerprint): array
    {
        $maxSeats = max(1, (int) ($payload['sts'] ?? 1));
        $normalizedActive = array_values(array_unique(array_map([$this, 'hashEnvironmentBinding'], $activeFingerprints)));
        $candidateHash = $this->hashEnvironmentBinding($requestFingerprint);

        if (in_array($candidateHash, $normalizedActive, true)) {
            return [
                'allowed' => true,
                'status' => 'SEAT_ALREADY_ACTIVE',
                'seats_used' => count($normalizedActive),
                'max_seats' => $maxSeats,
            ];
        }

        if (count($normalizedActive) >= $maxSeats) {
            return [
                'allowed' => false,
                'status' => 'SEAT_QUOTA_EXCEEDED',
                'seats_used' => count($normalizedActive),
                'max_seats' => $maxSeats,
            ];
        }

        return [
            'allowed' => true,
            'status' => 'SEAT_ACTIVATED',
            'seats_used' => count($normalizedActive) + 1,
            'max_seats' => $maxSeats,
        ];
    }

    private function decodeHexKey(string $hex, int $expectedBytes, string $label): string
    {
        $clean = trim($hex);
        if (strlen($clean) !== $expectedBytes * 2 || !ctype_xdigit($clean)) {
            throw new InvalidArgumentException(
                sprintf('Invalid Ed25519 %s key: expected %d hex characters.', $label, $expectedBytes * 2)
            );
        }

        $binary = sodium_hex2bin($clean);
        if (strlen($binary) !== $expectedBytes) {
            throw new RuntimeException("Failed decoding {$label} key binary.");
        }

        return $binary;
    }

    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
