# SBC Home system posture + Fleet SPA scrape

**Status:** Thin system strip shipped on Filament Home (branch `sbc-home-ops-pulse`).  
**Related:** Cursor canvas `sbc-home-dashboard-concepts`; `TODO.md` (geo heat + Grafana → fleet).

## Edge Home (done / constraints)

**Widget:** `SystemPostureWidget` via `HomeDashboardMetrics::systemPosture()`.

| Signal | Source | Notes |
|--------|--------|--------|
| Load 1/5/15 | `/proc/loadavg` | Compared to CPU count for warn/hot colour |
| Memory used % | `/proc/meminfo` MemTotal − MemAvailable | No shell |
| Disk used % | `disk_total_space` / `disk_free_space` on `/` | Space only — **not** I/O rate |

**Efficiency:** 45s cache, 60s Livewire poll. No `exec`, no 1s CPU sample sleep, no `iostat`.  
**Out of scope on Home:** disk I/O rates, network graphs, per-process top, long time-series (FreePBX wallpaper).

### Follow-up — usage meters (SPA kinship)

**Done (2026-07-30):** Filament `SystemPostureWidget` uses thin green→amber→red **usage meters** under Load / Memory / Disk (same lerp as SPA `HomeHostStrip`). Home page title is **Home** only (FQDN remains on the INSTANCE chip). Friendly SBC sitename still deferred (no DB field yet).

## Fleet SPA — what to build later (not started)

Goal: multi-edge host health in **Fleet mode** without hammering each SBC on every browser poll, and without putting geo/I/O heatmaps on the Magrathea MariaDB path.

### Preferred shape

1. **Edge emits a small summary** (cron or artisan on SBC admin, e.g. every 1–5 min):
   - Reuse the same fields as `systemPosture()` (+ optional later: unique door-knock IP counts, not full geo).
   - Write **local JSON** under a known path **or** `POST` to Gatekeeper (fleet.token) — prefer Gatekeeper so edge never needs S3 write IAM for this (Rule 9 / node writer posture).
2. **Control stores HoR in S3** (catalog-adjacent), e.g. `fleet/edge-health/{edge_id}.json` with `updated_at`, load, mem%, disk%, optional security rollups.
3. **Fleet SPA** reads Gatekeeper `GET /api/v1/…` overlay (same pattern as instance `health` / egress badge) — **not** browser→SBC direct for every metric tick.
4. **History / heat / I/O graphs** (if ever): scrapes land in S3; Fleet SPA charts from Gatekeeper, **or** operator points **unmodified Grafana** at exported metrics. Do not fork Grafana; do not geolocate on Home poll.

### Explicit non-goals for v1 Fleet scrape

- Browser calling ip-api or OpenSIPS MI from SPA.
- Storing full door-knock rows in S3.
- On-SBC Leaflet heatmaps.

### Implementer checklist (when scheduled)

- [ ] Artisan `pbx3sbc:emit-home-summary` (or similar) + cron example under `deploy/cron.d/`
- [ ] Gatekeeper ingest + S3 object schema + retention (align with edge health probe cadence)
- [ ] SPA Fleet Instances (or Edge card) show load/mem/disk badges from overlay
- [ ] Document ops: summary failure must not affect SIP; missing summary → “stale / unknown” in UI
- [ ] Door-knock **country** rollup (if product asks): enrich unique IPs **off** the request path (control job + geo cache), store rollup only

### Why not only on SBC

Shared MariaDB with OpenSIPS + admin polls on fat `door_knock_attempts` is the load we care about. Host `/proc` reads are cheap; **history and multi-box views** belong on control/Fleet (and optional Grafana), not Filament Home.
