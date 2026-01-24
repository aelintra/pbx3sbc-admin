# Current State Summary

**Last Updated:** 2026-01-22  
**Purpose:** Quick reference for current implementation status and known issues

## Recent Changes (2026-01-22)

### ✅ UX Improvements - Create Call Route Form
- **Replaced hidden dropdown option with explicit Radio buttons** - "Use existing domain" vs "Create new domain" are now always visible upfront
- **Added autofocus** - Radio button group gets focus on page load, improving initial user experience
- **Better field visibility** - Domain select dropdown appears when "existing" is selected, domain text input appears when "new" is selected
- **Improved redirect behavior** - After creating a call route, user is redirected to Destinations page filtered by the domain's setid (shows all destinations for that domain)
- **Removed "Create and create another" button** - Using `protected static bool $canCreateAnother = false;` in CreateCallRoute page
- **Fixed view page confusion** - Creation fields (radio buttons, domain select) are now properly hidden on ViewCallRoute page

### ✅ UX Improvements - Destinations Panel
- **Fixed delete action filter preservation** - When deleting a destination, the setid filter is now preserved by getting it from the deleted record
- **Fixed create destination redirect** - After creating a destination, user is redirected back to Destinations list filtered by setid (not to Edit page)
- **Removed "Create and create another" button** - Using `protected static bool $canCreateAnother = false;` in CreateDispatcher page
- **Fixed delete call route redirect** - When deleting a call route, user is redirected to Call Routes list (prevents showing all destinations with invalid filter)
- **Added domain validation** - Destinations page validates domain exists when setid filter is present, redirects if domain was deleted

### ✅ Code Quality Improvements
- **N+1 queries fixed** - CallRouteResource now uses model accessors with eager-loaded relationships
- **Production-ready config** - Replaced direct `env()` usage with `config()` for compatibility with config caching
- **Better code maintainability** - Refactored direct queries to use Eloquent relationships (`$domain->dispatchers()`)
- **Transaction scope documented** - Clarified Filament lifecycle limitations in EditCallRoute

**See Also:**
- `COMPREHENSIVE-CODE-REVIEW.md` - Comprehensive code review (most issues fixed)
- `PROJECT-CONTEXT.md` - Full project context and architecture

## Implementation Status

### ✅ Completed Features

#### Call Routes Management
- ✅ Unified Domain + Dispatcher management via `CallRouteResource`
- ✅ Auto-managed `setid` field (hidden from users)
- ✅ **Improved UX:** Radio button selection for domain type (existing/new) - always visible, no hidden options
- ✅ **Improved UX:** Autofocus on form load for better user experience
- ✅ **Improved UX:** Redirect to Destinations page after creation (filtered by domain setid)
- ✅ Existing destinations display in create form
- ✅ "Manage Destinations" action (redirects to Destinations panel filtered by setid)
- ✅ "Edit Domain" action (domain name only)
- ✅ OpenSIPS MI integration (domain_reload, dispatcher_reload)
- ✅ Multi-destination support (each domain can have multiple dispatcher entries)

#### CDR and Active Calls Panels
- ✅ Read-only CDR panel with comprehensive filters
- ✅ Active Calls monitoring (Dialog resource)
- ✅ CDR statistics widget on dashboard
- ✅ Date/time filter with validation
- ✅ Removed confusing columns (Call-ID) for better UX

#### Installer and Deployment
- ✅ Fully automated `install.sh` script
- ✅ Idempotent operations
- ✅ PHP extension detection and installation
- ✅ Nginx/PHP-FPM auto-configuration
- ✅ Non-interactive admin user creation
- ✅ Remote deployment support

### ⚠️ Known Issues

**None currently blocking** - All critical issues resolved.

**Minor Issues:**
- Alpine/Livewire console warnings (cosmetic, don't affect functionality)
- Delete action notifications for OpenSIPS MI reload failures (parked - session flash not persisting through Filament redirects)

## Release Preparation Tasks

- [ ] **Docker Setup Documentation** - Tidy up Docker MySQL setup and create comprehensive howto document (currently optional/alternative, not in use - app connects to remote MySQL at 192.168.1.58)
- [ ] **Herd + Remote Database Howto** - Create documentation for using Laravel Herd with remote MySQL database instance (current development setup)

## Technical Debt

See `COMPREHENSIVE-CODE-REVIEW.md` for detailed analysis. 

### ✅ Recently Fixed (2026-01-22)

**Code Quality:**
- ✅ **N+1 queries in CallRouteResource** - Fixed using model accessors with eager-loaded relationships
- ✅ **Direct `env()` usage** - Fixed, now uses `config()` only (production-ready)
- ✅ **Direct queries instead of relationships** - Refactored to use `$domain->dispatchers()` relationship throughout
- ✅ **Transaction scope** - Documented Filament lifecycle limitation

**UX Improvements:**
- ✅ **Create Call Route form** - Replaced hidden dropdown with explicit Radio buttons for domain type selection
- ✅ **Form autofocus** - Radio button group gets focus on page load
- ✅ **Post-create redirect** - Redirects to Destinations page filtered by domain setid (shows destinations for created route)
- ✅ **View page cleanup** - Creation fields properly hidden on ViewCallRoute page
- ✅ **Simplified form actions** - Removed "Create and create another" button

### ⏳ Parked Issues
- ⏳ **Delete action notifications** - Session flash not persisting through Filament redirects (needs different approach)

### Low Priority (Nice to Have)
- Unused page files (CreateCdr, EditCdr, CreateDialog, EditDialog)
- URL parameter handling simplification
- CreateCallRoute form logic documentation

## Key Architecture Decisions

- **CallRouteResource** uses `Domain` model as primary entity
- **setid** is auto-managed (hidden from users, auto-generated)
- **OpenSIPS MI** calls wrapped in try-catch for graceful degradation
- **Multi-table operations** use database transactions
- **Read-only data** (CDR, Dialog) - no modification actions

## Related Documentation

- `PROJECT-CONTEXT.md` - Complete project overview
- `CODE-QUALITY.md` - Code review, best practices, and recommendations
- `UX-DESIGN-DECISIONS.md` - UX design decisions and rationale
- `ARCHITECTURE.md` - System architecture and design decisions
