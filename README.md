# Sitterwise

A **caregiver services marketplace platform** connecting clients with caregivers for childcare, pet-sitting, and companion care. Built with Laravel 13 + React 19 (Inertia SPA).

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Laravel 13, MySQL/SQLite |
| Frontend | React 19, TypeScript, Tailwind CSS v4 |
| Framework | Inertia.js v2 (SPA + SSR) |
| Auth | Laravel Fortify (headless) |
| Payments | Stripe (PaymentIntents, Connect) |
| Build | Vite 8, Laravel Wayfinder |
| Testing | Pest PHP v4 |

## User Roles

| Role | Description |
|------|-------------|
| **Admin** | Full system management — caregivers, clients, bookings, payments |
| **Super Admin** | Extended access — master data (hotels, pricing rules, attributes) |
| **Caregiver** | Manage availability, receive booking invites, process payouts |
| **Client** | Create bookings, manage payment methods, rate caregivers |
| **Guest** | Book without account (hotel guests via embedded checkout) |

## Key Features

### Booking Lifecycle
`received` → `reserved` → `confirmed` → `completed` → `paid`

Three entry points: **guest** (unauthenticated with Stripe checkout), **client** (logged-in with saved profile), **admin** (full control). Pricing auto-calculates from configurable `PricingRule` entries based on service type, children count, and pets.

### Caregiver Application
Public multi-step wizard with email OTP verification. Collects personal info, experience, education, references, certifications. Generates PDF agreements on submission.

### Service Types
Babysitter, Petsitter, Companion Care, Group Childcare (Invoiced), Corporate (Invoiced), Comped

### Client Types
Resident (San Diego), Vacationer (hotel guests), Invoiced

### Payments
- **Stripe PaymentIntents** for client charges (off-session with auto-retry)
- **Stripe Connect** for caregiver payouts
- Webhook-driven status updates

### Notifications
Event-driven architecture with email (SendGrid), SMS (Twilio), and database notifications sent on booking creation, acceptance, invitations, receipts, and reminders.

## Architecture

### Design Patterns

- **Strategy Pattern** — `BookingServiceFactory` resolves role-specific services (`AdminBookingService`, `CaregiverBookingService`, `ClientBookingService`, `GuestBookingService`)
- **Event-Driven** — Booking lifecycle events decouple business logic from notifications (8 events, 6 listeners)
- **Service Layer** — Business logic encapsulated in service classes, controllers are thin
- **EAV** — Dynamic attributes via `AttributeDefinition` + polymorphic pivot
- **Snapshot Pattern** — Bookings snapshot client/children/pets at creation for historical accuracy
- **Atomic Reservation** — Optimistic locking prevents race conditions on caregiver booking claims

### Models (30)

Core: `User`, `Caregiver`, `Client`, `Booking`, `BookingGroup`, `BookingRating`

Supporting: `CaregiverStatus`, `SpecialtyType`, `CertificationType`, `Location`, `Hotel`, `Availability`, `PricingRule`, `AttributeDefinition`, `ClientChild`, `ClientPet`, `ClientAddress`, `ClientPaymentMethod`, `CaregiverPayout`, `BookingCaregiverNotification`, `QuickLink`

### Frontend Structure

```
resources/js/
  pages/
    admin/          — Admin dashboard, bookings, caregivers, clients, transactions
    caregiver/      — Available bookings, jobs, payouts
    client/         — Bookings, payments, reviews
    superadmin/     — Master data management
    auth/           — Login, register, password reset, 2FA
    guest/          — Guest booking flow
    public/         — Caregiver bio, application wizard
    settings/       — Profile, security, appearance
  components/
    ui/             — Radix UI primitives (shadcn-style)
    stripe/         — Stripe Elements wrapper
  layouts/          — App, auth, guest, settings layouts
```

## Getting Started

### Prerequisites
- PHP 8.4+
- Composer
- Node.js 20+
- SQLite or MySQL

### Installation

```bash
git clone <repository>
cd sitterwise

cp .env.example .env
php artisan key:generate

composer install
npm install

php artisan migrate --seed
npm run build
```

### Development

```bash
composer run dev
# or separately:
npm run dev
php artisan serve
```

## Testing

```bash
php artisan test --compact
php artisan test --compact --filter=testName
vendor/bin/pint
```

## Directory Overview

```
app/
  Enums/           — 11 PHP enums (BookingStatus, ServiceType, etc.)
  Http/
    Controllers/   — 25 controllers
    Middleware/     — 7 middleware classes (role gates)
    Requests/      — 31 Form Request validation classes
  Models/          — 30 Eloquent models
  Services/        — Booking, Billing, Payments, Webhooks
  Events/          — Booking lifecycle events (8)
  Listeners/       — Notification handlers (6)
  Mail/            — Mailable classes (8)
  Notifications/   — Notification classes (7)
database/
  factories/       — Model factories
  migrations/      — 46 migration files
  seeders/         — Database seeders
tests/
  Feature/         — Auth, Admin, Client, Caregiver, Guest, Settings
  Unit/            — Models, Enums, Middleware, Requests, Resources, Policies
  Arch/            — Architecture conventions
```
