<?php

namespace App\Enums;

enum AiFeature: string
{
    case Summary = 'summary';
    case Ask = 'ask';
    case Explain = 'explain';
    case Digest = 'digest';
}
