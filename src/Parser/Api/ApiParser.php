<?php

namespace Apilyser\Parser\Api;

use Apilyser\Analyser\ClassMethodContext;
use Apilyser\Definition\NewClassResponseParameter;
use Apilyser\Resolver\ResponseCall;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;

interface ApiParser {

    /**
     * @return string[] of class names
     */
    function getSupportedResponseClasses(): array;

    /**
     * @return string[] of class names
     */
    function getSupportedRequestClasses(): array;

    /**
     * @param $className full namespaced class name
     * @return bool if it exists
     */
    function supportRequestClass(string $className): bool;

    /**
     * @param $className full namespaced class name
     * @return bool if it exists
     */
    function supportResponseClass(string $className): bool;

    function getQuery(): string;

    function getBody(): string;

    function getNewClassParameters(): NewClassResponseParameter;

    /**
     * @param ClassMethodContext $context
     * @param Node $node
     * @param ?ResponseCall $modifierResponseCall
     * 
     * @return ?ResponseCall
     */
    function tryParseCallLikeAsResponse(
        ClassMethodContext $context,
        Node $node,
        ?ResponseCall $modifierResponseCall = null
    ): ?ResponseCall;
}