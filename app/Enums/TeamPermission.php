<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    // Permisos de Webhooks
    case ManageWebhooks = 'webhook:manage';
    case CreateWebhook = 'webhooks:create';
    case UpdateWebhook = 'webhooks:update';
    case DeleteWebhook = 'webhooks:delete';

    // Permisos de Extracciones
    case ViewExtractions = 'extraction:view';
    case CreateExtraction = 'extraction:create';
    case ManageExtractions = 'extraction:manage';
    case UpdateExtraction = 'extractions:update';
    case DeleteExtraction = 'extractions:delete';

    // --- Fiscal Extraction Engine ---
    case ViewFiscalDocuments = 'document:view';
    case ManageSriCredentials = 'sri_credential:manage';
}
