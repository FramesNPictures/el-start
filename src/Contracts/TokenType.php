<?php

namespace Fnp\ElStart\Contracts;

use BackedEnum;

/**
 * Marks the application enum that describes the available token types.
 *
 * The enum is owned by the application and should be integer backed, so its
 * cases end up in the `type_eid` column. The column is read back as a plain
 * integer — resolve it with `ETokenType::from($token->type_eid)` where the
 * case itself is needed.
 */
interface TokenType extends BackedEnum {}
