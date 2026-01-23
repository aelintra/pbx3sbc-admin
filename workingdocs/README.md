# Working Documentation

This folder contains project documentation for the PBX3SBC Admin Panel. Documents are organized by purpose.

## Quick Start

**New to the project?** Start here:
1. `PROJECT-CONTEXT.md` - Complete project overview and architecture
2. `CURRENT-STATE.md` - Current implementation status

## Document Categories

### Core Documentation
- **PROJECT-CONTEXT.md** - Main onboarding document. Read this first.
- **CURRENT-STATE.md** - Current implementation status, completed features, known issues
- **CODE-REVIEW-FINDINGS.md** - Technical debt analysis and code improvements needed

### Architecture & Design
- **TWO-REPO-STRATEGY.md** - Why we have separate repositories (pbx3sbc vs pbx3sbc-admin)
- **ADMIN-PANEL-DESIGN.md** - High-level architecture and design decisions
- **ROUTE-UX-IMPROVEMENTS.md** - Call Routes UX design decisions and rationale
- **CALL-ROUTE-MULTI-DESTINATION-OPTIONS.md** - Multi-destination handling design options

### Implementation Guides
- **LARAVEL-IMPLEMENTATION-GUIDE.md** - Laravel/Filament implementation patterns and examples
- **FILAMENT-MULTI-TABLE-OPERATIONS.md** - How to handle multi-table operations in Filament
- **ADMIN-PANEL-EXTENSIBILITY-GUIDE.md** - How to add new features/resources to the admin panel

### Feature Specifications
- **CDR-FRONTEND-SPEC.md** - Detailed specification for CDR (Call Detail Records) panel

### Deployment & Setup
- **INSTALLATION-LOG.md** - Step-by-step installation commands and process
- **REMOTE-DEPLOYMENT-GUIDE.md** - Guide for deploying admin panel on separate server
- **DEVELOPMENT-STACK-RECOMMENDATIONS.md** - Development environment setup recommendations

### Authentication & Authorization
- **AUTHENTICATION-AUTHORIZATION-CLARIFICATION.md** - Explanation of auth system (Laravel sessions vs Sanctum)
- **FILAMENT-ROLES-PERMISSIONS-GUIDE.md** - How to implement RBAC with Filament Shield

### Requirements & Assessment
- **LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md** - Assessment of S3/Minio, Service Management, Remote APIs support
- **FRONTEND-OPTIONS-DETAILED-ANALYSIS.md** - Detailed comparison of frontend technology options
- **ADMIN-PANEL-PLANNING-APPROACH.md** - Planning methodology and task breakdown (some sections may be outdated)

### Historical (Reference Only)
- **SESSION-SUMMARY.md** - Historical development session summary (January 2026). Mostly for reference.

## Document Maintenance

- **Keep documents up to date** - Update when features are added or changed
- **Remove redundancy** - Don't duplicate information across documents
- **Cross-reference** - Use links to related documents instead of duplicating content
- **Archive outdated** - Move truly outdated documents to an archive or remove them

## Last Updated

2026-01-22 - Consolidated and organized documentation structure
