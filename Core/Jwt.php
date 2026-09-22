<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Jwt
 *      ออก / ตรวจ access JWT และ refresh token
 *      รองรับ revoke (blacklist jti + ลบ refresh)
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

final class Jwt
{
    private static ?string $storageOverride = null;

    public static function useStorage(?string $path): void
    {
        self::$storageOverride = $path;
    }

    public static function storage(): string
    {
        return self::$storageOverride ?? dirname(__DIR__) . '/Storage/cache/jwt';
    }

    public static function accessTtl(): int
    {
        return max(60, (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900));
    }

    public static function refreshTtl(): int
    {
        return max(300, (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800));
    }

    /**
     * @param array<string, mixed> $payload ต้องมี sub
     */
    public static function create(array $payload, ?string $secret = null, ?int $expiry = null): string
    {
        $secret = self::secret($secret);
        self::assertSecret($secret);
        if (empty($payload['sub'] ?? '')) {
            Response::error('JWT payload ต้องมี sub (subject)', 400);
        }

        $expiry ??= self::accessTtl();
        $now = time();
        $claims = $payload;
        unset($claims['iat'], $claims['exp'], $claims['nbf'], $claims['typ']);

        if (empty($claims['jti'])) {
            $claims['jti'] = bin2hex(random_bytes(16));
        }

        $token = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + max(1, $expiry),
            'typ' => 'access',
        ] + $claims;

        return FirebaseJwt::encode($token, $secret, 'HS256');
    }

    /**
     * ออก access + refresh พร้อมกัน (ใช้ตอน login)
     *
     * @param array<string, mixed> $payload
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public static function issue(array $payload, ?string $secret = null, ?int $accessTtl = null, ?int $refreshTtl = null): array
    {
        $accessTtl ??= self::accessTtl();
        $refreshTtl ??= self::refreshTtl();
        $access = self::create($payload, $secret, $accessTtl);
        $decoded = self::verify($access, $secret, false);
        if ($decoded === null) {
            Response::error('สร้าง access token ไม่สำเร็จ', 500);
        }

        $refresh = self::storeRefresh($decoded, $refreshTtl);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
        ];
    }

    /**
     * แลก refresh เป็นคู่โทเคนใหม่ (หมุนทั้ง access และ refresh)
     * access เก่าถูก blacklist ทันที refresh เก่าถูกลบทันที
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public static function refresh(string $refreshToken, ?string $secret = null, ?int $accessTtl = null, ?int $refreshTtl = null): ?array
    {
        $record = self::readRefresh($refreshToken);
        if ($record === null) {
            return null;
        }

        self::deleteRefresh($refreshToken);
        self::blacklistLinkedAccess($record);

        $payload = $record['claims'];
        $payload['sub'] = $record['sub'];
        if ($record['role'] !== null) {
            $payload['role'] = $record['role'];
        }
        unset($payload['jti'], $payload['iat'], $payload['exp'], $payload['nbf'], $payload['typ']);

        return self::issue($payload, $secret, $accessTtl, $refreshTtl);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, ?string $secret = null, bool $checkRevoke = true): ?array
    {
        $secret = self::secret($secret);
        if ($token === '' || strlen($secret) < 32) {
            return null;
        }

        try {
            $decoded = (array) FirebaseJwt::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            error_log('[JWT Error] ' . $e->getMessage());

            return null;
        }

        $typ = $decoded['typ'] ?? 'access';
        if ($typ !== 'access') {
            return null;
        }

        if ($checkRevoke) {
            $jti = isset($decoded['jti']) ? (string) $decoded['jti'] : '';
            if ($jti !== '' && self::isRevoked($jti)) {
                return null;
            }
        }

        if (random_int(1, 20) === 1) {
            self::pruneExpired();
        }

        return $decoded;
    }

    /** เพิกถอน access token และ refresh ที่ผูกกับ access นั้น (ถ้ามี) */
    public static function revoke(string $accessToken, ?string $secret = null): bool
    {
        $payload = self::verify($accessToken, $secret, false);
        if ($payload === null) {
            return false;
        }

        $jti = isset($payload['jti']) ? (string) $payload['jti'] : '';
        if ($jti === '') {
            return false;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        $ok = true;
        if ($exp > time()) {
            $ok = self::blacklist($jti, $exp);
        }

        // ลบ refresh ที่ผูก access_jti เสมอ — logout ไม่ต้องส่ง refresh_token มาด้วย
        self::deleteRefreshByAccessJti($jti);

        return $ok;
    }

    /** เพิกถอน refresh และ access ที่ผูกอยู่ด้วย */
    public static function revokeRefresh(string $refreshToken): bool
    {
        $record = self::readRefresh($refreshToken);
        if ($record === null) {
            return false;
        }

        self::blacklistLinkedAccess($record);
        self::deleteRefresh($refreshToken);

        return true;
    }

    /** logout: เพิกถอน access + refresh */
    public static function revokePair(?string $accessToken, ?string $refreshToken, ?string $secret = null): void
    {
        if (is_string($accessToken) && $accessToken !== '') {
            self::revoke($accessToken, $secret);
        }
        if (is_string($refreshToken) && $refreshToken !== '') {
            self::revokeRefresh($refreshToken);
        }
    }

    public static function isRevoked(string $jti): bool
    {
        $path = self::revokePath($jti);
        if (!is_file($path)) {
            return false;
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $exp = is_array($data) ? (int) ($data['exp'] ?? 0) : 0;
        if ($exp <= time()) {
            @unlink($path);

            return false;
        }

        return true;
    }

    public static function pruneExpired(?string $root = null): int
    {
        $root ??= self::storage();
        $removed = 0;
        $now = time();

        foreach ([self::revokeDir($root), self::refreshDir($root)] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.json') ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $raw = file_get_contents($path);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                $exp = is_array($data) ? (int) ($data['exp'] ?? 0) : 0;
                if ($exp > 0 && $exp <= $now && @unlink($path)) {
                    $removed++;
                }
                if ($removed >= 250) {
                    return $removed;
                }
            }
        }

        return $removed;
    }

    private static function assertSecret(string $secret): void
    {
        if (strlen($secret) < 32) {
            Response::error('JWT secret key ต้องยาวอย่างน้อย 32 ตัวอักษร', 500);
        }
    }

    private static function secret(?string $secret): string
    {
        return $secret ?? (string) ($_ENV['JWT_SECRET'] ?? '');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function storeRefresh(array $payload, int $ttl): string
    {
        $token = bin2hex(random_bytes(32));
        $dir = self::refreshDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Response::error('สร้างที่เก็บ refresh token ไม่ได้', 500);
        }

        $claims = $payload;
        unset($claims['iat'], $claims['exp'], $claims['nbf'], $claims['jti'], $claims['typ']);

        $record = [
            'sub' => (string) ($payload['sub'] ?? ''),
            'role' => isset($payload['role']) && is_string($payload['role']) ? $payload['role'] : null,
            'claims' => $claims,
            'access_jti' => isset($payload['jti']) ? (string) $payload['jti'] : null,
            'access_exp' => (int) ($payload['exp'] ?? 0),
            'exp' => time() + max(1, $ttl),
        ];

        $path = self::refreshPath($token);
        if (file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            Response::error('บันทึก refresh token ไม่สำเร็จ', 500);
        }

        return $token;
    }

    /**
     * @return array{
     *     sub: string,
     *     role: ?string,
     *     claims: array<string, mixed>,
     *     access_jti: ?string,
     *     access_exp: int,
     *     exp: int
     * }|null
     */
    private static function readRefresh(string $token): ?array
    {
        if ($token === '' || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $path = self::refreshPath($token);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return null;
        }

        $exp = (int) ($data['exp'] ?? 0);
        if ($exp <= time()) {
            @unlink($path);

            return null;
        }

        $claims = $data['claims'] ?? [];
        if (!is_array($claims)) {
            $claims = [];
        }

        $accessJti = $data['access_jti'] ?? null;

        return [
            'sub' => (string) ($data['sub'] ?? ''),
            'role' => isset($data['role']) && is_string($data['role']) ? $data['role'] : null,
            'claims' => $claims,
            'access_jti' => is_string($accessJti) && $accessJti !== '' ? $accessJti : null,
            'access_exp' => (int) ($data['access_exp'] ?? 0),
            'exp' => $exp,
        ];
    }

    /**
     * @param array{access_jti: ?string, access_exp: int} $record
     */
    private static function blacklistLinkedAccess(array $record): void
    {
        $jti = $record['access_jti'] ?? null;
        $exp = (int) ($record['access_exp'] ?? 0);
        if (!is_string($jti) || $jti === '' || $exp <= time()) {
            return;
        }

        self::blacklist($jti, $exp);
    }

    private static function deleteRefreshByAccessJti(string $accessJti): void
    {
        $dir = self::refreshDir();
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.json') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = file_get_contents($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                continue;
            }
            if (($data['access_jti'] ?? null) === $accessJti) {
                @unlink($path);
            }
        }
    }

    private static function deleteRefresh(string $token): void
    {
        $path = self::refreshPath($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function blacklist(string $jti, int $exp): bool
    {
        $dir = self::revokeDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $path = self::revokePath($jti);

        return file_put_contents(
            $path,
            json_encode(['exp' => $exp], JSON_THROW_ON_ERROR),
            LOCK_EX,
        ) !== false;
    }

    private static function refreshDir(?string $root = null): string
    {
        return ($root ?? self::storage()) . '/refresh';
    }

    private static function revokeDir(?string $root = null): string
    {
        return ($root ?? self::storage()) . '/revoke';
    }

    private static function refreshPath(string $token): string
    {
        return self::refreshDir() . '/' . hash('sha256', $token) . '.json';
    }

    private static function revokePath(string $jti): string
    {
        $safe = preg_replace('/[^a-fA-F0-9]/', '', $jti) ?? '';

        return self::revokeDir() . '/' . ($safe !== '' ? $safe : hash('sha256', $jti)) . '.json';
    }
}
