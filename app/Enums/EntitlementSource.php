<?php

namespace App\Enums;

enum EntitlementSource: string
{
    case Admin = 'admin';
    case Free = 'free';
    case Premium = 'premium';
    case Gift = 'gift';
    case Sponsor = 'sponsor';
}
