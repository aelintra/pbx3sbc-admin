# Route Management UX Improvements

**Date:** 2026-01-21  
**Problem:** Current two-panel approach (Domain + Dispatcher) is clunky. Users must manually coordinate `setid` values, and the relationship between domain and dispatcher destinations isn't obvious.

## Current UX Problems

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
   - **Current UI:** "Create domain with setid, then create dispatcher with same setid"

## Proposed Solutions

### Option 1: Unified "Call Routes" Resource (Recommended)

**Concept:** Treat domain + dispatcher set as a single "Route" entity.

**UI Structure:**
```
Call Routes
├── Route: example.com → PBX Asterisk-1 (192.168.1.100:5060)
│   ├── Domain: example.com
│   ├── Dispatcher Set: Set 5 (Private - 1 domain)
│   └── Destinations:
│       ├── sip:192.168.1.100:5060 (Active, Weight: 1)
│       └── sip:192.168.1.101:5060 (Active, Weight: 1) [Backup]
│
├── Route: another.com → PBX Asterisk-2 (192.168.1.200:5060)
│   ├── Domain: another.com
│   ├── Dispatcher Set: Set 6 (Private - 1 domain)
│   └── Destinations:
│       └── sip:192.168.1.200:5060 (Active, Weight: 1)
│
└── Route: tenant3.com → PBX Multi-Tenant (192.168.1.50:5060)
    ├── Domain: tenant3.com
    ├── Dispatcher Set: Set 10 (Private - 1 domain)
    └── Destinations:
        ├── sip:192.168.1.50:5060 (Active, Weight: 1) [Primary]
        └── sip:192.168.1.51:5060 (Active, Weight: 1) [Backup]
```

**Note on Multi-Tenant Scenarios:**
- Each domain has its own unique setid and dispatcher set
- Multiple domains can route to the same PBX backend destinations, but each domain maintains its own dispatcher set
- To route multiple domains to same backend, create separate routes with same destination URIs but different setids

**Implementation:**
- New `CallRouteResource` (or `SipRouteResource`)
- Shows domain with its dispatcher destinations in one view
- Auto-manages setid (hidden from users, auto-generated)
- Create/edit route in single form
- Visual relationship is obvious

**Pros:**
- ✅ Single unified interface
- ✅ Relationship is obvious
- ✅ No setid coordination needed
- ✅ Matches user's mental model ("route calls from X to Y")

**Cons:**
- ⚠️ Requires new resource structure
- ⚠️ Need to handle both domain and dispatcher tables

### Option 2: Domain Resource with Inline Dispatcher Management

**Concept:** Keep Domain Resource, but show dispatcher destinations inline.

**UI Structure:**
```
Domains
├── example.com (Set ID: 5)
│   └── Destinations:
│       ├── sip:192.168.1.100:5060 [Edit] [Delete]
│       └── [+ Add Destination]
│
└── another.com (Set ID: 6)
    └── Destinations:
        └── sip:192.168.1.200:5060 [Edit] [Delete]
```

**Implementation:**
- Enhance DomainResource with RelationManager or custom view
- Show dispatcher destinations grouped by domain
- Auto-populate setid when adding destinations

**Pros:**
- ✅ Keeps existing Domain Resource
- ✅ Relationship visible
- ✅ Less restructuring needed

**Cons:**
- ⚠️ Still two-step (create domain, then add destinations)
- ⚠️ setid still visible (though auto-populated)

### Option 3: Wizard-Based Route Creation

**Concept:** Guided multi-step wizard to create complete route.

**Steps:**
1. **Step 1: Domain**
   - Enter domain name
   - Check if exists, auto-populate setid if it does
   - Or create new domain (setid auto-generated)

2. **Step 2: Destinations**
   - Add one or more PBX destinations
   - Configure weight, priority, description

3. **Step 3: Review**
   - Show complete route: Domain → Destinations
   - Confirm before creating

**Implementation:**
- Custom Filament page with Wizard component
- Single transaction creates domain + dispatchers
- Auto-generates setid

**Pros:**
- ✅ Guided, user-friendly
- ✅ Complete route in one operation
- ✅ Review step prevents mistakes

**Cons:**
- ⚠️ Only for creation (not editing existing routes)
- ⚠️ Still need separate view for managing routes

### Option 4: Hybrid Approach (Best UX)

**Combine Options 1 + 3:**

1. **Unified "Call Routes" Resource** for viewing/managing routes
   - Shows domain → destinations relationship clearly
   - Auto-manages setid (hidden from users)

2. **Wizard for Creating Routes**
   - Guided creation of complete routes
   - Single operation creates domain + dispatchers

3. **Enhanced Domain Resource** (keep for advanced users)
   - Still available for direct domain management
   - Can be hidden from navigation if desired

**UI Flow:**
```
Navigation:
├── Call Routes (main interface) ← New unified resource
│   ├── List Routes (shows domain → destinations)
│   ├── Create Route (wizard)
│   └── Edit Route (single form for domain + destinations)
│
├── Domains (advanced) ← Keep existing, maybe hide from nav
└── Dispatchers (advanced) ← Keep existing, maybe hide from nav
```

## Recommended Implementation: Option 4 (Hybrid)

### Phase 1: Create Unified "Call Routes" Resource

**New Resource:** `CallRouteResource`

**Data Model:**
- Virtual model that represents domain + dispatcher set
- Uses existing Domain and Dispatcher models
- Auto-generates/manages setid

**List View:**
```
┌─────────────────────────────────────────────────────────────┐
│ Call Routes                                                  │
├─────────────────────────────────────────────────────────────┤
│ Domain          │ Destinations                    │ Actions │
├─────────────────┼─────────────────────────────────┼─────────┤
│ example.com     │ • sip:192.168.1.100:5060       │ [Edit]  │
│                 │   (Active, Weight: 1)          │ [Delete]│
│                 │ • sip:192.168.1.101:5060       │         │
│                 │   (Active, Weight: 1)         │         │
├─────────────────┼─────────────────────────────────┼─────────┤
│ another.com     │ • sip:192.168.1.200:5060       │ [Edit]  │
│                 │   (Active, Weight: 1)          │ [Delete]│
└─────────────────┴─────────────────────────────────┴─────────┘
```

**Create/Edit Form:**
```
┌─────────────────────────────────────────────────────────────┐
│ Create Call Route                                            │
├─────────────────────────────────────────────────────────────┤
│ Domain Name: [example.com________________]                  │
│                                                              │
│ Dispatcher Set:                                             │
│ • Set ID will be auto-generated (unique per domain)         │
│ • Each domain gets its own dispatcher set                   │
│ • Set ID: [Auto-generated, hidden from user]               │
│                                                              │
│ Destinations:                                                │
│ ┌────────────────────────────────────────────────────────┐ │
│ │ Destination 1:                                          │ │
│ │   SIP URI: [sip:192.168.1.100:5060]                    │ │
│ │   Weight: [1]  Priority: [0]  Description: [Primary]   │ │
│ │   State: [Active ▼]                                     │ │
│ └────────────────────────────────────────────────────────┘ │
│                                                              │
│ [+ Add Another Destination]                                 │
│                                                              │
│ [Cancel] [Create Route]                                     │
└─────────────────────────────────────────────────────────────┘
```

**Note:** Each domain gets its own unique dispatcher set (setid). Setid is auto-generated and hidden from users.

**Key Features:**
- **Auto-manage setid:** Generate automatically, never show to user
- **Single form:** Domain + destinations in one place
- **Visual relationship:** Clear that destinations belong to domain
- **Transaction safety:** All-or-nothing creation

### Phase 2: Auto-Generate SetID

**Strategy:**
1. **For new routes:** Auto-increment highest setid + 1 (always create new unique setid)
2. **For existing domains:** Use existing setid (cannot change)
3. **Never expose setid to users:** Hide from forms, show only in advanced view if needed
4. **Each domain gets unique setid:** Cannot share setid between domains (setid is unique in domain table)

**UI for SetID Management:**
- Show as "Dispatcher Set" not "Set ID" (hidden from users)
- Auto-generate unique setid for each new domain
- Display: "Set 5" (for reference only, in advanced view)
- Each domain gets its own unique setid (cannot share)

**Implementation:**
```php
// In CallRouteResource
protected function handleRecordCreation(array $data): Model
{
    return DB::transaction(function () use ($data) {
        // Auto-generate setid if new domain
        if (!isset($data['setid']) || $data['setid'] === null) {
            $maxSetid = Domain::max('setid') ?? 0;
            $data['setid'] = $maxSetid + 1;
        }
        
        // Create domain
        $domain = Domain::create([
            'domain' => $data['domain'],
            'setid' => $data['setid'],
            'accept_subdomain' => $data['accept_subdomain'] ?? 0,
        ]);
        
        // Create dispatcher entries
        foreach ($data['destinations'] as $dest) {
            Dispatcher::create([
                'setid' => $domain->setid,
                'destination' => $dest['destination'],
                'weight' => $dest['weight'] ?? '1',
                'priority' => $dest['priority'] ?? 0,
                'state' => $dest['state'] ?? 0,
                'description' => $dest['description'] ?? '',
            ]);
        }
        
        return $domain; // Return domain as the "route" record
    });
}
```

### Phase 3: Visual Route Representation

**Show the complete path:**
```
Route: example.com → Asterisk PBX
├── Domain: example.com
├── Set ID: 5 (auto-managed, hidden from users)
└── Destinations:
    ├── Primary: sip:192.168.1.100:5060 (Active, Weight: 1)
    └── Backup: sip:192.168.1.101:5060 (Active, Weight: 1)
```

## Implementation Plan

### Step 1: Create Route Model (Virtual/Aggregate)

```php
// app/Models/CallRoute.php
class CallRoute
{
    // Virtual model that aggregates Domain + Dispatcher
    // Doesn't map to a table, but represents the relationship
}
```

Or use Domain as the primary model and add relationship methods.

### Step 2: Create CallRouteResource

```php
// app/Filament/Resources/CallRouteResource.php
class CallRouteResource extends Resource
{
    protected static ?string $model = Domain::class; // Use Domain as base
    
    // Customize to show domain + dispatchers together
    // Auto-manage setid
    // Single form for domain + destinations
}
```

### Step 3: Add Relationship Methods

```php
// app/Models/Domain.php
public function dispatchers()
{
    return $this->hasMany(Dispatcher::class, 'setid', 'setid');
}
```

### Step 4: Customize List View

Show domain with its dispatcher destinations in expandable rows or grouped view.

### Step 5: Customize Form

Single form with:
- Domain name field
- Repeater for destinations
- Auto-generate setid (hidden field)

## Benefits

1. **Better UX:**
   - Single interface for complete routes
   - No setid coordination needed
   - Relationship is obvious

2. **Matches Mental Model:**
   - "Route calls from domain X to PBX Y"
   - Not "Create domain with setid, then create dispatcher with same setid"
   - Each domain has its own route configuration

3. **Reduces Errors:**
   - Can't mismatch setid values
   - Transaction ensures consistency

4. **Simpler Workflow:**
   - One operation creates complete route
   - Edit route in one place
   - Each domain has its own unique dispatcher set

5. **Multi-Tenant Support:**
   - Each domain has its own dispatcher set (unique setid)
   - Multiple domains can route to same PBX destinations by creating separate routes with same destination URIs
   - Clear visualization of each domain's routing configuration

## Migration Path

1. **Keep existing resources** (Domain, Dispatcher) for now
2. **Add new CallRouteResource** alongside them
3. **Test with users** - get feedback
4. **Optionally hide** Domain/Dispatcher from navigation (keep for advanced users)
5. **Eventually deprecate** separate resources if CallRoute works well

## Data Model Clarification

**Confirmed Relationships:**

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

**Implications for UX:**
- One domain → one dispatcher set (via setid) - strict 1:1 relationship
- One dispatcher set → multiple destinations (for failover/load balancing)
- **Each domain has its own unique dispatcher set** (cannot share setid between domains)
- When creating a route:
  - Always create new setid (auto-generated, unique per domain)
  - Cannot reuse existing setid (each domain gets its own)

## Questions to Consider

1. **Can one domain have multiple dispatcher sets?**
   - ✅ **Answer:** No - one domain has one setid (1:1 relationship)

2. **Can one dispatcher set serve multiple domains?**
   - ✅ **Answer:** No - setid is unique in domain table, each domain has its own unique setid
   - **UX Implication:** Always create new setid when creating new domain (auto-generated)

3. **Do users need to see setid at all?**
   - ✅ **Answer:** No - it exists ONLY to link domain to destinations
   - **Recommendation:** Hide it completely from users
   - Show only in "Advanced" view for debugging if needed

4. **What happens when editing?**
   - Can user change domain name? (probably not - create new route)
   - Can user add/remove destinations? (yes)
   - Can user change setid? (no - auto-managed, unique per domain)

## Next Steps

1. **Decide on approach** (recommend Option 4: Hybrid)
2. **Create CallRouteResource** with unified view
3. **Implement auto-setid generation**
4. **Test with real users**
5. **Iterate based on feedback**
