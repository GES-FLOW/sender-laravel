<?php

namespace Gesflow\Sender\Enums;

enum Channel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
}
