# Call Route Multi-Destination Handling Options

**Last Updated:** 2026-01-22  
**Status:** ✅ Fully Implemented and Working

## Current Implementation Status

**Implemented:**
- ✅ "Manage Destinations" action button on each domain row in Call Routes table
- ✅ Action redirects to Destinations panel (DispatcherResource) filtered by domain's setid
- ✅ Destinations panel shows all destinations for the selected domain
- ✅ Full CRUD operations available in Destinations panel (create/edit/delete)
- ✅ Filter preserved when navigating back/forth between Call Routes and Destinations

**Implementation Details:**
- Uses Filament's URL redirect action (not a modal)
- Redirects to `DispatcherResource::getUrl('index')` with `tableFilters[setid][value]` parameter
- Destinations panel automatically filters by setid when accessed via "Manage Destinations"
- "Back to Call Routes" button available in Destinations panel header

**Files:**
- `app/Filament/Resources/CallRouteResource.php` - Action definition (lines 272-282)
- `app/Filament/Resources/DispatcherResource.php` - Destinations panel with setid filtering
- `app/Filament/Resources/DispatcherResource/Pages/ListDispatchers.php` - Filter handling and redirect logic

**Status:** ✅ Complete and Working

---

# Call Route Multi-Destination Handling Options

**Date:** 2026-01-22  
**Problem:** Call Routes resource is based on Domain model, but each domain can have multiple destinations. View/Edit/Delete actions operate on the domain, not individual destinations. When a domain has multiple destinations, you can only edit/delete the first one.

## Current Architecture

- **Resource Model:** `Domain` (one domain = one row)
- **Relationship:** Domain hasMany Dispatchers (via setid)
- **Issue:** Actions (View/Edit/Delete) operate on Domain, not individual Dispatcher records
- **Current Edit Behavior:** Only shows first destination

## Option 1: Expandable Rows with Nested Destination Actions (Recommended)

**Concept:** Keep domain-based table, but make rows expandable to show all destinations with individual actions.

**Implementation:**
- Use Filament's expandable rows feature
- When row is expanded, show table of all destinations for that domain
- Each destination has its own Edit/Delete actions
- Domain row has "Add Destination" action

**Pros:**
- ✅ Clear hierarchy: Domain → Destinations
- ✅ Can manage all destinations for a domain in one place
- ✅ Can add new destinations without leaving the page
- ✅ Domain-level actions still available (edit domain, delete entire route)

**Cons:**
- ⚠️ Requires expandable rows implementation
- ⚠️ Slightly more complex UI

**Example UI:**
```
┌─────────────────────────────────────────────────────────────┐
│ Domain          │ # Dest │ Destinations        │ Actions   │
├─────────────────┼────────┼─────────────────────┼───────────┤
│ example.com     │ 3      │ ✓ sip:192.168...   │ [▼] [Edit]│
│                 │        │ ✓ sip:192.168...   │           │
│                 │        │ ✗ sip:192.168...   │           │
│                 │        │                     │           │
│ Expanded:       │        │                     │           │
│ ┌─────────────┐ │        │ Destination 1       │ [Edit][X] │
│ │ sip:192...  │ │        │ Destination 2       │ [Edit][X] │
│ │ Destination 3│        │                     │ [Edit][X] │
│ │ [+ Add]     │ │        │                     │           │
│ └─────────────┘ │        │                     │           │
└─────────────────┴────────┴─────────────────────┴───────────┘
```

## Option 2: Flatten to Destination-Based Table

**Concept:** Change resource to be based on Dispatcher model, show each destination as a separate row.

**Implementation:**
- Change `CallRouteResource` model from `Domain` to `Dispatcher`
- Add domain column to show which domain each destination belongs to
- Group by domain visually (or use filters)
- Each row = one destination (can edit/delete individually)

**Pros:**
- ✅ Simple: one row = one destination
- ✅ Direct edit/delete of individual destinations
- ✅ No nested UI complexity

**Cons:**
- ⚠️ Loses domain-centric view
- ⚠️ Harder to see all destinations for a domain at once
- ⚠️ Creating a route requires multiple steps (create domain, then add destinations)

**Example UI:**
```
┌─────────────────────────────────────────────────────────────┐
│ Domain          │ Destination        │ Weight │ State │ Actions│
├─────────────────┼────────────────────┼────────┼───────┼────────┤
│ example.com     │ sip:192.168.1.100 │ 1      │ Active│ [Edit] │
│ example.com     │ sip:192.168.1.101 │ 1      │ Active│ [Edit] │
│ example.com     │ sip:192.168.1.102 │ 1      │ Inact │ [Edit] │
│ another.com     │ sip:192.168.1.200 │ 1      │ Active│ [Edit] │
└─────────────────┴────────────────────┴────────┴───────┴────────┘
```

## Option 3: Relation Manager for Destinations

**Concept:** Keep domain-based table, use Filament RelationManager to manage destinations.

**Implementation:**
- Domain table shows domains
- Click "Manage Destinations" action opens RelationManager
- RelationManager shows all destinations for that domain
- Can add/edit/delete destinations within the manager

**Pros:**
- ✅ Clean separation: domains vs destinations
- ✅ Filament built-in pattern
- ✅ Can manage all destinations for a domain together

**Cons:**
- ⚠️ Requires navigation to separate page
- ⚠️ Less immediate than inline editing
- ⚠️ Two-step process (select domain, then manage destinations)

## Option 4: Hybrid: Domain Table + Inline Destination Actions

**Concept:** Keep domain-based table, but add destination management actions directly in the table.

**Implementation:**
- Domain row shows summary of destinations
- Add custom actions: "Edit Destination 1", "Edit Destination 2", etc.
- Or: "Manage Destinations" action that opens modal with all destinations
- Modal allows editing/deleting individual destinations

**Pros:**
- ✅ Keeps domain-centric view
- ✅ Can manage individual destinations
- ✅ Modal keeps context (still on same page)

**Cons:**
- ⚠️ Modal might be cluttered with many destinations
- ⚠️ Less intuitive than expandable rows

## Option 5: Two-Level Navigation

**Concept:** Domain list → Click domain → Destination list for that domain.

**Implementation:**
- Main table shows domains only
- Click domain opens destination management page
- Destination page shows all destinations for that domain
- Can add/edit/delete destinations on that page

**Pros:**
- ✅ Very clear separation
- ✅ Simple implementation
- ✅ Easy to understand

**Cons:**
- ⚠️ Requires navigation away from main table
- ⚠️ Can't see destinations at a glance
- ⚠️ More clicks to manage routes

## Recommendation: Option 1 (Expandable Rows)

**Why:**
1. **Best UX:** Shows domain → destinations hierarchy clearly
2. **Efficient:** Can see all destinations without leaving the page
3. **Flexible:** Can manage both domain-level and destination-level operations
4. **Scalable:** Works well even with many destinations per domain

**Implementation Approach:**
1. Use Filament's `expandable()` feature on table rows
2. Create custom view component for expanded content
3. Show destinations table with individual actions
4. Add "Add Destination" action in expanded view

**Alternative Quick Fix:**
- If expandable rows are complex, use Option 4 (Modal) as interim solution
- Modal can be implemented quickly while planning expandable rows

## Questions to Consider

1. **How many destinations per domain typically?**
   - If usually 1-2: Option 2 (flatten) might be simpler
   - If 3+: Option 1 (expandable) is better

2. **Do users need to see all destinations at once?**
   - Yes → Option 1 or 2
   - No → Option 3 or 5

3. **How often do users edit individual destinations vs entire routes?**
   - Individual edits → Option 1, 2, or 4
   - Route-level edits → Current approach is fine

4. **Should domain and destination editing be separate?**
   - Yes → Option 3 or 5
   - No → Option 1 or 4
