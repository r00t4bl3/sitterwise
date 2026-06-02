# Booking Flow

## Status Lifecycle

```mermaid
stateDiagram-v2
    [*] --> received : Booking created
    received --> reserved : Caregiver reserves (60s TTL)
    reserved --> received : TTL expires / Caregiver releases
    reserved --> confirmed : Caregiver confirms within TTL
    confirmed --> completed : Caregiver checks out
    completed --> paid : Admin processes payment
    received --> cancelled : Cancel
    reserved --> cancelled : Cancel
    confirmed --> cancelled : Cancel
    paid --> [*]
    cancelled --> [*]
```

## Creation Channels

```mermaid
flowchart TD
    Entry([Booking Request]) --> G1
    Entry --> C1
    Entry --> A1

    subgraph Guest["Guest Booking"]
        direction TB
        G1[Guest submits form] --> G2[Validate & store in session]
        G2 --> G3[Stripe SetupIntent - card on file]
        G3 --> G4{New user?}
        G4 -->|Yes| G5[Create User + Client<br/>temp password]
        G4 -->|No| G6[Find existing User]
        G5 --> G7[Create BookingGroup<br/>submission_type = guest]
        G6 --> G7
        G7 --> G8[Create Booking/s<br/>status = received]
        G8 --> G9[Fire GuestAccountSetup<br/>send password setup email]
    end

    subgraph Client["Client Booking"]
        direction TB
        C1[Client submits form] --> C2[Load existing Client profile]
        C2 --> C3[Create BookingGroup<br/>submission_type = client]
        C3 --> C4[Create Booking/s<br/>status = received]
    end

    subgraph Admin["Admin Booking"]
        direction TB
        A1[Admin submits form] --> A2{New client?}
        A2 -->|Yes| A3[Create User + Client inline]
        A2 -->|No| A4[Use existing Client]
        A3 --> A5[Create BookingGroup]
        A4 --> A5
        A5 --> A6{Assign caregiver?}
        A6 -->|Yes| A7[Set caregiver_id directly<br/>Fire BookingAccepted]
        A6 -->|No| A8[Leave unassigned]
        A7 --> A9[Create Booking/s<br/>admin sets status]
        A8 --> A9
    end

    G9 --> Received([status = received])
    C4 --> Received
    A9 --> Received
```

## Caregiver Assignment

```mermaid
sequenceDiagram
    participant Admin
    participant System
    participant Caregiver

    Admin->>System: Select caregivers & notify
    System->>System: Create BookingCaregiverNotification records
    System->>Caregiver: Send email/SMS invitation

    Caregiver->>System: Click "Reserve"
    System->>System: Atomic DB update<br/>reserved_by = caregiver<br/>reservation_expires_at = now + 60s<br/>status = reserved
    System-->>Caregiver: Broadcast JobReserved

    alt Caregiver confirms within 60s
        Caregiver->>System: Click "Confirm"
        System->>System: Atomic DB update<br/>caregiver_id, confirmed_by, confirmed_at<br/>status = confirmed
        System->>System: Create CaregiverAssignment
        System-->>Caregiver: Fire BookingAccepted (confirmation email)
    else TTL expires or caregiver releases
        System->>System: Revert to status = received<br/>Clear reserved_by
    end

    Note over Caregiver,System: Post-confirmation: caregiver can back out

    opt Caregiver backs out of confirmed booking
        Caregiver->>System: POST assignments/{id}/back-out<br/>with reason
        System->>System: CaregiverAssignment<br/>resolution = backed_out
        System->>Admin: Email notification<br/>(AdminCaregiverBackedOutMail)
        Note over System: Booking status unchanged<br/>Admin handles reassignment manually
    end
```

## Post-Confirmation Lifecycle

```mermaid
flowchart TD
    subgraph Checkout["Caregiver Checkout"]
        direction TB
        CO1[Caregiver submits checkout] --> CO2[Update actual times<br/>reimbursement, bonus]
        CO2 --> CO3[status = completed<br/>checkout_at set]
    end

    subgraph Payment["Admin Payment"]
        direction TB
        P1[Admin reviews & adjusts] --> P2[Process Payment]
        P2 --> P3[Stripe PaymentIntent<br/>off-session charge]
        P3 --> P4{Success?}
        P4 -->|Yes| P5[status = paid<br/>payment_status = charged<br/>Send receipt email]
        P4 -->|No| P6[Increment charge_attempt_count<br/>payment_status = failed]
        P6 --> P7[Auto-retry queue<br/>0s → 1h → 1d → 3d<br/>max 4 attempts]
    end

    subgraph Rating["Rating"]
        direction TB
        R1[Client rates caregiver] --> R2[Update aggregate rating]
        R3[Caregiver rates client] --> R4[Update aggregate rating]
    end

    subgraph Cancellation["Admin Cancellation"]
        direction TB
        CA1[Admin sets status = cancelled] --> CA2[Zero all financial amounts]
    end

    subgraph BackOut["Caregiver Back-Out"]
        direction TB
        BO1[Caregiver submits back-out<br/>with reason] --> BO2[CaregiverAssignment<br/>resolved = backed_out]
        BO2 --> BO3[Admin notified via email]
        BO3 --> BO4[Booking status unchanged<br/>Admin handles manually]
    end

    CO3 --> P1
    P5 --> R1
    P5 --> R3
    CO3 --> BO1
```

## Financial Model

```mermaid
flowchart TD
    PR[PricingRule<br/>service_type + number_of_children] -->|lookup| HR[Hourly Rates]
    HR --> CTH[charge_to_client_hourly]
    HR --> PTG[paid_to_caregiver_hourly]
    HR --> SWC[sitterwise_cut_hourly]

    CTH -->|× total_working_hour| CC[charge_to_client]
    PTG -->|× total_working_hour| PG[paid_to_caregiver]
    SWC -->|× total_working_hour| SC[sitterwise_cut]

    CC --> TSA[total_service_amount<br/>= charge_to_client<br/>+ reimbursement + bonus]
    TSA --> TA[total_amount<br/>= total_service_amount + tip]

    TA -->|Stripe charge| SP[Service Payment]
    tip[Tip] -->|Separate Stripe charge| TP[Tip Payment]
```

## Data Model

```mermaid
erDiagram
    BookingGroup ||--o{ Booking : has
    BookingGroup {
        int client_id FK
        string service_type
        string location_type
        string submission_type
        json children
        json pets
        string address_line1
        int hotel_id FK
        boolean requires_payment
    }

    Booking {
        int booking_group_id FK
        int caregiver_id FK
        datetime start_datetime
        datetime end_datetime
        string status
        string payment_status
        decimal charge_to_client
        decimal paid_to_caregiver
        decimal sitterwise_cut
        decimal total_amount
        int reserved_by FK
        datetime reservation_expires_at
    }

    Booking ||--o{ BookingCaregiverNotification : invitations
    Booking ||--o| CaregiverAssignment : assignment
    Booking ||--o{ ClientPayment : payments
    Booking ||--o{ BookingRating : ratings
```
