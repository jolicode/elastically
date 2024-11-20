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

final class MultipleIndexationRequest implements IndexationRequestInterface
{
    private array $operations = [];

    public function __construct(array $operations)
    {
        $this->operations = $operations;
    }

    /** @return array<IndexationRequest> */
    public function getOperations(): array
    {
        return $this->operations;
    }
}
