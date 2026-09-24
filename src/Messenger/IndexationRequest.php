<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Messenger;

final readonly class IndexationRequest implements IndexationRequestInterface
{
    public function __construct(
        private string $className,
        private string $id,
        private string $operation = IndexationRequestHandler::OP_INDEX,
        private ?string $targetIndex = null,
    ) {
        if (!\in_array($operation, IndexationRequestHandler::OPERATIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Not supported operation "%s" given.', $operation));
        }
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTargetIndex(): ?string
    {
        return $this->targetIndex;
    }
}
