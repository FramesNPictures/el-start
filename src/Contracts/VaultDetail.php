<?php

namespace Fnp\ElStart\Contracts;

use BackedEnum;

/**
 * Marks the application enum that names the details kept in the vault.
 *
 * The enum is owned by the application and should be integer backed, so its
 * cases end up in the `detail_eid` column. The column is read back as a plain
 * integer — resolve it with `EVaultDetail::from($entry->detail_eid)` where the
 * case itself is needed. Case values are part of the key derivation, so
 * renumbering one makes the entries stored under it unreadable.
 */
interface VaultDetail extends BackedEnum {}
