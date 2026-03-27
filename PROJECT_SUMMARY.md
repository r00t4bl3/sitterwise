# Sitterwise Project Summary

## Overview

Build a Sitterwise Laravel application with comprehensive caregiver management features including CRUD operations, authorization, search functionality, and password reset.

## Requirements

- User wants profile photo to upload immediately when selected (separate from main form data)
- User wants to use Inertia's `useForm` for CSRF handling
- User explicitly asked to IGNORE `resources/js/pages/welcome.tsx`
- User wants flat controller structure (not namespaced)
- User wants routes without `/admin/` prefix
- User wants UI to match design mockup with semantic CSS classes
- Admin-only routes for CRUD operations (create, store, edit, update, destroy, profile-photo, password)
- Regular authenticated users can only access: index, show, search-suggestions

## Key Discoveries

1. **Inertia's `useForm`** automatically handles CSRF tokens - no manual token extraction needed
2. **File uploads with Inertia**: using `post()` with `_method: 'patch'` spoofing is preferred
3. **Inertia responses**: Inertia expects Inertia responses, NOT plain JSON - returning `response()->json()` causes errors
4. **Route::resource quirk**: Custom routes need to be placed BEFORE `Route::resource` to take precedence
5. **Sidebar highlighting**: NavMain uses `useCurrentUrl` hook - changed from `isCurrentUrl` to `isCurrentOrParentUrl` to highlight parent for child routes (`/caregivers/23`, `/caregivers/23/edit`)
6. **Toast duplication**: Flash messages persist in page props - added useRef tracking to prevent duplicate toasts
7. **Role enum**: User roles are 'admin', 'client', 'caregiver' (not 'user')

## Accomplishments

### Core Features

- ✅ **Caregiver CRUD** - Create, Read, Update, Delete functionality
- ✅ **Profile photo upload** - Separate endpoint and UI
- ✅ **Live search with suggestions** - Debounced, min 2 characters
- ✅ **Flash message component** - `toaster-message.tsx` with useRef to prevent duplicates
- ✅ **Password reset** - Sheet component on caregiver show page
- ✅ **Authorization middleware** - `EnsureUserIsAdmin` middleware
- ✅ **Route organization** - Used `Route::resource` with custom routes before it
- ✅ **Sidebar highlighting** - Changed to `isCurrentOrParentUrl`
- ✅ **Tests** - 28 tests covering CRUD + authorization
- ✅ **UI fixes** - Specialties display, "Manage" wording removed, button centering

## Project Structure

### Backend Files

- `app/Http/Controllers/CaregiverController.php` - Main CRUD controller
- `app/Http/Middleware/EnsureUserIsAdmin.php` - Admin authorization middleware
- `app/Http/Requests/StoreCaregiverRequest.php` - Form validation for create
- `app/Http/Requests/UpdateCaregiverRequest.php` - Form validation for update
- `app/Models/Caregiver.php` - Caregiver model
- `routes/web.php` - Route definitions with resource + custom routes
- `database/migrations/` - Database migrations for caregivers table

### Frontend Files

- `resources/js/pages/caregivers/index.tsx` - Caregiver list with live search
- `resources/js/pages/caregivers/show.tsx` - Caregiver detail with password reset
- `resources/js/pages/caregivers/edit.tsx` - Caregiver edit form
- `resources/js/pages/caregivers/create.tsx` - Caregiver create form
- `resources/js/pages/caregivers/CaregiverForm.tsx` - Reusable form component
- `resources/js/pages/caregivers/CaregiverSearch.tsx` - Search suggestions component
- `resources/js/components/app-sidebar.tsx` - Navigation sidebar
- `resources/js/components/nav-main.tsx` - Nav menu with active highlighting
- `resources/js/components/toaster-message.tsx` - Flash message toast component
- `resources/js/components/flash-message.tsx` - Alternative flash component
- `resources/js/hooks/use-current-url.ts` - URL matching hook
- `resources/js/components/ui/avatar.tsx` - Avatar component with photo support
- `resources/js/components/ui/sheet.tsx` - Sheet/drawer component
- `resources/js/components/ui/button.tsx` - Button component
- `resources/js/components/ui/input.tsx` - Input component
- `resources/js/components/ui/label.tsx` - Label component
- `resources/js/components/ui/textarea.tsx` - Textarea component

### Test Files

- `tests/Feature/CaregiverTest.php` - Comprehensive tests (28 tests)

## Test Summary

**28 tests covering:**

- Caregiver CRUD operations
- Authorization (admin vs regular users)
- Search functionality
- Profile photo upload
- Password reset
- Flash messages and redirects
- Validation errors

## Route Structure

```
GET    /caregivers              (index - all users)
GET    /caregivers/search-suggestions (search - all users)
GET    /caregivers/create       (create - admin only)
POST   /caregivers              (store - admin only)
GET    /caregivers/{caregiver}  (show - all users)
GET    /caregivers/{caregiver}/edit (edit - admin only)
PUT    /caregivers/{caregiver}  (update - admin only)
DELETE /caregivers/{caregiver}  (destroy - admin only)
POST   /caregivers/{caregiver}/profile-photo (photo - admin only)
POST   /caregivers/{caregiver}/password (password reset - admin only)
```

## Technical Notes

- Uses Laravel 13, Inertia v2, React 19
- Tailwind CSS for styling
- Pest for testing
- Wayfinder for route type-safety
- Laravel Fortify for authentication
