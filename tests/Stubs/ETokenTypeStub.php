<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Contracts\TokenType;

enum ETokenTypeStub: int implements TokenType
{
    case Api = 1;
    case Invitation = 2;
    case Reset = 3;
}
