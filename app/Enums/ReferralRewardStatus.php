<?php

namespace App\Enums;

enum ReferralRewardStatus: string
{
    case Pending = 'pending';
    case WaitingConfirmation = 'waiting_confirmation';
    case Rewarded = 'rewarded';
}
