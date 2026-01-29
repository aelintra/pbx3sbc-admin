# Quick Start – Agent Context

**Last Updated:** January 2026  
**Purpose:** Quick reference for next agent – current state and next steps. Read this first.

---

## Current State

- **Branch:** main (f2b-manage merged). Fail2ban admin panel features are on main.
- **Stack:** Laravel 12 + Filament 3.x. Shared MySQL database with OpenSIPS (pbx3sbc repo).
- **Fail2ban:** Phase 2 complete – Fail2banStatus page, Fail2banWhitelistResource (CRUD), Fail2banService, WhitelistSyncService. Admin panel calls pbx3sbc sync script (sudo) for whitelist; requires sudoers on SBC server.

---

## Most Recent Work

- **Merge:** f2b-manage → main (Fail2ban admin panel).
- **Cleanup:** Removed temporary `.!68091!.env` file.
- **Ready:** Whitelist sync and ban/unban from admin panel (verify on deployed server).

---

## Next Steps

1. **Verify Fail2ban from admin panel** – Whitelist sync, ban/unban (sudoers must be set on SBC; see pbx3sbc `scripts/setup-admin-panel-sudoers.sh`).
2. **Update CURRENT-STATE.md** – Mark Phase 2 (Fail2ban Management) as complete.
3. **Phase 3** – Advanced security features (per REQUIREMENTS/ADMIN-PANEL-SECURITY-REQUIREMENTS.md).

---

## Key Files

| What | Where |
|------|--------|
| Fail2ban status page | `app/Filament/Pages/Fail2banStatus.php` |
| Fail2ban whitelist resource | `app/Filament/Resources/Fail2banWhitelistResource.php` |
| Fail2ban service | `app/Services/Fail2banService.php` |
| Whitelist sync service | `app/Services/WhitelistSyncService.php` |
| Installer | `install.sh` |
| Project context | `workingdocs/ARCHITECTURE/PROJECT-CONTEXT.md` |
| Current state (detailed) | `workingdocs/QUICK-REFERENCES/CURRENT-STATE.md` |

---

## Repos

- **pbx3sbc-admin** (this repo) – Laravel admin panel. Connects to same MySQL as OpenSIPS.
- **pbx3sbc** – OpenSIPS SBC. Contains Fail2ban config, sync script, sudoers setup script. Admin panel must run on same server (or have SSH/remote execution) for Fail2ban management.

---

## Documentation Layout

- **QUICK-START.md** (this file) – Start here.
- **QUICK-REFERENCES/CURRENT-STATE.md** – Detailed implementation status.
- **ARCHITECTURE/** – PROJECT-CONTEXT, ARCHITECTURE, UX-DESIGN-DECISIONS.
- **IMPLEMENTATION/** – IMPLEMENTATION-GUIDE, CODE-QUALITY, AUTHENTICATION, LARAVEL-FILAMENT-PROJECT-RULES.
- **DEPLOYMENT/** – DEPLOYMENT, DEVELOPMENT-STACK-RECOMMENDATIONS, HERD-REMOTE-DATABASE-SETUP.
- **REQUIREMENTS/** – Security requirements, planning, assessments.
- **BEST-PRACTICES/** – BEST-PRACTICES-REVIEW.
- **archive/** – Historical/superseded docs.
