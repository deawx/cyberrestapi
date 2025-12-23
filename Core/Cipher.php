<?php

declare(strict_types=1);

namespace Core;

/**
 * Secure encryption/decryption class using AES-256-GCM (Authenticated Encryption)
 *
 * รองรับ PHP 7.4 ขึ้นไป
 * ใช้ AEAD (GCM) ซึ่งปลอดภัยและทันสมัยกว่าการใช้ CBC + HMAC แยก
 */
/*
#ตัวอย่างการใช้งาน:
#สร้าง instance พร้อม key ใหม่
$cipher = new Cipher();

#หรือใช้ key ที่เก็บไว้
$savedKey = 'ฐาน64ของคุณ...';
$cipher = new Cipher($savedKey);

$encrypted = $cipher->encrypt('ข้อมูลลับมาก');
echo $encrypted; #string base64

$decrypted = $cipher->decrypt($encrypted);
echo $decrypted; #ข้อมูลลับมาก
*/
final class Cipher {
    private string $key;    #Raw binary key (32 bytes)
    private string $cipher; #Algorithm name (default: aes-256-gcm)

    /**
     * @param string|null $key Base64-encoded key. ถ้าเป็น null จะสร้าง key ใหม่
     * @param string      $cipher ชื่อ algorithm (แนะนำ aes-256-gcm)
     */
    public function __construct(string|null $key = null, string $cipher = 'aes-256-gcm') {
        if ($key === null) {
            $key = self::generateKey();
        }

        $rawKey = base64_decode($key, true);
        if ($rawKey === false) {
            throw new \Exception('Invalid base64-encoded key provided');
        }

        $this->validateKeyLength($rawKey, $cipher);
        $this->key = $rawKey;
        $this->cipher = $cipher;
    }

    /**
     * สร้าง key ที่ปลอดภัย (256-bit entropy)
     *
     * @return string Base64-encoded key (เหมาะสำหรับเก็บใน config หรือ DB)
     */
    public static function generateKey(): string {
        return base64_encode(random_bytes(32));
    }

    /**
     * ตรวจสอบความยาว key
     */
    private function validateKeyLength(string $rawKey, string $cipher): void {
        if (strlen($rawKey) !== 32) {
            throw new \Exception("Key must be 32 bytes (256-bit) for cipher {$cipher}");
        }
    }

    /**
     * เข้ารหัสข้อมูล
     *
     * @param string $data ข้อมูลที่ต้องการเข้ารหัส
     * @return string Base64-encoded string (IV || tag || ciphertext)
     * @throws \Exception ถ้าการเข้ารหัสล้มเหลวหรือข้อมูลว่าง
     */
    public function encrypt(string $data): string {
        if ($data === '') {
            throw new \Exception('Data to encrypt cannot be empty');
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
            $tag
        );

        if ($ciphertext === false) {
            throw new \Exception('Encryption failed: ' . openssl_error_string());
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
    public function decrypt(string $encryptedData): string {
        if ($encryptedData === '') {
            throw new \Exception('Encrypted data cannot be empty');
        }

        $data = base64_decode($encryptedData, true);
        if ($data === false) {
            throw new \Exception('Invalid base64-encoded encrypted data');
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
            $tag
        );

        if ($plaintext === false) {
            throw new \Exception('Decryption failed: ' . openssl_error_string());
        }

        return $plaintext;
    }

    /**
     * คืนค่าความยาว IV ตาม cipher ที่ใช้
     */
    private function getIvLength(): int {
        $length = openssl_cipher_iv_length($this->cipher);
        if ($length === false) {
            throw new \Exception('Unsupported cipher: ' . $this->cipher);
        }

        return $length;
    }
}
