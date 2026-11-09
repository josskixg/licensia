<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LicenseCryptoService;
use InvalidArgumentException;

/**
 * Laravel 11 API Controller for Licensia Server Operations:
 * - POST /api/v1/licenses/issue
 * - POST /api/v1/licenses/activate
 * - POST /api/v1/licenses/verify
 * - POST /api/v1/licenses/revoke
 */
final class LicenseServerController
{
    public function __construct(
        private readonly LicenseCryptoService $crypto = new LicenseCryptoService()
    ) {
    }

    /**
     * Issue a newly signed Ed25519 license certificate.
     *
     * @param array<string, mixed> $requestData
     * @return array<string, mixed>
     */
    public function issue(array $requestData, string $secretKeyHex): array
    {
        $tier = strtolower((string) ($requestData['tier'] ?? 'pro'));
        $defaultSeats = match ($tier) {
            'starter' => 1,
            'pro' => 5,
            'agency' => 25,
            default => 5,
        };

        $claims = [
            'license_id' => $requestData['license_id'] ?? ('lic_' . bin2hex(random_bytes(6))),
            'product_id' => (string) ($requestData['product_id'] ?? ''),
            'customer' => (string) ($requestData['customer'] ?? ''),
            'domain' => (string) ($requestData['domain'] ?? '*'),
            'tier' => $tier,
            'max_seats' => (int) ($requestData['max_seats'] ?? $defaultSeats),
            'expires_at' => (int) ($requestData['expires_at'] ?? (time() + 31536000)),
        ];

        $certificate = $this->crypto->issueCertificate($claims, $secretKeyHex);

        return [
            'ok' => true,
            'license_id' => $claims['license_id'],
            'certificate' => $certificate,
            'tier' => $tier,
            'max_seats' => $claims['max_seats'],
            'expires_at' => $claims['expires_at'],
        ];
    }

    /**
     * Activate a domain or hardware fingerprint seat against a signed certificate.
     *
     * @param array{certificate: string, domain_or_hwid: string, active_seats?: list<string>} $requestData
     * @return array<string, mixed>
     */
    public function activateSeat(array $requestData, string $publicKeyHex): array
    {
        $verification = $this->crypto->verifyCertificateOffline(
            (string) ($requestData['certificate'] ?? ''),
            $publicKeyHex,
            (string) ($requestData['domain_or_hwid'] ?? '')
        );

        if (!$verification['valid'] || !is_array($verification['payload'])) {
            return [
                'ok' => false,
                'http_code' => 422,
                'status' => $verification['status'],
                'reason' => $verification['reason'],
            ];
        }

        $seatResult = $this->crypto->validateSeatAllocation(
            $verification['payload'],
            $requestData['active_seats'] ?? [],
            (string) $requestData['domain_or_hwid']
        );

        return [
            'ok' => $seatResult['allowed'],
            'http_code' => $seatResult['allowed'] ? 200 : 409,
            'status' => $seatResult['status'],
            'seats_used' => $seatResult['seats_used'],
            'max_seats' => $seatResult['max_seats'],
            'license_id' => $verification['payload']['lid'],
            'signature_hex' => $verification['signature_hex'],
        ];
    }

    /**
     * Verify periodic online heartbeat and return cryptographic verification telemetry.
     *
     * @param array{certificate: string, domain_or_hwid?: string, revoked_ids?: list<string>} $requestData
     * @return array<string, mixed>
     */
    public function verifyHeartbeat(array $requestData, string $publicKeyHex): array
    {
        $verification = $this->crypto->verifyCertificateOffline(
            (string) ($requestData['certificate'] ?? ''),
            $publicKeyHex,
            isset($requestData['domain_or_hwid']) ? (string) $requestData['domain_or_hwid'] : null
        );

        if (!$verification['valid'] || !is_array($verification['payload'])) {
            return [
                'ok' => false,
                'status' => $verification['status'],
                'reason' => $verification['reason'],
            ];
        }

        $revokedIds = $requestData['revoked_ids'] ?? [];
        if (in_array($verification['payload']['lid'], $revokedIds, true)) {
            return [
                'ok' => false,
                'status' => 'REVOKED_LICENSE',
                'reason' => 'License ID has been revoked by the publisher.',
                'license_id' => $verification['payload']['lid'],
            ];
        }

        return [
            'ok' => true,
            'status' => 'VALID_CRYPTOGRAPHIC_SEAL',
            'license_id' => $verification['payload']['lid'],
            'payload' => $verification['payload'],
            'signature_hex' => $verification['signature_hex'],
        ];
    }

    /**
     * Revoke a license key by ID and record revocation reason.
     *
     * @param array{license_id: string, reason?: string} $requestData
     * @return array<string, mixed>
     */
    public function revoke(array $requestData): array
    {
        $licenseId = trim((string) ($requestData['license_id'] ?? ''));
        if ($licenseId === '') {
            throw new InvalidArgumentException('license_id is required to revoke a certificate.');
        }

        return [
            'ok' => true,
            'status' => 'REVOKED_LICENSE',
            'license_id' => $licenseId,
            'reason' => (string) ($requestData['reason'] ?? 'Publisher administrative revocation'),
            'revoked_at' => time(),
        ];
    }
}
