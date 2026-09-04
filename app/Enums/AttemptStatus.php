<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
