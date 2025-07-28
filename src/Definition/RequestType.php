<?php

namespace Apilyser\Definition;

enum RequestType 
{
    case Path;
    case Query;
    case Body;
    case Unknown;
}