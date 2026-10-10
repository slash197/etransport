<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation;

/**
 * @internal
 */
final class Location
{
    /**
     * A readable path to a node: names without namespace, and a position
     * only where an element has siblings of the same name.
     */
    public static function of(\DOMNode $node): string
    {
        if ($node instanceof \DOMAttr) {
            return ($node->ownerElement === null ? '' : self::of($node->ownerElement)) . '/@' . $node->nodeName;
        }
        if (!$node instanceof \DOMElement) {
            return $node instanceof \DOMDocument ? '/' : (string) $node->getNodePath();
        }

        $position = 1;
        $total = 0;
        foreach ($node->parentNode->childNodes ?? [] as $sibling) {
            if ($sibling instanceof \DOMElement
                && $sibling->localName === $node->localName
                && $sibling->namespaceURI === $node->namespaceURI
            ) {
                $total++;
                if ($sibling === $node) {
                    $position = $total;
                }
            }
        }

        $parent = $node->parentNode instanceof \DOMElement ? self::of($node->parentNode) : '';

        return $parent . '/' . $node->localName . ($total > 1 ? "[{$position}]" : '');
    }
}
