<?php

namespace App\Enums;

enum ExtractionTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Retry = 'retry';
    case ApiTriggered = 'api_triggered';
}