<?php

namespace App\Enums;

enum ResponseFormat: string
{
    case Json = 'json';
    case Text = 'text';
    case Xml = 'xml';
}
