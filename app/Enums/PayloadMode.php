<?php

namespace App\Enums;

enum PayloadMode: string
{
    case Query = 'query';
    case Json = 'json';
    case Form = 'form';
}
