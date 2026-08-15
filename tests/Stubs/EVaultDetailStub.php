<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Contracts\VaultDetail;

enum EVaultDetailStub: int implements VaultDetail
{
    case Note = 1;
    case Pin = 2;
    case Recovery = 3;
}
