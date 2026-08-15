# Vault

The vault stores data of any model encrypted for the key pairs that may read it. Every model that unlocks the vault owns
a key pair; its secret half is protected by the password the user types, so the plaintext exists only inside a request of
someone who has unlocked it.

Entries can be sealed to more than one reader: the model that wrote it, a *system user* for background work and
recovery, and anyone it is shared with afterwards.

## Why

Laravel's `encrypted` cast protects data with `APP_KEY`. Anyone who can read the application code can read the data. The
vault moves the secret out of the deployment and into the user's password.

Public key cryptography is what makes that practical. Sealing an entry needs only a *public* key, so a process can store
data for a reader it cannot impersonate, and a password change never touches an entry — only the small blob holding that
user's secret key.

## How it works

1. **Key pairs.** The first `vault()->unlock($user, $password)` mints an X25519 key pair for the user. The public half
   is stored in the clear in `app_vault_keys`; the secret half is encrypted with a key derived from the password with
   Argon2id, salted with `user.id` and `user.email` and peppered with the application key.
2. **Session.** That derived key is kept in the session, wrapped with the application key, so later requests can open
   the secret key again. A dump of `app_sessions` alone yields nothing, and neither does the entries table.
3. **Entries.** Each entry gets a random key of its own. The value is encrypted with it using XChaCha20-Poly1305, and
   the model, its id and the detail are bound to the ciphertext as associated data — an entry copied to another row or
   another detail fails to decrypt instead of quietly opening.
4. **Grants.** The entry key is sealed once per reader into `app_vault_grants`: to the writer, to the system user when
   a public key is registered, and to anyone the entry is shared with. Any one of them opens the entry.
5. **Lock.** `vault()->lock()` drops the keys, and the module locks the vault on the `Logout` event.

Writes always run as a logged-in user. Sealing needs no secret, so that is a rule of the module rather than a limit of
the cryptography: it keeps every entry attributable to someone who was actually there.

```
app_vault                      app_vault_keys              app_vault_grants
  vaultable_type, _id            keyable_type, _id           vault_id
  detail_eid                     public_key                  keyable_type, _id
  value  (entry key)             secret_key (password)       sealed_key (entry key)
```

## Requirements

The `ext-sodium` extension (bundled with PHP) and a set `APP_KEY`.

## Setup

### 1. Define the details

Every entry is stored under a *detail* — an integer backed enum case owned by the application. Create it in `app/Enums`
and implement the contract:

```php
namespace App\Enums;

use Fnp\ElStart\Contracts\VaultDetail;

enum EVaultDetail: int implements VaultDetail
{
    case NationalId = 1;
    case BankAccount = 2;
    case RecoveryCodes = 3;
}
```

### 2. Unlock at login

The password is only in hand while the user is logging in, so unlock there:

```php
namespace App\Http\Actions\Auth;

class LoginAction
{
    public function handle(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        vault()->unlock(Auth::user(), $request->input('password'));

        return redirect()->intended();
    }
}
```

The first unlock mints the key pair; every one after opens it, so a wrong password is refused right there with a
`VaultException` rather than surfacing later as an unreadable entry.

`session()->regenerate()` keeps the key — it only changes the session id. `session()->invalidate()` drops it, as does
logging out.

Anything that logs a user in without a password — remember-me cookies, magic links, impersonation — leaves the vault
locked. Handle that case rather than assuming an authenticated user can read their entries.

### 3. Add the trait

```php
namespace App\Models;

use Fnp\ElStart\Traits\HasVault;

class User extends Authenticatable
{
    use HasVault;
}
```

### 4. Optional: pepper and cost

Both belong in a service provider, and both change the derived key, so set them once and keep them stable — changing
either locks every key pair out of its password:

```php
use Fnp\ElStart\Services\VaultService;

// Keeps the vault closed even if APP_KEY leaks. Back this secret up.
VaultService::usePepper(config('services.vault.pepper'));

// Defaults to the interactive limits — 64 MB and roughly 85 ms per unlock on a current laptop.
// The sensitive ones below ask for 1 GB per unlock; measure before putting them on a shared server.
VaultService::useDerivationCost(
    SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
    SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
);
```

## Usage

### Through the model

```php
$user->putVault(EVaultDetail::BankAccount, 'PL61 1090 1014 0000 0712 1981 2874');

$user->vaultValue(EVaultDetail::BankAccount);            // the decrypted value
$user->vaultValue(EVaultDetail::NationalId, 'unknown');  // default when nothing is stored

$user->hasVault(EVaultDetail::BankAccount);              // true
$user->vaultDetails();                                   // collection of stored detail values
$user->removeVault(EVaultDetail::BankAccount);           // drops one detail and its grants
$user->removeVault();                                    // drops all of them

$user->vault();        // MorphMany of the entries attached to this model
$user->vaultGrants();  // MorphMany of the entries this model may open
$user->vaultKey;       // the key pair of this model
```

Writing the same detail twice replaces the value and keeps the entry key, so everyone it was shared with keeps reading
it. Overwriting requires a grant of your own — a model you cannot read, you cannot silently replace.

### Through the service

The same operations for any model, whether it uses the trait or not:

```php
vault()->put($invoice, EVaultDetail::BankAccount, $iban);
vault()->get($invoice, EVaultDetail::BankAccount, $default = null);
vault()->has($invoice, EVaultDetail::BankAccount);
vault()->remove($invoice, EVaultDetail::BankAccount);  // omit the detail to drop all of them
```

The data does not have to belong to the user who locks it. A user can encrypt a detail of an invoice, a document or any
other model; the entry is sealed to whoever wrote it.

### Sharing

```php
$user->shareVault(EVaultDetail::BankAccount, $accountant);
vault()->share($invoice, EVaultDetail::BankAccount, $accountant);
```

The reader needs a key pair, which it only has once it has unlocked the vault itself. Sharing with a model that never
did throws.

```php
$user->revokeVault(EVaultDetail::BankAccount, $accountant);
```

Revoking deletes the grant **and rotates the entry key**, re-sealing it to everyone left, so a copy of the old sealed
key is worth nothing from that moment on. What the reader already saw is of course beyond recall.

### Payloads outside the table

`encrypt()` and `decrypt()` derive a key straight from the password of the session, for a queue payload, a cached blob or
a column of your own. Pass the same context to both, or leave it out on both:

```php
$payload = vault()->encrypt($answers, EVaultDetail::RecoveryCodes, "quiz|{$quiz->id}");
$answers = vault()->decrypt($payload, EVaultDetail::RecoveryCodes, "quiz|{$quiz->id}");
```

These payloads are sealed to no key pair: no grants, no system user, and no recovery. They also break on a `rekey()`,
because they hang off the password rather than off the key pair. Keep them short lived.

### State of the vault

```php
vault()->isUnlocked();   // a key pair of either kind is loaded
vault()->isLocked();     // none is
vault()->isSystem();     // this process is unlocked as the system user
vault()->userId();       // id of the model the session was unlocked for, null for the system user
vault()->lock();         // drop everything
```

### What works while locked

Details are stored in the clear, so entries stay addressable without a key:

| Locked | Needs a key pair |
| --- | --- |
| `hasVault()` / `has()` | `putVault()` / `put()` |
| `vaultDetails()` | `vaultValue()` / `get()` |
| `removeVault()` / `remove()` | `shareVault()` / `revokeVault()` |
| `vault()`, `vaultGrants()` relations | `rekey()`, `recover()` |

## Changing the password or the email address

Both take part in the derivation that protects the secret key, so both need a `rekey()` — which rewrites exactly one
row, the key pair of that user. **No entry is touched, and there is no list of models to keep.**

```php
namespace App\Http\Actions\Profile;

class UpdateCredentialsAction
{
    public function handle(UpdateCredentialsRequest $request): RedirectResponse
    {
        $user = $request->user();

        // The vault is still unlocked with the old password here.
        $user->email = $request->input('email');

        vault()->rekey($user, $request->input('password'));

        $user->password = Hash::make($request->input('password'));
        $user->save();

        return back();
    }
}
```

The session is left unlocked, and only the new password opens the key pair from then on.

Put a `rekey()` next to every place that changes an email address or a password: an admin editing a user, a profile
form, a bulk import. The one place that cannot use it is a password reset — there is no old password to unlock with. That
is what the system user is for.

## The system user

The system user is a key pair, not a record. Entries are sealed to its public key as they are written, and the secret key
opens them without anyone's password.

### 1. Generate a key pair

```php
php artisan tinker
>>> \Fnp\ElStart\Services\VaultService::generateSystemKeys();
=> ["public" => "…", "secret" => "…"]
```

Put both halves in the environment, and back the secret one up outside the server.

### 2. Register them in a module

Authentication of the system user belongs at the service provider level, not at a call site. Add the
`ModuleVaultSystem` feature to a module and hand out the halves per process:

```php
namespace App\Providers;

use Fnp\ElModule\ElModule;
use Fnp\ElStart\Features\ModuleVaultSystem;

class AppModule extends ElModule
{
    use ModuleVaultSystem;

    public function defineVaultSystem(): array
    {
        $console = $this->app->runningInConsole();

        return [
            // Every process seals new entries.
            'public' => env('VAULT_SYSTEM_PUBLIC_KEY'),

            // Only the console reads them.
            'secret' => $console ? env('VAULT_SYSTEM_SECRET_KEY') : null,

            // Unlock as soon as the module boots.
            'unlock' => $console,
        ];
    }
}
```

Keep `VAULT_SYSTEM_SECRET_KEY` out of the web environment altogether if the deployment allows it. A web process that
never holds the secret key cannot be turned into a reader by a bug or an injected call.

### 3. Read as the system user

With `unlock` left at `false`, open the vault where the work actually happens:

```php
namespace App\Console\Commands;

class ExportBankDetails extends Command
{
    public function handle(): int
    {
        vault()->unlockAsSystem();

        User::query()->each(function (User $user): void {
            $this->line($user->vaultValue(EVaultDetail::BankAccount, '—'));
        });

        vault()->lock();

        return self::SUCCESS;
    }
}
```

`unlockAsSystem()` writes nothing to the session: the key pair lives in memory and dies with the process, so a request
that unlocks as the system user cannot hand system access to the next one.

The name follows `unlock()` / `lock()` / `isUnlocked()` rather than the vocabulary of authentication, because nothing is
logged in — no guard, no session, no user record.

### The system user never writes

`put()` and `rekey()` throw while the vault is unlocked as the system user. Both would produce an entry or a key pair
the user cannot open, which is a trap discovered far too late. Unlock as the user to write.

### Recovering a forgotten password

```php
vault()->unlockAsSystem();

$recovered = vault()->recover($user, $newPassword);
```

`recover()` mints a fresh key pair for the user, protected by the new password, and re-seals every entry the system can
open to it — found through that user's own grants, so there is no list of models to pass and nothing to forget.

It returns how many entries it handed over. Entries that were never sealed to the system user cannot be recovered and
are left untouched; compare the result with `$user->vaultGrants()->count()` to see whether any were left behind.

## Events

Seven events in `Fnp\ElStart\Events` follow the vault, and all of them implement `Auditable`, so the audit listener of
this module writes them to `app_audit` with no wiring of your own:

| Event | Dispatched by | Carries |
| --- | --- | --- |
| `VaultOpened` | `unlock()`, `unlockAsSystem()` | identity type and id, `minted` when the key pair was created |
| `VaultClosed` | `lock()`, and the `Logout` listener | identity type and id |
| `VaultRekeyed` | `rekey()`, `recover()` | the user, `recovered`, number of re-sealed entries |
| `VaultUpdated` | `put()` | the entry, `created` when it was the first write |
| `VaultRemoved` | `remove()` | the model, the detail (null for all), number of entries |
| `VaultShared` | `share()` | the entry and the reader let in |
| `VaultRevoked` | `revoke()` | the entry and the reader shut out |

**No event carries a value, a key or a password**, and neither does the audit payload — an audit trail of the vault says
that a detail changed and who was there, never what it changed to. There is a test holding that line; keep it if you add
events of your own.

Each audit row also records who was there in `user_id`: the id of the logged in user, or null when nobody was — a console
command, a queued job, a schedule. That is independent of which key pair did the work, so an admin who opens the vault as
the system user is filed under their own id with `identity: system` in the payload, while the same command run from cron
is filed with no user at all.

Only real changes are announced: locking a vault that was never open, removing nothing, sharing with a reader that
already holds a grant, and revoking one that holds none all stay quiet.

Listen to them like any other event:

```php
Event::listen(function (VaultOpened $event): void {
    if ($event->isSystem()) {
        Log::channel('security')->info('The system user opened the vault.');
    }
});
```

## Errors

`Fnp\ElStart\Exceptions\VaultException` is thrown when:

- the vault is locked and an entry is read or written,
- the password does not open the stored key pair,
- nothing grants the current key pair access to an entry,
- an entry does not decrypt — a rotated pepper, a tampered row, a moved entry,
- `put()` or `rekey()` is called while unlocked as the system user, or `recover()` while not,
- a reader has no key pair to share with, or the entry to share is not there,
- `unlockAsSystem()` is called in a process that holds no secret key, or a registered key is the wrong size,
- the user record has no id or no email address to derive a key from.

Reading a detail the model simply does not hold is not an error; it returns the default.

```php
try {
    $iban = $user->vaultValue(EVaultDetail::BankAccount);
} catch (VaultException) {
    return redirect()->route('vault.unlock');
}
```

## The tables

All three ship with this module. Secret columns are hidden from array and JSON output.

**`app_vault`** — one row per model and detail. `value` is the ciphertext, `detail_eid` the enum case value as a plain
integer. Unique on `vaultable_type`, `vaultable_id`, `detail_eid`.

**`app_vault_keys`** — one row per model that ever unlocked the vault. `public_key` in the clear, `secret_key` encrypted
with the password derived key. It holds no credential, so a password or an email change rewrites this row alone.

**`app_vault_grants`** — one row per reader per entry. `sealed_key` is the entry key sealed to that reader's public key;
the system user appears as `keyable_type` = `system`, `keyable_id` = `0`. Deleting an entry deletes its grants.

The models carry the scopes for querying directly — `AppVault::for($model)->ofDetail($detail)`,
`AppVaultGrant::for($reader)`, `AppVaultGrant::forSystem()` — which is useful for reporting on *who can read what*
without being able to read any of it.

## Things to know

- **Readers are explicit.** An entry is readable by its writer, by the system user, and by whoever it was shared with —
  no one else, whatever their role in the application.
- **The system secret key is the whole vault.** Whoever holds it reads every sealed entry. Keep it out of the web
  environment, out of the repository, and out of logs and backups that travel with the database.
- **Rotating the system key pair** leaves existing grants sealed to the old public key. Re-seal them while both pairs
  are still around: unlock as the old system user and share the entries to the new one, or re-write them as their users.
- **No recovery without the system user.** With no system key pair registered, a lost password is lost data by design.
  Say so in the interface before a user stores anything.
- **A stable pepper.** Rotating `APP_KEY` — or the custom pepper — locks every key pair out of its password. With a
  system key pair the entries can still be recovered; without one they are gone.
- **JSON round trip.** Values are serialized with `json_encode`, so store scalars and arrays. Objects come back as
  arrays.
- **Details are visible.** `detail_eid` is stored in the clear, and so is every grant. That a user has a `BankAccount`
  entry, and who may read it, is not a secret; only the value is.

## Testing

Key derivation is deliberately slow. Drop the cost in tests that are not measuring it, and restore it afterwards:

```php
beforeEach(fn () => VaultService::useDerivationCost(1, 8192));

afterEach(fn () => VaultService::useDerivationCost(
    SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
));
```

The cost, the pepper and the system keys are static, so reset whichever a test changes. `generateSystemKeys()` gives a
throwaway pair per test, and a model needs one `unlock()` before it can be shared with.
