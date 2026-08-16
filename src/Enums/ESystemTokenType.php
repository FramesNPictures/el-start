<?php

namespace Fnp\ElStart\Enums;

use Fnp\ElStart\Contracts\TokenType;

/**
 * The tokens this module keeps for a model.
 *
 * Cases live at 100000 and above so the token types of an application,
 * numbered from one, never collide with them in `type_eid`.
 */
enum ESystemTokenType: int implements TokenType
{
    case PasswordReset = 100000;
    case Remember = 100001;
}
