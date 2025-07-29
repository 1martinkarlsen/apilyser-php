<?php declare(strict_types=1);

namespace Apilyser\Definition;

enum RequestType 
{
    case Path;
    case Query;
    case Body;
    case Unknown;
}