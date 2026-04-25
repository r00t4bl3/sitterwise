# Sitterwise Project Summary

## Overview
Sitterwise is a comprehensive Laravel-based platform for managing caregiver services, designed to connect clients with qualified caregivers for various care needs. The application provides role-based access control for administrators, caregivers, and clients, with specialized dashboards and functionality for each user type.

## Key Features

### Core Functionality
- **Caregiver Management**: Full CRUD operations for caregivers with profile management, status tracking, and specialization assignments
- **Booking System**: Complete booking lifecycle management including reservations, confirmations, and releases
- **Client Management**: Client profiles, payment methods, and booking history
- **Payment Processing**: Integration with Stripe for payment processing and payout management
- **Availability Management**: Caregiver availability scheduling and management
- **Rating System**: Client and admin rating system for caregivers
- **Search & Filtering**: Advanced search capabilities for caregivers and clients

### Technical Architecture
- **Backend**: Laravel 13 with Inertia.js for server-side rendering
- **Frontend**: React 19 with TypeScript for interactive UI components
- **Database**: MySQL with comprehensive Eloquent models and relationships
- **Authentication**: Laravel Fortify for secure authentication and user management
- **Authorization**: Role-based access control (admin, caregiver, client)
- **Payment Processing**: Stripe integration for payments and payouts
- **Testing**: Pest PHP testing framework with comprehensive test coverage

## Application Structure

### Backend Components
- **Models**: Comprehensive Eloquent models for Users, Caregivers, Clients, Bookings, Payments, etc.
- **Controllers**: RESTful controllers for each resource with proper validation
- **Services**: Service layer implementing business logic (BookingService, PaymentService, etc.)
- **Requests**: Form request validation for all user inputs
- **Resources**: API resources for consistent data serialization
- **Middleware**: Custom middleware for role-based access control
- **Migrations**: Database schema management with proper relationships

### Frontend Components
- **Pages**: Role-specific pages for admin, caregiver, and client dashboards
- **Components**: Reusable UI components built with React and TypeScript
- **Layouts**: Consistent application layouts with navigation and theming
- **Forms**: Inertia forms for seamless data submission and validation
- **State Management**: React hooks and Inertia's reactive data flow

### User Roles & Permissions

#### Administrator
- Full access to all system features
- Caregiver and client management
- Booking oversight and management
- System configuration and monitoring
- User account management

#### Caregiver
- Personal profile management
- Booking availability and status updates
- Payout management through Stripe
- Booking confirmation and release workflows

#### Client
- Personal profile and payment method management
- Booking creation and management
- Caregiver search and selection
- Payment processing

## Technical Stack
- **Framework**: Laravel 13 with Inertia.js
- **Frontend**: React 19, TypeScript, Tailwind CSS
- **Database**: MySQL with Eloquent ORM
- **Authentication**: Laravel Fortify
- **Payments**: Stripe API integration
- **Testing**: Pest PHP testing framework
- **Deployment**: Docker-ready with Laravel Sail
- **Development**: Vite development server with hot reloading

## Key Models & Relationships
- **User**: Base authentication model with role-based permissions
- **Caregiver**: Comprehensive caregiver profiles with specializations, certifications, and availability
- **Client**: Client profiles with booking history and payment methods
- **Booking**: Complex booking system with status tracking and payment integration
- **Payment**: Stripe-integrated payment processing with secure handling

## Development Features
- Code generation with Artisan commands
- Comprehensive test suite with Pest
- Code quality enforcement with Laravel Pint
- Type safety with TypeScript
- Automated builds with Vite
- Real-time logging with Laravel Pail

## API & Integration Points
- **Stripe Payment Processing**: Full integration with Stripe for payments and payouts
- **SMS/Email Notifications**: Twilio integration for communication
- **Webhooks**: Stripe webhook handling for payment events
- **Route Type Safety**: Wayfinder integration for type-safe route generation

This application provides a complete solution for managing a caregiver service platform with proper separation of concerns, security, and scalability.