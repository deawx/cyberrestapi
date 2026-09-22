<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Upload;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UploadTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cyberrestapi-upload-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
        unset($_FILES['avatar']);
    }

    public function testStoreCopiesAllowedFile(): void
    {
        $tmp = $this->root . '/source.png';
        file_put_contents($tmp, "\x89PNG\r\n\x1a\n" . str_repeat('x', 32));
        $_FILES['avatar'] = [
            'name' => 'photo.png',
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];

        $stored = Upload::store('avatar', 'avatars', ['png'], 1024 * 1024, $this->root);

        $this->assertSame('photo.png', $stored['name']);
        $this->assertStringStartsWith('avatars/', $stored['path']);
        $this->assertFileExists($this->root . '/' . $stored['path']);
    }

    public function testStoreRejectsDeniedExtension(): void
    {
        $tmp = $this->root . '/shell.php';
        file_put_contents($tmp, '<?php echo 1;');
        $_FILES['avatar'] = [
            'name' => 'shell.php',
            'type' => 'text/plain',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];

        $this->expectException(RuntimeException::class);
        Upload::store('avatar', 'avatars', ['png'], 1024 * 1024, $this->root);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir . '/' . $name;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
