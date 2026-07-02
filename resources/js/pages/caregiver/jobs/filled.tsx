import { Head, Link } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs = [
    { title: 'Jobs', href: '/jobs' },
    { title: 'Unavailable', href: '#' },
];

export default function JobFilled() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Job Unavailable" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-6 p-4 text-center">
                <div className="rounded-full bg-muted p-4">
                    <UserCheck className="h-16 w-16 text-muted-foreground" />
                </div>
                <h1 className="text-2xl font-bold text-foreground">
                    This job has already been filled
                </h1>
                <p className="max-w-md text-muted-foreground">
                    Another caregiver has already been assigned to this booking.
                    Thanks for your interest — check the jobs list for other
                    opportunities.
                </p>
                <Button asChild>
                    <Link href="/jobs">Back to Jobs</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
