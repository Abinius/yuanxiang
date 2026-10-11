<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 地块存在付款记录或在约认养，禁止删除（收过钱的田块是永久红线：
 * 编号、认养记录与溯源链不能断）。消息即给用户看的拒绝原因。
 *
 * 默认 409（后台口径）；家人端按权限边界口径转 403。
 */
class PlotNotDeletable extends HttpException
{
    public function __construct(string $message, int $status = 409)
    {
        parent::__construct($status, $message);
    }
}
