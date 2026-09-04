<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
