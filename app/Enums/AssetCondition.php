<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Good = 'Baik';
    case LightDamage = 'Rusak Ringan';
    case HeavyDamage = 'Rusak Berat';
}
