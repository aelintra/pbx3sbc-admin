# Call Routes Implementation Status

**Last Updated:** 2026-01-21  
**Status:** Partially Complete - Core functionality working, modal enhancements in progress

## Completed ✅

1. **Call Routes Unified Resource**
   - Created `CallRouteResource` that unifies Domain + Dispatcher management
   - Auto-manages `setid` field (users never see it)
   - Create/Edit/Delete routes with OpenSIPS MI integration
   - Domain and Dispatcher resources hidden from navigation

2. **Manage Destinations Modal**
   - Modal opens correctly
   - Displays all destinations for a domain
   - Edit links work (open in new tab)
   - Delete functionality works (removes row, updates count, triggers MI reload)
   - View shows destination details (SIP URI, Weight, Priority, State, Description)

3. **Edit Domain Functionality**
   - "Edit Domain" action button added
   - Opens modal to edit just the domain name
   - Validates domain format
   - Triggers OpenSIPS MI reload

4. **UI/UX Improvements**
   - Removed Edit button from Call Routes table (Manage handles it)
   - Removed View button (misleading for multi-destination domains)
   - Changed "Manage Destinations" to just "Manage"
   - Added warning to Delete action about deleting entire route

## In Progress / Issues ⚠️

1. **Add Destination in Modal**
   - **Status:** Not working
   - **Issue:** Form submission not being intercepted by JavaScript
   - **Attempted Fixes:**
     - Added `onsubmit="return false;"` to form
     - Changed button to `type="button"`
     - Added event delegation and direct handlers
     - **Current State:** Button does nothing, no console errors
   - **Next Steps:** Need to debug why event handlers aren't firing

2. **Edit Destination Inline**
   - **Status:** Not working
   - **Issue:** Edit button click not triggering edit mode
   - **Attempted Fixes:**
     - Event delegation on document
     - Multiple setup attempts with delays
     - Using `closest()` to find buttons
     - **Current State:** Edit button doesn't respond
   - **Next Steps:** Simplify approach or use Filament's built-in edit actions

3. **Alpine/Livewire Warnings**
   - **Status:** Cosmetic warnings, don't break functionality
   - **Issue:** Alpine expressions trying to access `$event.detail?.id` which is undefined
   - **Impact:** Warnings in console but functionality works (delete works despite warnings)
   - **Next Steps:** Can be ignored for now, or investigate Livewire modal integration

## Technical Details

### Files Created/Modified

**New Files:**
- `app/Filament/Resources/CallRouteResource.php` - Main unified resource
- `app/Filament/Resources/CallRouteResource/Pages/*` - CRUD pages
- `app/Http/Controllers/DispatcherController.php` - API endpoints for modal operations
- `resources/views/filament/tables/expandable-destinations.blade.php` - Modal content view
- `resources/views/filament/tables/destination-row.blade.php` - Destination row partial
- `config/opensips.php` - OpenSIPS MI configuration

**Modified Files:**
- `app/Filament/Resources/DomainResource.php` - Hidden from navigation
- `app/Filament/Resources/DispatcherResource.php` - Hidden from navigation, renamed to "Destinations"
- `app/Models/Domain.php` - Added `dispatchers()` relationship
- `routes/web.php` - Added API routes for dispatcher CRUD

### Routes Added

```php
POST   /admin/dispatchers              -> DispatcherController@store
PUT    /admin/dispatchers/{dispatcher} -> DispatcherController@update  
DELETE /admin/dispatchers/{dispatcher} -> DispatcherController@destroy
```

### Current Architecture

- **Call Routes Panel:** Primary interface for all route management
- **Manage Modal:** Shows destinations, allows delete, edit (via new tab), add (not working)
- **Edit Domain Modal:** Allows editing domain name only
- **Delete Route:** Removes domain + all destinations with warning

## Known Issues

1. **Add Destination Form Submission**
   - Form has `onsubmit="return false;"` and button is `type="button"`
   - Event handlers set up but not firing
   - No console errors, button does nothing
   - **Possible Causes:**
     - Event handlers not attached when modal loads
     - JavaScript conflicts with Livewire
     - Form/button selectors not matching

2. **Edit Destination Inline**
   - Edit button click not triggering `toggleEditMode()`
   - Event delegation may not be working correctly
   - **Possible Causes:**
     - Event handlers not attached
     - Click events being intercepted by Livewire
     - Modal content loading asynchronously

3. **Alpine/Livewire Warnings**
   - `Alpine Expression Error: undefined` for `$event.detail?.id`
   - Happens when modal content is manipulated
   - Doesn't break functionality but creates noise in console

## Recommendations for Next Session

### Option 1: Simplify Add/Edit (Recommended)
- Remove inline editing complexity
- Use Filament's built-in edit actions (open in modal/page)
- For "Add Destination": Use a simple form that submits and reloads modal content
- Accept that some operations require leaving the modal

### Option 2: Fix Current Implementation
- Debug why event handlers aren't firing
- Check if Livewire is preventing event propagation
- Consider using Livewire components instead of plain JavaScript
- Use Filament's action system for add/edit operations

### Option 3: Hybrid Approach
- Keep delete working (it works!)
- For add/edit: Redirect to Edit Call Route page
- Simpler, more reliable, less JavaScript complexity

## What Works

✅ Create Call Route (domain + destination)  
✅ View Call Routes table  
✅ Manage modal opens and displays destinations  
✅ Delete destination from modal  
✅ Delete entire route (domain + destinations)  
✅ Edit domain name  
✅ Edit destination (opens in new tab)  
✅ OpenSIPS MI integration (reloads after changes)  

## What Doesn't Work

❌ Add destination from modal (form submission not intercepted)  
❌ Edit destination inline in modal (button doesn't respond)  

## Next Steps

1. Decide on approach: Simplify vs. Fix current implementation
2. If simplifying: Remove complex JavaScript, use Filament's built-in mechanisms
3. If fixing: Debug event handler attachment and Livewire conflicts
4. Test all CRUD operations end-to-end
5. Remove Domain and Dispatcher panels from navigation (already done)
