<?php

namespace App\Enums;

enum EvidenceSource: string
{
    case Native = 'native';
    case GitHub = 'github';
    case File = 'file';
    case Image = 'image';
    case Calendar = 'calendar';
    case External = 'external';
}
