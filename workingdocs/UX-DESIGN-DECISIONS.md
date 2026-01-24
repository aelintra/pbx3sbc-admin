# UX Design Decisions

**Last Updated:** 2026-01-22  
**Purpose:** Document UX design decisions, rationale, and improvements for Call Routes management

## Overview

This document covers the UX design decisions for the Call Routes management interface, including the evolution from separate Domain/Dispatcher panels to a unified Call Routes resource.

---

## Route Management UX Evolution

### Original Problem

The initial two-panel approach (Domain + Dispatcher) had several UX issues:

1. **Two Separate Panels:**
   - Domain Resource (create domain with setid)
   - Dispatcher Resource (create destinations with setid)
   - Users must remember/coordinate setid values

2. **Hidden Relationship:**
   - The connection between domain and dispatcher set isn't obvious
   - Users must understand that setid links them

3. **Manual Coordination:**
   - User creates domain with setid=5
   - User must remember to use setid=5 when creating dispatcher entries
   - Easy to make mistakes

4. **Conceptual Model vs. UI:**
   - **Conceptual:** "Route calls from domain X to PBX Y"
   - **Original UI:** "Create domain with setid, then create dispatcher with same setid"

### Solution: Unified "Call Routes" Resource

**Concept:** Treat domain + dispatcher set as a single "Route" entity.

**Key Features:**
- Single unified interface
- Relationship is obvious
- No setid coordination needed
- Matches user's mental model ("route calls from X to Y")
- Auto-manages setid (hidden from users, auto-generated)

**Implementation:**
- `CallRouteResource` uses `Domain` model as primary entity
- Auto-generates unique setid for each new domain
- Shows domain with its dispatcher destinations in one view
- Create/edit route in single form

---

## Multi-Destination Handling

### Current Implementation

**Status:** ✅ Fully Implemented and Working

**Approach:** "Manage Destinations" action redirects to Destinations panel

**Implementation:**
- "Manage Destinations" action button on each domain row in Call Routes table
- Action redirects to Destinations panel (DispatcherResource) filtered by domain's setid
- Destinations panel shows all destinations for the selected domain
- Full CRUD operations available in Destinations panel (create/edit/delete)
- Filter preserved when navigating back/forth between Call Routes and Destinations

**Files:**
- `app/Filament/Resources/CallRouteResource.php` - Action definition (lines 272-282)
- `app/Filament/Resources/DispatcherResource.php` - Destinations panel with setid filtering
- `app/Filament/Resources/DispatcherResource/Pages/ListDispatchers.php` - Filter handling and redirect logic

### Alternative Options Considered

#### Option 1: Expandable Rows with Nested Destination Actions
- Keep domain-based table, make rows expandable
- Show all destinations when row is expanded
- Each destination has its own Edit/Delete actions

**Pros:** Clear hierarchy, can manage all destinations in one place  
**Cons:** Requires expandable rows implementation, slightly more complex UI

#### Option 2: Flatten to Destination-Based Table
- Change resource to be based on Dispatcher model
- Show each destination as a separate row
- Add domain column to show which domain each destination belongs to

**Pros:** Simple, direct edit/delete of individual destinations  
**Cons:** Loses domain-centric view, harder to see all destinations for a domain

#### Option 3: Relation Manager for Destinations
- Keep domain-based table
- Click "Manage Destinations" opens RelationManager
- RelationManager shows all destinations for that domain

**Pros:** Clean separation, Filament built-in pattern  
**Cons:** Requires navigation to separate page, two-step process

**Selected:** Current redirect approach (similar to Option 3 but simpler)

---

## Create Call Route Form Improvements (2026-01-22)

### Problems Identified

1. **No field focused on first render** - Caused user uncertainty
2. **"Create new domain" option hidden** - Only visible after clicking dropdown
3. **Option at bottom of list** - Not visible without scrolling
4. **Empty View page after creation** - Creation fields still visible (confusing)

### Solutions Implemented

#### 1. Explicit Radio Button Selection
- Replaced hidden dropdown option with Radio buttons
- "Use existing domain" and "Create new domain" are always visible upfront
- No need to open dropdown to see all options
- Better for accessibility and clarity

#### 2. Autofocus on Form Load
- Radio button group gets autofocus when page loads
- User immediately knows where to start
- When "new" is selected, domain text input gets autofocus

#### 3. Improved Post-Create Redirect
- After creating a call route, user is redirected to Destinations page
- Page is automatically filtered by the domain's setid
- Shows all destinations for that domain, including the one just created
- More logical flow: create route → see destinations

#### 4. View Page Cleanup
- Creation fields (radio buttons, domain select) are now properly hidden on ViewCallRoute page
- View page shows only actual data, not creation form

#### 5. Simplified Form Actions
- Removed "Create and create another" button
- Only "Create" button shown (using `protected static bool $canCreateAnother = false;`)

**Files Modified:**
- `app/Filament/Resources/CallRouteResource.php` - Form schema updated with Radio buttons
- `app/Filament/Resources/CallRouteResource/Pages/CreateCallRoute.php` - Added redirect logic, removed "create another" button
- Visibility conditions updated to hide creation fields on View page

**Result:**
- Better initial user experience (clear choice, focused field)
- No hidden options (all choices visible)
- Logical post-create flow (redirect to destinations)
- Cleaner view page (no creation fields)

---

## Data Model & Relationships

### Confirmed Relationships

1. **Domain → SetID:**
   - One domain = one setid (1:1)
   - **SetID MUST be unique within the domain table** (one setid per domain)
   - Each domain has its own unique setid

2. **SetID → Dispatcher:**
   - One setid = many dispatcher rows (1:many)
   - Multiple dispatcher rows with same setid = multiple destinations
   - Enables failover and load balancing
   - **SetID is NOT unique in the dispatcher table** (multiple dispatcher rows share same setid)

3. **Overall Model:**
   - Domain (1) → SetID (unique, 1:1) → Dispatcher Set (many destinations)
   - Classic ER would be: Domain → Link Table → Destination
   - OpenSIPS uses setid as the link (denormalized)

### Implications for UX

- One domain → one dispatcher set (via setid) - strict 1:1 relationship
- One dispatcher set → multiple destinations (for failover/load balancing)
- **Each domain has its own unique dispatcher set** (cannot share setid between domains)
- When creating a route:
  - Always create new setid (auto-generated, unique per domain)
  - Cannot reuse existing setid (each domain gets its own)

### SetID Management Strategy

1. **For new routes:** Auto-increment highest setid + 1 (always create new unique setid)
2. **For existing domains:** Use existing setid (cannot change)
3. **Never expose setid to users:** Hide from forms, show only in advanced view if needed
4. **Each domain gets unique setid:** Cannot share setid between domains

**UI for SetID Management:**
- Show as "Dispatcher Set" not "Set ID" (hidden from users)
- Auto-generate unique setid for each new domain
- Display: "Set 5" (for reference only, in advanced view)
- Each domain gets its own unique setid (cannot share)

---

## Multi-Tenant Scenarios

**Note on Multi-Tenant Scenarios:**
- Each domain has its own unique setid and dispatcher set
- Multiple domains can route to the same PBX backend destinations, but each domain maintains its own dispatcher set
- To route multiple domains to same backend, create separate routes with same destination URIs but different setids

**Example:**
```
Route: example.com → PBX Asterisk-1 (192.168.1.100:5060)
├── Domain: example.com
├── Dispatcher Set: Set 5 (Private - 1 domain)
└── Destinations:
    ├── sip:192.168.1.100:5060 (Active, Weight: 1)
    └── sip:192.168.1.101:5060 (Active, Weight: 1) [Backup]

Route: tenant3.com → PBX Multi-Tenant (192.168.1.50:5060)
├── Domain: tenant3.com
├── Dispatcher Set: Set 10 (Private - 1 domain)
└── Destinations:
    ├── sip:192.168.1.50:5060 (Active, Weight: 1) [Primary]
    └── sip:192.168.1.51:5060 (Active, Weight: 1) [Backup]
```

---

## Key UX Principles Applied

1. **"Don't make me think"** - Clear choices, obvious relationships
2. **Progressive disclosure** - Show what's needed when it's needed
3. **Consistent patterns** - Use Filament conventions
4. **Error prevention** - Auto-manage setid, validate inputs
5. **Feedback** - Clear redirects, notifications, visual indicators

---

## Related Documentation

- `PROJECT-CONTEXT.md` - Project architecture and database schema
- `CURRENT-STATE.md` - Current implementation status
- `CODE-QUALITY.md` - Code review and best practices
