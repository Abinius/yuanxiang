<?php

namespace App\Enums;

enum PlotStatus: string
{
    case Available = 'available';
    case Adopted = 'adopted';
    case SoldOut = 'sold_out';
    case Offline = 'offline';
}
