<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case ExtractionStarted = 'extraction.started';
    case ExtractionCompleted = 'extraction.completed';
    case ExtractionFailed = 'extraction.failed';
    case DocumentReceived = 'document.received';
    case DocumentProcessed = 'document.processed';
    case DocumentFailed = 'document.failed';
}