<?php

namespace App\Enums;

enum FiscalDocumentDirection: string
{
    case Received = 'received';
    case Issued = 'issued';
}