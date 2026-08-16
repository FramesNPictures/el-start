<?php

namespace Fnp\ElStart\Enums;

use Fnp\ElStart\Contracts\VaultDetail;

/**
 * The details this module keeps in the vault of a model.
 *
 * Cases live at 100000 and above so the details of an application, numbered
 * from one, never collide with them in `detail_eid`. Numbers are permanent and
 * new cases are appended: renumbering one makes every entry stored under it
 * unreadable, whatever order the cases end up reading in.
 */
enum ESystemVaultDetail: int implements VaultDetail
{
    case Email = 100000;
    case EmailHistory = 100002;
    case Name = 100001;
}
