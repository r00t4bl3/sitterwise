import { usePage } from '@inertiajs/react';
import AdminDashboard from './dashboard/admin';
import CaregiverDashboard from './dashboard/caregiver';
import ClientDashboard from './dashboard/client';
import SuperAdminDashboard from './dashboard/superadmin';

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface Props {
    [key: string]: unknown;
    user: {
        role: string;
        name: string;
    };
    stats?: {
        totalCaregivers?: number;
        activeCaregivers?: number;
        totalClients?: number;
        activeBookings?: number;
        totalBookings?: number;
        completedBookings?: number;
        pastBookings?: number;
        favoriteCaregivers?: number;
        totalEarned?: number;
        completedJobs?: number;
        thisMonthCompleted?: number;
        thisMonthUpcoming?: number;
        ytdCompleted?: number;
        ytdUpcoming?: number;
        ytdPercentChange?: number | null;
        ytdLastYearLabel?: string;
        troubledUnassigned?: number;
        troubledMissingPayment?: number;
        troubledAwaitingCheckout?: number;
    };
    caregiver?: {
        id: number;
        firstName: string;
        lastName: string;
        rating: number | null;
        status: string | null;
        availabilities: Availability[];
        bookingStatuses: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
        nextJob?: any;
        upcomingJobs?: any[];
        newInvites?: any[];
        timeSlots: any[];
    };
    client?: {
        nextBooking: any;
        upcomingBookings: any[];
        recentBookings: any[];
        bookingStatuses?: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
    };
    admin?: {
        bookingsNeedingAttention: any[];
        todaysBookings: any[];
        recentBookings: any[];
        recentCaregivers: any[];
        bookingStatuses: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
        clients: any[];
        clientTypes: any[];
        hotels: any[];
        caregivers: any[];
        serviceTypes: any[];
        locationTypes: any[];
        paymentStatuses: any[];
        petTypes: any[];
        bookingAttributes: any[];
        sitterPreferences: any[];
        quickLinks: any[];
    };
}

export default function Dashboard() {
    const { user, stats, caregiver, client, admin } = usePage<Props>().props;

    switch (user.role) {
        case 'caregiver':
            return (
                <CaregiverDashboard
                    caregiver={{
                        id: caregiver?.id || 0,
                        firstName: caregiver?.firstName || user.name,
                        lastName: caregiver?.lastName || '',
                        rating: caregiver?.rating || null,
                        status: caregiver?.status || 'Unknown',
                        availabilities: caregiver?.availabilities || [],
                        bookingStatuses: caregiver?.bookingStatuses || [],
                        nextJob: caregiver?.nextJob,
                        upcomingJobs: caregiver?.upcomingJobs || [],
                        newInvites: caregiver?.newInvites || [],
                        timeSlots: caregiver?.timeSlots || [],
                    }}
                    stats={{
                        totalEarned: stats?.totalEarned || 0,
                        completedJobs: stats?.completedJobs || 0,
                    }}
                />
            );

        case 'admin':
            return (
                <AdminDashboard
                    admin={admin as any}
                    stats={{
                        totalCaregivers: stats?.totalCaregivers ?? 0,
                        activeCaregivers: stats?.activeCaregivers ?? 0,
                        totalClients: stats?.totalClients ?? 0,
                        totalBookings: stats?.totalBookings ?? 0,
                        thisMonthCompleted: stats?.thisMonthCompleted ?? 0,
                        thisMonthUpcoming: stats?.thisMonthUpcoming ?? 0,
                        ytdCompleted: stats?.ytdCompleted ?? 0,
                        ytdUpcoming: stats?.ytdUpcoming ?? 0,
                        ytdPercentChange: stats?.ytdPercentChange ?? null,
                        ytdLastYearLabel: stats?.ytdLastYearLabel ?? '',
                        troubledUnassigned: stats?.troubledUnassigned ?? 0,
                        troubledMissingPayment:
                            stats?.troubledMissingPayment ?? 0,
                        troubledAwaitingCheckout:
                            stats?.troubledAwaitingCheckout ?? 0,
                    }}
                />
            );

        case 'super_admin':
            return (
                <SuperAdminDashboard
                    admin={admin as any}
                    stats={{
                        totalCaregivers: stats?.totalCaregivers ?? 0,
                        activeCaregivers: stats?.activeCaregivers ?? 0,
                        totalClients: stats?.totalClients ?? 0,
                        totalBookings: stats?.totalBookings ?? 0,
                        thisMonthCompleted: stats?.thisMonthCompleted ?? 0,
                        thisMonthUpcoming: stats?.thisMonthUpcoming ?? 0,
                        ytdCompleted: stats?.ytdCompleted ?? 0,
                        ytdUpcoming: stats?.ytdUpcoming ?? 0,
                        ytdPercentChange: stats?.ytdPercentChange ?? null,
                        ytdLastYearLabel: stats?.ytdLastYearLabel ?? '',
                        troubledUnassigned: stats?.troubledUnassigned ?? 0,
                        troubledMissingPayment:
                            stats?.troubledMissingPayment ?? 0,
                        troubledAwaitingCheckout:
                            stats?.troubledAwaitingCheckout ?? 0,
                    }}
                />
            );

        case 'client':
        default:
            return (
                <ClientDashboard
                    stats={{
                        totalBookings: stats?.totalBookings ?? 0,
                        completedBookings: stats?.completedBookings ?? 0,
                        upcomingBookings: client?.upcomingBookings || [],
                        recentBookings: client?.recentBookings || [],
                    }}
                    client={client as any}
                    bookingStatuses={client?.bookingStatuses || []}
                />
            );
    }
}
