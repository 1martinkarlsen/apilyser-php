<?php declare(strict_types=1);

namespace Apilyser\Parser;

use PhpParser\ParserFactory;

class NodeParser
{
    /** @var \PhpParser\Parser */
    private $parser;

    public function __construct() {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return \PhpParser\Node\Stmt[]|null
     */
    public function parse(string $code): array|null {
        return $this->parser->parse($code);
    }
}