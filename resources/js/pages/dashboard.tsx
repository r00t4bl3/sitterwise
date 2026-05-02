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
        total_caregivers?: number;
        active_caregivers?: number;
        total_clients?: number;
        active_bookings?: number;
        past_bookings?: number;
        favorite_caregivers?: number;
        total_earned?: number;
        completed_jobs?: number;
    };
    caregiver?: {
        id: number;
        first_name: string;
        last_name: string;
        rating: number | null;
        status: { name: string };
        availabilities: Availability[];
        next_job?: any;
        upcoming_jobs?: any[];
        new_invites?: any[];
    };
    client?: {
        next_booking: any;
        upcoming_bookings: any[];
        recent_bookings: any[];
    };
    admin?: {
        bookings_needing_attention: any[];
        todays_bookings: any[];
        recent_bookings: any[];
        recent_caregivers: any[];
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
                        first_name: caregiver?.first_name || user.name,
                        last_name: caregiver?.last_name || '',
                        rating: caregiver?.rating || null,
                        status: caregiver?.status?.name || 'Unknown',
                        availabilities: caregiver?.availabilities || [],
                        next_job: caregiver?.next_job,
                        upcoming_jobs: caregiver?.upcoming_jobs || [],
                        new_invites: caregiver?.new_invites || [],
                    }}
                    stats={{
                        total_earned: stats?.total_earned || 0,
                        completed_jobs: stats?.completed_jobs || 0,
                    }}
                />
            );

        case 'admin':
            return (
                <AdminDashboard
                    admin={{
                        bookings_needing_attention:
                            admin?.bookings_needing_attention || [],
                        todays_bookings: admin?.todays_bookings || [],
                        recent_bookings: admin?.recent_bookings || [],
                        recent_caregivers: admin?.recent_caregivers || [],
                    }}
                    stats={{
                        total_caregivers: stats?.total_caregivers || 0,
                        active_caregivers: stats?.active_caregivers || 0,
                        total_clients: stats?.total_clients || 0,
                    }}
                />
            );

        case 'super_admin':
            return (
                <SuperAdminDashboard
                    stats={{
                        total_caregivers: stats?.total_caregivers || 0,
                        active_caregivers: stats?.active_caregivers || 0,
                        total_clients: stats?.total_clients || 0,
                    }}
                />
            );

        case 'client':
        default:
            return (
                <ClientDashboard
                    user={{ name: user.name }}
                    stats={{
                        total_bookings: stats?.active_bookings || 0,
                        completed_bookings: stats?.past_bookings || 0,
                        upcoming_bookings: client?.upcoming_bookings || [],
                        recent_bookings: client?.recent_bookings || [],
                    }}
                    client={client as any}
                />
            );
    }
}
