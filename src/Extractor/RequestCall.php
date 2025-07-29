<?php declare(strict_types=1);

namespace Apilyser\Extractor;

use Apilyser\Definition\RequestType;
use PhpParser\Node;

class RequestCall
{
    public string $variableName; // E.g. 'request' from $request->input()
    public string $parameterName; // E.g. 'user_id' from ->input('user_id')
    public RequestType $source; // E.g. 'query', 'body', 'header'
    public string $deducedType; // E.g. 'string', 'int'
    public ?Node $node = null; // Reference til AST-noden for yderligere analyse
}