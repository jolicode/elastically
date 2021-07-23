<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'analyzer' => [
        'beer_name' => [
            'tokenizer' => 'classic',
            'filter' => ['elision'],
        ],
    ],
];
