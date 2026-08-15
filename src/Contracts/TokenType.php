<?php

namespace Fnp\ElStart\Contracts;

use BackedEnum;

/**
 * Marks the application enum that describes the available token types.
 *
 * The enum is owned by the application and should be integer backed, so its
 * cases end up in the `type_eid` column. Register it with
 * `AppToken::useTokenTypes(ETokenType::class)` to have the column cast back.
 */
interface TokenType extends BackedEnum {}
