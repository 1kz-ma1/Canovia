<?php

namespace App\Enums;

enum ProductKey: string
{
    case PremiumCore = 'premium_core';
    case StudyPack = 'study_pack';
    case CareerPack = 'career_pack';
    case DeveloperPack = 'developer_pack';
    case CreatorPack = 'creator_pack';
    case AllAccess = 'all_access';
    case AiCapacityBoost = 'ai_capacity_boost';
}
