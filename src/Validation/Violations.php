<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation;

/**
 * Everything wrong with a document. Empty when the document is valid.
 *
 * @implements \IteratorAggregate<int, Violation>
 */
final class Violations implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(private readonly array $violations = [])
    {
    }

    public function isEmpty(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return list<Violation>
     */
    public function all(): array
    {
        return $this->violations;
    }

    /**
     * The ids of the business rules that were broken, each listed once.
     *
     * @return list<string>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->violations as $violation) {
            if ($violation->rule !== null) {
                $rules[$violation->rule] = true;
            }
        }

        return array_map(strval(...), array_keys($rules));
    }

    public function count(): int
    {
        return count($this->violations);
    }

    /**
     * @return \ArrayIterator<int, Violation>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->violations);
    }
}
