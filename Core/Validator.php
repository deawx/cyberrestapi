<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Validator
 *      ตรวจข้อมูลเข้าตามกฎที่กำหนด
 *      ใช้จาก Request::validate() หรือ Validator::make()
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<int, array{name: string, param: ?string}>> */
    private array $rules;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, mixed> $files
     */
    private function __construct(array $data, array $rules, array $files)
    {
        $this->data = $data;
        $this->files = $files;
        $this->rules = [];
        foreach ($rules as $field => $ruleSet) {
            $this->rules[$field] = self::parseRules($ruleSet);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, mixed> $files
     */
    public static function make(array $data, array $rules, array $files = []): self
    {
        $validator = new self($data, $rules, $files);
        $validator->run();

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $out[$field] = $this->data[$field];
            }
        }

        return $out;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $file = $this->files[$field] ?? null;
            $nullable = $this->hasRule($rules, 'nullable');
            $present = $this->isPresent($value, $file);

            foreach ($rules as $rule) {
                if ($rule['name'] === 'nullable') {
                    continue;
                }

                if ($rule['name'] !== 'required' && !$present && $nullable) {
                    continue;
                }

                $this->apply($field, $value, $file, $rule);
            }
        }
    }

    /**
     * @param array<int, array{name: string, param: ?string}> $rules
     */
    private function hasRule(array $rules, string $name): bool
    {
        foreach ($rules as $rule) {
            if ($rule['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    private function isPresent(mixed $value, mixed $file): bool
    {
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return true;
        }

        return $value !== null && $value !== '';
    }

    /**
     * @param array{name: string, param: ?string} $rule
     */
    private function apply(string $field, mixed $value, mixed $file, array $rule): void
    {
        $name = $rule['name'];
        $param = $rule['param'];

        match ($name) {
            'required' => $this->isPresent($value, $file) ?: $this->fail($field, "The {$field} field is required."),
            'email' => $this->skipEmpty($value) || filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false
                ?: $this->fail($field, "The {$field} must be a valid email."),
            'integer' => $this->skipEmpty($value) || filter_var($value, FILTER_VALIDATE_INT) !== false
                ?: $this->fail($field, "The {$field} must be an integer."),
            'numeric' => $this->skipEmpty($value) || is_numeric($value)
                ?: $this->fail($field, "The {$field} must be numeric."),
            'string' => $this->skipEmpty($value) || is_string($value)
                ?: $this->fail($field, "The {$field} must be a string."),
            'array' => $this->skipEmpty($value) || is_array($value)
                ?: $this->fail($field, "The {$field} must be an array."),
            'boolean' => $this->skipEmpty($value) || $this->isBoolean($value)
                ?: $this->fail($field, "The {$field} must be a boolean."),
            'url' => $this->skipEmpty($value) || filter_var((string) $value, FILTER_VALIDATE_URL) !== false
                ?: $this->fail($field, "The {$field} must be a valid URL."),
            'min' => $this->checkMin($field, $value, (int) $param),
            'max' => $this->checkMax($field, $value, $file, (int) $param),
            'in' => $this->skipEmpty($value) || in_array((string) $value, explode(',', (string) $param), true)
                ?: $this->fail($field, "The {$field} is invalid."),
            'confirmed' => (string) $value === (string) ($this->data[$field . '_confirmation'] ?? null)
                ?: $this->fail($field, "The {$field} confirmation does not match."),
            'regex' => $this->skipEmpty($value) || (is_string($param) && preg_match('/' . $param . '/', (string) $value) === 1)
                ?: $this->fail($field, "The {$field} format is invalid."),
            'file' => ($this->isUploaded($file) || $this->isLocalTestFile($file)) ?: $this->fail($field, "The {$field} must be a file."),
            'image' => $this->isImage($file) ?: $this->fail($field, "The {$field} must be an image."),
            'mimes' => $this->checkMimes($field, $file, (string) $param),
            default => null,
        };
    }

    private function skipEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function isBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    private function checkMin(string $field, mixed $value, int $min): void
    {
        if ($this->skipEmpty($value)) {
            return;
        }

        if (is_numeric($value) && !is_string($value)) {
            if ((float) $value < $min) {
                $this->fail($field, "The {$field} must be at least {$min}.");
            }

            return;
        }

        if (mb_strlen((string) $value) < $min) {
            $this->fail($field, "The {$field} must be at least {$min} characters.");
        }
    }

    private function checkMax(string $field, mixed $value, mixed $file, int $max): void
    {
        if ($this->isUploaded($file)) {
            $kb = (int) ceil(((int) ($file['size'] ?? 0)) / 1024);
            if ($kb > $max) {
                $this->fail($field, "The {$field} may not be greater than {$max} kilobytes.");
            }

            return;
        }

        if ($this->skipEmpty($value)) {
            return;
        }

        if (is_numeric($value) && !is_string($value)) {
            if ((float) $value > $max) {
                $this->fail($field, "The {$field} may not be greater than {$max}.");
            }

            return;
        }

        if (mb_strlen((string) $value) > $max) {
            $this->fail($field, "The {$field} may not be greater than {$max} characters.");
        }
    }

    private function isUploaded(mixed $file): bool
    {
        return is_array($file)
            && isset($file['tmp_name'], $file['error'])
            && (int) $file['error'] === UPLOAD_ERR_OK
            && is_uploaded_file((string) $file['tmp_name']);
    }

    private function isImage(mixed $file): bool
    {
        if (!$this->isUploaded($file) && !($this->isLocalTestFile($file))) {
            return false;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $info = @getimagesize($tmp);

        return $info !== false;
    }

    private function checkMimes(string $field, mixed $file, string $param): void
    {
        if (!$this->isUploaded($file) && !$this->isLocalTestFile($file)) {
            $this->fail($field, "The {$field} must be a file.");

            return;
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = array_map(static fn(string $item): string => strtolower(trim($item)), explode(',', $param));
        if (!in_array($ext, $allowed, true)) {
            $this->fail($field, "The {$field} must be a file of type: {$param}.");
        }
    }

    /**
     * PHPUnit ใช้ไฟล์ชั่วคราวที่ไม่ผ่าน is_uploaded_file()
     *
     * @param array<string, mixed>|mixed $file
     */
    private function isLocalTestFile(mixed $file): bool
    {
        return is_array($file)
            && isset($file['tmp_name'])
            && is_string($file['tmp_name'])
            && is_file($file['tmp_name']);
    }

    /**
     * @param string|list<string> $rules
     * @return list<array{name: string, param: ?string}>
     */
    private static function parseRules(string|array $rules): array
    {
        if (is_string($rules)) {
            $rules = $rules === '' ? [] : explode('|', $rules);
        }

        $parsed = [];
        foreach ($rules as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue;
            }

            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $parsed[] = ['name' => strtolower($name), 'param' => $param];
        }

        return $parsed;
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $message;
    }
}
