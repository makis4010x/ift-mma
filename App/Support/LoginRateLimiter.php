<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;
use RuntimeException;

/**
 * Database-backed login rate limiting.
 *
 * Only limits NEW login attempts.
 * Never logs out an already authenticated user.
 */
final class LoginRateLimiter
{
    private const WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;

    private const PER_EMAIL_AND_IP = 5;
    private const PER_IP = 25;

    public static function blocked(
        Database $db,
        ?string $email
    ): bool {
        foreach (array_keys(self::keys($email)) as $key) {
            $blocked = $db->query(
                'SELECT 1
                 FROM login_rate_limits
                 WHERE rate_key = :rate_key
                   AND blocked_until_utc > UTC_TIMESTAMP(6)
                 LIMIT 1',
                ['rate_key' => $key]
            )->fetchColumn();

            if ($blocked !== false) {
                return true;
            }
        }

        return false;
    }

    public static function failure(
        Database $db,
        ?string $email
    ): void {
        foreach (
            self::keys($email) as $key => $threshold
        ) {
            $db->transaction(
                static function (Database $db) use (
                    $key,
                    $threshold
                ): void {

                    // Create the counter if it does not exist.
                    $db->query(
                        'INSERT INTO login_rate_limits
                            (
                                rate_key,
                                attempts,
                                window_started_at_utc,
                                blocked_until_utc,
                                updated_at_utc
                            )
                         VALUES (
                             :rate_key,
                             0,
                             UTC_TIMESTAMP(6),
                             NULL,
                             UTC_TIMESTAMP(6)
                         )
                         ON DUPLICATE KEY UPDATE
                             rate_key = rate_key',
                        ['rate_key' => $key]
                    );

                    // Lock row to prevent concurrent increments.
                    $row = $db->query(
                        'SELECT
                            attempts,
                            window_started_at_utc,
                            blocked_until_utc,
                            UTC_TIMESTAMP(6) AS current_utc

                         FROM login_rate_limits

                         WHERE rate_key = :rate_key
                         FOR UPDATE',
                        ['rate_key' => $key]
                    )->fetch();

                    if (!is_array($row)) {
                        throw new RuntimeException(
                            'Login rate limit row missing.'
                        );
                    }

                    $zone = new DateTimeZone('UTC');

                    $now = new DateTimeImmutable(
                        (string) $row['current_utc'],
                        $zone
                    );

                    $start = new DateTimeImmutable(
                        (string) $row['window_started_at_utc'],
                        $zone
                    );

                    // Do not prolong an active lockout.
                    if ($row['blocked_until_utc'] !== null) {
                        $blockedUntil = new DateTimeImmutable(
                            (string) $row['blocked_until_utc'],
                            $zone
                        );

                        if ($blockedUntil > $now) {
                            return;
                        }
                    }

                    $expired = $start <= $now->modify(
                        '-' . self::WINDOW_MINUTES . ' minutes'
                    );

                    $attempts = $expired
                        ? 1
                        : (int) $row['attempts'] + 1;

                    $windowStart = $expired
                        ? $now->format('Y-m-d H:i:s.u')
                        : (string) $row['window_started_at_utc'];

                    $blockUntil = $attempts >= $threshold
                        ? $now->modify(
                            '+' . self::BLOCK_MINUTES . ' minutes'
                        )->format('Y-m-d H:i:s.u')
                        : null;

                    $db->query(
                        'UPDATE login_rate_limits
                         SET
                             attempts = :attempts,
                             window_started_at_utc = :window_start,
                             blocked_until_utc = :blocked_until,
                             updated_at_utc = UTC_TIMESTAMP(6)
                         WHERE rate_key = :rate_key',
                        [
                            'attempts' => $attempts,
                            'window_start' => $windowStart,
                            'blocked_until' => $blockUntil,
                            'rate_key' => $key
                        ]
                    );
                }
            );
        }
    }

    public static function success(
        Database $db,
        string $email
    ): void {
        // Reset only the email/IP counter on success.
        // Keep IP-wide failed-attempt history.
        $keys = array_keys(self::keys($email));

        if (isset($keys[1])) {
            $db->query(
                'DELETE FROM login_rate_limits
                 WHERE rate_key = :rate_key',
                ['rate_key' => $keys[1]]
            );
        }
    }

    /**
     * Use the server connection IP.
     * Do not trust X-Forwarded-For without a
     * separately configured trusted proxy.
     *
     * @return array<string, int>
     */
    private static function keys(?string $email): array
    {
        $ip = (string) (
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        $keys = [
            hash(
                'sha256',
                'ift-login-ip|' . $ip
            ) => self::PER_IP
        ];

        if ($email !== null && $email !== '') {
            $keys[hash(
                    'sha256',
                    'ift-login-pair|' . $ip . '|' . $email
                )] = self::PER_EMAIL_AND_IP;
        }

        return $keys;
    }
}
