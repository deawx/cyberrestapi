<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Cipher
 *      เข้ารหัส AES-256-GCM จาก ENCRYPTION_KEY
 *      ใช้ Cipher::fromEnv() หรือ Cipher::generateKey()
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Cipher
{
    private string $key;    #Raw binary key (32 bytes)
    private string $cipher; #Algorithm name (default: aes-256-gcm)

    /**
     * @param string|null $key hex 64 ตัว หรือ base64 ของ 32 bytes — ห้ามเว้นว่างแล้วสุ่ม key ทิ้ง
     */
    public function __construct(?string $key = null, string $cipher = 'aes-256-gcm')
    {
        if ($key === null || $key === '') {
            throw new \RuntimeException('ENCRYPTION_KEY is required');
        }

        $rawKey = self::decodeKey($key);
        $this->validateKeyLength($rawKey);
        $this->key = $rawKey;
        $this->cipher = $cipher;
    }

    public static function fromEnv(): self
    {
        return new self($_ENV['ENCRYPTION_KEY'] ?? '');
    }

    /**
     * สร้าง key ที่ปลอดภัย (256-bit entropy)
     *
     * @return string Base64-encoded key (เหมาะสำหรับเก็บใน config หรือ DB)
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private static function decodeKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[0-9a-fA-F]{64}$/', $key) === 1) {
            $raw = hex2bin($key);
            if (is_string($raw)) {
                return $raw;
            }
        }

        $raw = base64_decode($key, true);
        if ($raw === false) {
            throw new \RuntimeException('Invalid encryption key');
        }

        return $raw;
    }

    /**
     * ตรวจสอบความยาว key
     */
    private function validateKeyLength(string $rawKey): void
    {
        if (strlen($rawKey) !== 32) {
            throw new \RuntimeException('Key must be 32 bytes (256-bit)');
        }
    }

    /**
     * เข้ารหัสข้อมูล
     *
     * @param string $data ข้อมูลที่ต้องการเข้ารหัส
     * @return string Base64-encoded string (IV || tag || ciphertext)
     * @throws \Exception ถ้าการเข้ารหัสล้มเหลวหรือข้อมูลว่าง
     */
    public function encrypt(string $data): string
    {
        if ($data === '') {
            throw new \RuntimeException('Data to encrypt cannot be empty');
        }

        $ivLength = $this->getIvLength();
        $iv = random_bytes($ivLength);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Format: IV + authentication tag + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * ถอดรหัสข้อมูล
     *
     * @param string $encryptedData Base64-encoded encrypted string
     * @return string ข้อมูลเดิมที่ถอดรหัสแล้ว
     * @throws \Exception ถ้าการถอดรหัสล้มเหลวหรือข้อมูลไม่ถูกต้อง
     */
    public function decrypt(string $encryptedData): string
    {
        if ($encryptedData === '') {
            throw new \RuntimeException('Encrypted data cannot be empty');
        }

        $data = base64_decode($encryptedData, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid base64-encoded encrypted data');
        }

        $ivLength = $this->getIvLength();
        $tagLength = 16; // GCM authentication tag is always 16 bytes

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $ciphertext = substr($data, $ivLength + $tagLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    /**
     * คืนค่าความยาว IV ตาม cipher ที่ใช้
     */
    private function getIvLength(): int
    {
        $length = openssl_cipher_iv_length($this->cipher);
        if ($length === false) {
            throw new \RuntimeException('Unsupported cipher');
        }

        return $length;
    }
}
