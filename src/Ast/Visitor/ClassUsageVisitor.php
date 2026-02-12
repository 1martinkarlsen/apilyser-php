<?php declare(strict_types=1);

namespace Apilyser\Ast\Visitor;

use Apilyser\Ast\ClassUsage;
use Apilyser\Ast\Node\NameHelper;
use Apilyser\Resolver\NamespaceResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class ClassUsageVisitor extends NodeVisitorAbstract
{
    /**
     * @var ClassUsage[] $usages
     */
    public $usages = [];

    public function __construct(
        private NamespaceResolver $namespaceResolver,
        private string $className,
        private array $imports
    ) {}

    function enterNode(Node $node)
    {
        // Static call ClassName::method()
        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $className = $this->getTargetClassName($node->class);
            if ($className) {
                $this->usages[] = new ClassUsage(
                    className: $className,
                    usageType: StaticCall::class,
                    node: $node,
                    parent: $this->lookForParent($node)
                );
            }
        }

        // Class const ClassName::CONSTANT
        if ($node instanceof ClassConstFetch && $node->class instanceof Name) {
            $className = $this->getTargetClassName($node->class);
            if ($className) {
                $this->usages[] = new ClassUsage(
                    className: $className,
                    usageType: ClassConstFetch::class,
                    node: $node,
                    parent: $this->lookForParent($node)
                );
            }
        }

        // New class new ClassName()
        if ($node instanceof New_ && $node->class instanceof Name) {
            $className = $this->getTargetClassName($node->class);
            if ($className) {
                $this->usages[] = new ClassUsage(
                    className: $className,
                    usageType: New_::class,
                    node: $node,
                    parent: $this->lookForParent($node)
                );
            }
        }

        return null;
    }

    /**
     * @return ClassUsage[]
     */
    public function getUsages(): array
    {
        return $this->usages;
    }

    /**
     * @return string|null full namespaced class name
     */
    private function getTargetClassName(Name $name): ?string
    {
        $className = NameHelper::getName($name);

        $fullClassName = $this->namespaceResolver->findFullNamespaceForClass($className, $this->imports);
        if ($this->className === $fullClassName) {
            return $this->className;
        }

        return null;
    }

    private function lookForParent(Node $node): ?Node
    {
        $parent = $node->getAttribute('parent');

        if ($parent) {
            return $parent;
        }

        return null;
    }
}
