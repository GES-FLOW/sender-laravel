<?php

namespace Gesflow\Sender\Enums;

/**
 * Type d'un média envoyé sur WhatsApp.
 */
enum MediaType: string
{
    case Image = 'image';
    case File = 'file';
    case Video = 'video';
    case Voice = 'voice';
}
