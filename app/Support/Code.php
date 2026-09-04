<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * 业务码生成：前缀+日期+随机串，带 DB 查重重试。
 * 统一 TraceCode/GiftBox 等同类「每物一码」生成逻辑，避免各服务各写一份。
 */
class Code
{
    /**
     * 生成 {prefix}{YYYYMMDD}-{length 位随机大写} 码，查重失败重试 5 次后抛错。
     *
     * @param  callable  $exists  fn(string $code): bool，true 表示码已占用
     * @param  string  $label  碰撞兜底错误里的对象名（如「溯源码」「礼盒码」）
     */
    public static function dated(string $prefix, callable $exists, int $length = 8, string $label = '码'): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = $prefix.now()->format('Ymd').'-'.strtoupper(Str::random($length));
            if (! $exists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException($label.'生成碰撞，请重试');
    }
}
