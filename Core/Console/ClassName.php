<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\ClassName
 *      แปลงชื่อที่ผู้ใช้พิมพ์เป็น PascalCase
 *      ตัดอักขระพิเศษแล้วใช้กับคำสั่ง make:*
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

final class ClassName
{
    public static function pascal(string $name, string $stripSuffix = ''): string
    {
        $name = str_replace(['/', '\\'], '', $name);
        if ($stripSuffix !== '') {
            $quoted = preg_quote($stripSuffix, '/');
            $name = preg_replace('/' . $quoted . '$/i', '', $name) ?? $name;
        }
        $name = preg_replace('/[^A-Za-z0-9_-]/u', '', $name) ?? $name;
        $name = strtolower($name);

        $class = '';
        foreach (preg_split('/[_-]+/', $name) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            $class .= ucfirst($part);
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1) {
            return '';
        }

        return $class;
    }

    public static function snake(string $pascal): string
    {
        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $pascal) ?? $pascal;

        return strtolower($snake);
    }
}
