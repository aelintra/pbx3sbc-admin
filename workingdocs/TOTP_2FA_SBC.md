# TOTP 2FA — SBC admin (Filament)

**Status:** Implemented on branch `sbc-totp-2fa` (2026-08-07).  
**Parent:** [`pbx3/workingdocs/TOTP_2FA_REQUIREMENTS.md`](../../../pbx3/workingdocs/TOTP_2FA_REQUIREMENTS.md).  
**Stack:** Filament 3 + `jeffgreco13/filament-breezy` **^2.6** (Filament 3 line; do not bump to Breezy 3.x without Filament 4).

---

## Installer journey

1. Install SBC admin as today → first admin via `install.sh` / `php artisan make:filament-user` (password only).
2. Sign in at `/admin/login` with email + password. **No 2FA yet.**
3. Topbar **Profile** → My Profile → enable two-factor authentication.
4. Scan QR with any authenticator app (2FAS, Authy, Google Authenticator, …). Issuer defaults to **`Aelintra SBC`** (`PBX3_TOTP_ISSUER`).
5. Confirm with a live code; **save recovery codes** shown once.
6. Next login: password → challenge screen → TOTP (or a recovery code).

`install.sh` does **not** prompt for 2FA at create-admin time.

---

## Operator UI notes

- Custom SPA-kinship topbar hides Filament’s avatar menu. Enroll entry is the explicit **Profile** link (next to Logout).
- Fleet Bearer API (`/api/fleet/*`) is **unchanged** — not gated on Filament session TOTP.

---

## Lockout recovery

**Preferred:** use a recovery code from enroll.

**Sole admin locked out (no recovery codes):** clear Breezy 2FA session for that user, then re-enroll.

```bash
cd /opt/pbx3sbc-admin   # or your install path
sudo -u www-data php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'admin@example.com')->first();
$user->disableTwoFactorAuthentication();
```

Or SQL (SQLite/MySQL — adjust DB):

```sql
DELETE FROM breezy_sessions WHERE authenticatable_type LIKE '%User%' AND authenticatable_id = 1;
```

Then sign in with password only and re-enable 2FA from Profile.

**Second admin:** another Filament user can remain password-only (opt-in) or clear the locked user’s 2FA via tinker as above.

---

## Config

| Key | Default | Purpose |
|-----|---------|---------|
| `PBX3_TOTP_ISSUER` / `config('panel.totp_issuer')` | `Aelintra SBC` | otpauth issuer string |

---

## Upgrade

Package/composer upgrade must run migrations (includes `breezy_sessions`). Existing admins keep password-only until they enroll.

Operator install docs: **pbx3-docs** → Fleet → [Install SBC edge](https://aelintra.github.io/pbx3-docs/fleet/install-sbc/) § Admin TOTP 2FA.
