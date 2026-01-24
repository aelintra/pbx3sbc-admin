# CDR Front-End Panels Specification

**Date:** 2026-01-18  
**Status:** Ready for Development  
**Related:** `pbx3sbc` repository - CDR implementation complete

## Overview

This document specifies the front-end panels and functionality required to manage and display CDR (Call Detail Record) data in the pbx3sbc-admin panel. The CDR system is fully implemented and verified in the `pbx3sbc` repository, providing billing-ready call detail records with complete SIP URI information.

## Database Schema Reference

### `acc` Table (Call Detail Records)

**Location:** MySQL database `opensips`  
**Connection:** Same database as dispatcher/domain tables

**Key Columns:**
- `id` - Primary key (auto-increment)
- `method` - SIP method (typically "INVITE")
- `callid` - Unique Call-ID header (used for correlation)
- `from_tag` - SIP From tag
- `to_tag` - SIP To tag
- `from_uri` - **Full From SIP URI** (e.g., `sip:1001@192.168.1.58`) - **Essential for billing**
- `to_uri` - **Full To SIP URI** (e.g., `sip:1000@192.168.1.109`) - **Essential for billing**
- `sip_code` - SIP response code (200 = success, 404 = not found, etc.)
- `sip_reason` - SIP response reason (e.g., "OK", "Not Found")
- `time` - Call end timestamp (DATETIME)
- `created` - Call start timestamp (DATETIME) - **Used for duration calculation**
- `duration` - Call duration in seconds (INTEGER)
- `ms_duration` - Call duration in milliseconds (INTEGER) - **More precise**
- `setuptime` - Call setup time in seconds (time from INVITE to answer)

**Important Notes:**
- Each call produces exactly ONE CDR record (CDR mode correlates INVITE and BYE)
- `from_uri` and `to_uri` contain complete SIP URIs (not just tags)
- Duration is calculated automatically by CDR mode
- Timestamps are in MySQL DATETIME format

### `dialog` Table (Active Call Tracking)

**Location:** MySQL database `opensips`  
**Purpose:** Real-time active call monitoring

**Key Columns:**
- `dlg_id` - Dialog ID (primary key)
- `callid` - Call-ID header (matches `acc.callid`)
- `from_uri` - From SIP URI
- `to_uri` - To SIP URI
- `state` - Dialog state (1=early, 2=confirmed, 4=terminated)
- `start_time` - Dialog start timestamp
- `created` - Dialog creation timestamp
- `modified` - Last modification timestamp

**Important Notes:**
- Populated in real-time during calls
- With `db_mode=2`, dialogs are cached and flushed every 10 seconds
- Useful for active call monitoring and Prometheus/Grafana integration

## Required Front-End Panels

### 1. CDR List/Table View

**Purpose:** Display all call detail records with filtering, sorting, and search capabilities.

**Features:**
- **Table Display:**
  - Columns: ID, Call-ID, From URI, To URI, Duration, Start Time, End Time, SIP Code, Status
  - Sortable columns (default: newest first)
  - Pagination (configurable: 25, 50, 100, 200 per page)
  - Export to CSV/Excel

- **Filtering:**
  - Date range (start date, end date)
  - From URI (partial match)
  - To URI (partial match)
  - Call-ID (exact or partial match)
  - SIP Code (dropdown: 200, 404, 408, 486, etc.)
  - Duration range (min/max seconds)
  - Failed calls only (sip_code != 200)
  - Successful calls only (sip_code = 200)

- **Search:**
  - Global search across Call-ID, From URI, To URI
  - Quick filters: Today, Yesterday, Last 7 days, Last 30 days, This month

- **Actions:**
  - View details (modal or detail page)
  - Export filtered results
  - Delete (with confirmation, role-based)

**UI Requirements:**
- Responsive table (mobile-friendly)
- Loading states
- Empty states
- Error handling

### 2. CDR Detail View

**Purpose:** Display complete information for a single CDR record.

**Features:**
- **Display All Fields:**
  - Call identification: Call-ID, From Tag, To Tag
  - SIP URIs: From URI, To URI (highlighted, clickable for filtering)
  - Timing: Created (start), Time (end), Duration (formatted: "X minutes Y seconds")
  - Call metrics: Setup time, Duration (seconds), Duration (milliseconds)
  - Status: SIP Code, SIP Reason, Call status badge

- **Related Information:**
  - Link to dialog record (if still active)
  - Related CDRs (same Call-ID - should be none, but show if duplicates exist)

- **Actions:**
  - Edit (if needed, role-based)
  - Delete (with confirmation, role-based)
  - Export single record
  - Filter by From URI
  - Filter by To URI

**UI Requirements:**
- Clean, readable layout
- Highlight important information (duration, status)
- Color-coded status badges (success=green, failed=red, etc.)

### 3. CDR Statistics/Dashboard

**Purpose:** Provide overview statistics and charts for CDR data.

**Features:**
- **Key Metrics (Cards):**
  - Total calls (today, this week, this month)
  - Total call duration (formatted: hours, minutes)
  - Average call duration
  - Failed calls count
  - Success rate percentage
  - Most active caller (From URI)
  - Most called destination (To URI)

- **Charts:**
  - Calls over time (line chart: last 7 days, 30 days, custom range)
  - Calls by hour (bar chart: 24-hour distribution)
  - Calls by day of week (bar chart: Monday-Sunday)
  - Call duration distribution (histogram)
  - SIP code distribution (pie chart: success vs failures)
  - Top 10 callers (bar chart: by From URI)
  - Top 10 destinations (bar chart: by To URI)

- **Time Range Selector:**
  - Presets: Today, Yesterday, Last 7 days, Last 30 days, This month, This year, Custom range

**UI Requirements:**
- Responsive dashboard layout
- Real-time updates (optional, via polling or WebSocket)
- Export charts as images
- Drill-down capability (click chart to filter CDR list)

### 4. Active Calls Monitor (Dialog Table)

**Purpose:** Real-time monitoring of active calls.

**Features:**
- **Live Call List:**
  - Columns: Call-ID, From URI, To URI, State, Duration (live), Start Time
  - Auto-refresh (every 5-10 seconds)
  - Sortable columns
  - Filter by state (early, confirmed)

- **Call Details:**
  - Click to view full dialog information
  - Show call duration (live updating)
  - Show call state transitions

- **Actions:**
  - View CDR (when call ends)
  - Terminate call (if supported, role-based)

**UI Requirements:**
- Real-time updates (WebSocket or polling)
- Visual indicators for call state
- Duration counter (live updating)
- Alert for long-running calls (configurable threshold)

### 5. CDR Reports

**Purpose:** Generate and export custom reports.

**Features:**
- **Report Templates:**
  - Daily call summary
  - Weekly call summary
  - Monthly call summary
  - Billing report (by From URI, by To URI, by date range)
  - Failed calls report
  - Longest calls report
  - Most active users report

- **Report Configuration:**
  - Date range selection
  - Group by: Day, Week, Month, From URI, To URI
  - Include/exclude columns
  - Format: PDF, CSV, Excel

- **Scheduled Reports:**
  - Email reports (daily, weekly, monthly)
  - Export to storage
  - Role-based access

**UI Requirements:**
- Report builder interface
- Preview before generation
- Report history
- Download links

## Technical Implementation Notes

### Database Connection

**Connection Details:**
- Database: `opensips`
- Host: Configurable (default: `localhost`)
- User: Configurable (default: `opensips`)
- Password: From environment/config
- Same connection as dispatcher/domain tables

**Laravel Configuration:**
```php
// config/database.php
'opensips' => [
    'driver' => 'mysql',
    'host' => env('OPENSIPS_DB_HOST', 'localhost'),
    'database' => env('OPENSIPS_DB_DATABASE', 'opensips'),
    'username' => env('OPENSIPS_DB_USERNAME', 'opensips'),
    'password' => env('OPENSIPS_DB_PASSWORD'),
    // ...
],
```

### Models

**CDR Model (`app/Models/Cdr.php`):**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cdr extends Model
{
    protected $connection = 'opensips';
    protected $table = 'acc';
    
    protected $fillable = [
        'method', 'callid', 'from_tag', 'to_tag',
        'from_uri', 'to_uri', 'sip_code', 'sip_reason',
        'time', 'created', 'duration', 'ms_duration', 'setuptime'
    ];
    
    protected $casts = [
        'created' => 'datetime',
        'time' => 'datetime',
        'duration' => 'integer',
        'ms_duration' => 'integer',
        'setuptime' => 'integer',
    ];
    
    // Scopes
    public function scopeSuccessful($query) {
        return $query->where('sip_code', '200');
    }
    
    public function scopeFailed($query) {
        return $query->where('sip_code', '!=', '200');
    }
    
    public function scopeDateRange($query, $start, $end) {
        return $query->whereBetween('created', [$start, $end]);
    }
    
    // Accessors
    public function getFormattedDurationAttribute() {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
    
    public function getStatusAttribute() {
        return $this->sip_code == '200' ? 'success' : 'failed';
    }
}
```

**Dialog Model (`app/Models/Dialog.php`):**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dialog extends Model
{
    protected $connection = 'opensips';
    protected $table = 'dialog';
    
    protected $fillable = [
        'dlg_id', 'callid', 'from_uri', 'to_uri',
        'from_tag', 'to_tag', 'state', 'start_time',
        'created', 'modified'
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'state' => 'integer',
    ];
    
    // Relationships
    public function cdr() {
        return $this->hasOne(Cdr::class, 'callid', 'callid');
    }
    
    // Scopes
    public function scopeActive($query) {
        return $query->whereIn('state', [1, 2]); // early or confirmed
    }
    
    public function scopeConfirmed($query) {
        return $query->where('state', 2);
    }
}
```

### Filament Resources

**CDR Resource (`app/Filament/Resources/CdrResource.php`):**
- Use Filament's table resource
- Configure columns, filters, actions
- Custom formatters for duration, timestamps
- Export functionality

**Dialog Resource (`app/Filament/Resources/DialogResource.php`):**
- Real-time updates (polling)
- Live duration calculation
- State badges

### API Endpoints (Optional)

If needed for external integrations or AJAX:
- `GET /api/cdr` - List CDRs with filtering
- `GET /api/cdr/{id}` - Get single CDR
- `GET /api/cdr/stats` - Get statistics
- `GET /api/dialogs` - Get active dialogs
- `GET /api/dialogs/{id}` - Get single dialog

## User Roles & Permissions

**Suggested Roles:**
- **Admin:** Full access (view, export, delete)
- **Operator:** View and export (no delete)
- **Viewer:** View only (no export, no delete)

**Permissions:**
- `cdr.view` - View CDR list
- `cdr.view.detail` - View CDR details
- `cdr.export` - Export CDRs
- `cdr.delete` - Delete CDRs
- `dialog.view` - View active calls
- `stats.view` - View statistics/dashboard

## Performance Considerations

**Large Dataset Handling:**
- CDR table can grow large over time
- Implement pagination (default: 50 records per page)
- Use database indexes on frequently queried columns:
  - `created` (for date filtering)
  - `callid` (for lookups)
  - `from_uri`, `to_uri` (for filtering)
  - `sip_code` (for status filtering)
- Consider archiving old CDRs (older than X months)
- Use database views for complex queries

**Query Optimization:**
- Use eager loading for relationships
- Cache statistics (refresh every 5-10 minutes)
- Use database aggregation for statistics
- Limit date range queries (default: last 30 days)

## Integration Points

### With Existing Admin Panel
- Use same authentication/authorization system
- Follow existing UI/UX patterns
- Integrate with navigation menu
- Use existing export/import patterns

### With Prometheus/Grafana (Future)
- Dialog table provides real-time metrics
- CDR table provides historical metrics
- Expose metrics via API or direct database access

### With Billing System (Future)
- CDR data is billing-ready (From/To URIs, duration)
- Export functionality for billing integration
- API endpoints for automated billing queries

## Testing Requirements

**Unit Tests:**
- Model relationships
- Scopes and accessors
- Formatters

**Feature Tests:**
- CDR list filtering
- CDR detail view
- Statistics calculation
- Export functionality
- Permission checks

**Integration Tests:**
- Database connection
- Large dataset handling
- Date range queries
- Real-time dialog updates

## Future Enhancements

**Phase 2:**
- Advanced analytics (call patterns, peak hours)
- Call recording integration (if available)
- Cost calculation (if rate tables available)
- Automated billing export
- Webhook notifications for failed calls

**Phase 3:**
- Real-time call monitoring dashboard
- Call quality metrics (if available)
- Geographic call distribution (if location data available)
- Custom report builder
- Scheduled report delivery

## References

- CDR Implementation: `pbx3sbc/workingdocs/CDR-VERIFICATION-RESULTS.md`
- CDR Verification: `pbx3sbc/workingdocs/CDR-VERIFICATION-CHECKLIST.md`
- Database Schema: `pbx3sbc/scripts/init-database.sh`
- OpenSIPS ACC Module: https://opensips.org/docs/modules/3.6.x/acc.html
- OpenSIPS Dialog Module: https://opensips.org/docs/modules/3.6.x/dialog.html

---

**Document Status:** Ready for Development  
**Last Updated:** 2026-01-18  
**Next Steps:** Begin implementation of CDR List/Table View panel
