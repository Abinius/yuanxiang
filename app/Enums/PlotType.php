<?php

namespace App\Enums;

enum PlotType: string
{
    case Plot = 'plot';   // 分地档（0.1 亩）
    case Group = 'group'; // 拼团田（容器，其下挂株）
    case Plant = 'plant'; // 单株
}
