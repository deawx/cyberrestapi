<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Paginator
 *      อ่าน page / per_page และสร้าง meta ตัดหน้า
 *      per_page สูงสุด 100
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Paginator
{
    /**
     * @return array{0: int, 1: int}
     */
    public static function fromRequest(Request $request, int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', $defaultPerPage);
        $perPage = max(1, min($maxPerPage, $perPage > 0 ? $perPage : $defaultPerPage));

        return [$page, $perPage];
    }

    /**
     * @return array{total: int, per_page: int, current_page: int, last_page: int, from: int, to: int}
     */
    public static function meta(int $total, int $page, int $perPage): array
    {
        $total = max(0, $total);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $last = max(1, (int) ceil($total / $perPage));

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $last,
            'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to' => $total === 0 ? 0 : min($page * $perPage, $total),
        ];
    }
}
