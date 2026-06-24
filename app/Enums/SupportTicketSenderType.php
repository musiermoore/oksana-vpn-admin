<?php

namespace App\Enums;

enum SupportTicketSenderType: string
{
    case User = 'user';
    case Admin = 'admin';
}
