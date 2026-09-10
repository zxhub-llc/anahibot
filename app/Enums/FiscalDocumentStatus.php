<?php

namespace App\Enums;

enum FiscalDocumentStatus: string
{
    case Downloaded = 'downloaded';
    case Processed = 'processed';
    case Failed = 'failed';
}

