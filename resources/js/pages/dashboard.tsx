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
        pastBookings?: number;
        favoriteCaregivers?: number;
        totalEarned?: number;
        completedJobs?: number;
    };
    caregiver?: {
        id: number;
        firstName: string;
        lastName: string;
        rating: number | null;
        status: { name: string };
        availabilities: Availability[];
        nextJob?: any;
        upcomingJobs?: any[];
        newInvites?: any[];
        timeSlots: any[];
    };
    client?: {
        nextBooking: any;
        upcomingBookings: any[];
        recentBookings: any[];
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
                        status: caregiver?.status?.name || 'Unknown',
                        availabilities: caregiver?.availabilities || [],
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
                        totalCaregivers: stats?.totalCaregivers || 0,
                        activeCaregivers: stats?.activeCaregivers || 0,
                        totalClients: stats?.totalClients || 0,
                        totalBookings: stats?.totalBookings || 0,
                    }}
                />
            );

        case 'super_admin':
            return (
                <SuperAdminDashboard
                    admin={admin as any}
                    stats={{
                        totalCaregivers: stats?.totalCaregivers || 0,
                        activeCaregivers: stats?.activeCaregivers || 0,
                        totalClients: stats?.totalClients || 0,
                        totalBookings: stats?.totalBookings || 0,
                    }}
                />
            );

        case 'client':
        default:
            return (
                <ClientDashboard
                    user={{ name: user.name }}
                    stats={{
                        totalBookings: stats?.activeBookings || 0,
                        completedBookings: stats?.pastBookings || 0,
                        upcomingBookings: client?.upcomingBookings || [],
                        recentBookings: client?.recentBookings || [],
                    }}
                    client={client as any}
                />
            );
    }
}
