<?php

namespace App\Enums;

enum RoadmapFeatureStatus: string
{
    case Voting = 'voting';
    case Considering = 'considering';
    case Planned = 'planned';
    case InDevelopment = 'in_development';
    case Ready = 'ready';
    case RollingOut = 'rolling_out';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Voting => 'Support受付中',
            self::Considering => '正式検討中',
            self::Planned => 'Roadmap入り',
            self::InDevelopment => '開発中',
            self::Ready => '公開準備中',
            self::RollingOut => '段階公開中',
            self::Released => 'リリース済み',
        };
    }
}
