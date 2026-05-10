import { Head, Link, useForm } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    User as UserIcon,
    DollarSign,
    Briefcase,
    MapPin,
    Users,
    ChevronRight,
    Star,
    Bell,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { AvailabilityCalendar } from '@/components/availability-calendar';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    client?: {
        user: {
            name: string;
        };
    };
    children?: any[];
    address_city?: string;
    address_state?: string;
    hotel?: {
        name: string;
    };
}

interface CaregiverDashboardProps {
    caregiver: {
        id: number;
        firstName: string;
        lastName: string;
        rating: number | null;
        status: string;
        availabilities: Availability[];
        bookingStatuses: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
        nextJob?: Booking | null;
        upcomingJobs?: Booking[];
        newInvites?: Booking[];
        timeSlots: Array<{ value: string; label: string }>;
    };
    stats: {
        totalEarned: number;
        completedJobs: number;
    };
}

export default function CaregiverDashboard({
    caregiver,
    stats,
}: CaregiverDashboardProps) {
    const initialAvailabilities = caregiver.availabilities;
    const timeSlots = caregiver.timeSlots;
    const [availabilities, setAvailabilities] = useState(initialAvailabilities);

    useEffect(() => {
        setAvailabilities(initialAvailabilities);
    }, [initialAvailabilities]);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const {
        data,
        setData,
        patch,
        delete: deleteForm,
    } = useForm({
        date: '',
        time_slots: [] as string[],
        specific_time: '',
    });

    const openSheet = (date: string) => {
        const availabilityMap = availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;

                return acc;
            },
            {} as Record<string, Availability>,
        );
        const existing = availabilityMap[date];
        setSelectedDate(date);
        setData({
            date: date,
            time_slots: existing?.time_slots || [],
            specific_time: existing?.specific_time || '',
        });
        setIsSheetOpen(true);
    };

    const handleSave = () => {
        setProcessing(true);
        patch(`/availabilities/${caregiver.id}`, {
            onSuccess: () => {
                setIsSheetOpen(false);
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const handleDelete = () => {
        if (!selectedDate) {
            return;
        }

        setProcessing(true);
        const map = availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;

                return acc;
            },
            {} as Record<string, Availability>,
        );
        const existing = map[selectedDate];

        if (!existing || existing.id === undefined) {
            setIsSheetOpen(false);
            setProcessing(false);

            return;
        }

        deleteForm(`/availabilities/${existing.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSheetOpen(false);
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const handleTimeSlotChange = (slot: string, checked: boolean) => {
        if (checked) {
            if (!data.time_slots.includes(slot)) {
                setData('time_slots', [...data.time_slots, slot]);
            }
        } else {
            setData(
                'time_slots',
                data.time_slots.filter((s) => s !== slot),
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregiver Dashboard" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold text-foreground">
                        Welcome back, {caregiver.firstName}!
                    </h1>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="px-2 py-0.5">
                            <span className="mr-1.5 h-2 w-2 rounded-full bg-green-500"></span>
                            {caregiver.status}
                        </Badge>
                        <p className="text-sm text-muted-foreground">
                            You have {caregiver.newInvites?.length || 0} new job
                            invites
                        </p>
                    </div>
                </div>

                {/* Stats Bar */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Star className="h-4 w-4 text-yellow-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Current Rating
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {caregiver.rating
                                ? `${caregiver.rating} ★`
                                : 'No rating'}
                        </p>
                    </div>
                    <Link
                        href="/payouts"
                        className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md"
                    >

                        <div className="flex items-center gap-2 text-muted-foreground">
                            <DollarSign className="h-4 w-4 text-green-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Total Earned
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            ${stats.totalEarned.toFixed(2)}
                        </p>
                    </Link>
                    <Link
                        href="/jobs"
                        className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Briefcase className="h-4 w-4 text-blue-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Jobs Completed
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.completedJobs}
                        </p>
                    </Link>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Primary Focus: Next Appointment */}
                    <div className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold text-foreground">
                            Your Next Appointment
                        </h2>

                        {caregiver.nextJob ? (
                            <div className="relative overflow-hidden rounded-xl border-2 border-primary/20 bg-card p-6 shadow-md transition-all hover:border-primary/40">
                                <div className="absolute top-0 right-0 p-4">
                                    <StatusBadge
                                        status={caregiver.nextJob.status}
                                        bookingStatuses={
                                            caregiver.bookingStatuses
                                        }
                                    />
                                </div>

                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Clock className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-medium tracking-tight text-muted-foreground uppercase">
                                            {caregiver.nextJob.service_type} Job
                                        </h3>
                                        <p className="text-lg font-bold">
                                            {formatDisplayDateTime(
                                                caregiver.nextJob
                                                    .start_datetime,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="mb-6 grid gap-3">
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <UserIcon className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">
                                            Client:{' '}
                                            {caregiver.nextJob.client?.user
                                                .name || 'N/A'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                        <span>
                                            {caregiver.nextJob.hotel?.name ||
                                                `${caregiver.nextJob.address_city}, ${caregiver.nextJob.address_state}`}
                                        </span>
                                    </div>
                                    {caregiver.nextJob.children && (
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                {caregiver.nextJob.children
                                                    .length || 0}{' '}
                                                Children
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <Button asChild className="w-full">
                                    <Link
                                        href={`/jobs/${caregiver.nextJob.ulid}`}
                                    >
                                        View Job Details
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card p-8 text-center">
                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                    <Calendar className="h-8 w-8 text-muted-foreground" />
                                </div>
                                <h3 className="mb-2 text-lg font-medium">
                                    No appointments scheduled
                                </h3>
                                <p className="mb-6 text-sm text-muted-foreground">
                                    Your next confirmed job will appear here.
                                    Make sure your availability is up to date!
                                </p>
                            </div>
                        )}

                        {/* Job Opportunities / Invites */}
                        <div className="mt-2 flex flex-col gap-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold text-foreground">
                                <Bell className="h-5 w-5 text-primary" />
                                Job Opportunities
                            </h2>
                            <div className="space-y-3">
                                {caregiver.newInvites &&
                                caregiver.newInvites.length > 0 ? (
                                    caregiver.newInvites.map((invite) => (
                                        <Link
                                            key={invite.id}
                                            href={`/bookings/${invite.ulid}`}
                                            className="flex items-center justify-between rounded-lg border border-border bg-card p-4 transition-all hover:border-primary/50 hover:bg-accent/30"
                                        >
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px]"
                                                    >
                                                        NEW
                                                    </Badge>
                                                    <span className="text-sm font-bold text-primary uppercase">
                                                        {invite.service_type}
                                                    </span>
                                                </div>
                                                <p className="text-sm font-medium">
                                                    {formatDisplayDateTime(
                                                        invite.start_datetime,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {invite.address_city},{' '}
                                                    {invite.address_state}
                                                </p>
                                            </div>
                                            <ChevronRight className="h-5 w-5 text-muted-foreground" />
                                        </Link>
                                    ))
                                ) : (
                                    <div className="rounded-lg border border-border bg-card p-6 text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No new job invites at the moment.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Secondary Side: Schedule & Availability */}
                    <div className="flex flex-col gap-6">
                        {/* More Upcoming Jobs */}
                        {caregiver.upcomingJobs &&
                            caregiver.upcomingJobs.length > 0 && (
                                <div>
                                    <div className="mb-3 flex items-center justify-between">
                                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                            More Upcoming Jobs
                                        </h2>
                                        <Link
                                            href="/jobs"
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            Full Schedule
                                        </Link>
                                    </div>
                                    <div className="space-y-3">
                                        {caregiver.upcomingJobs.map((job) => (
                                            <Link
                                                key={job.id}
                                                href={`/jobs/${job.ulid}`}
                                                className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/5">
                                                        <Briefcase className="h-4 w-4 text-primary" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {formatDisplayDateTime(
                                                                job.start_datetime,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {job.client?.user
                                                                .name || 'N/A'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            )}

                        {/* Availability Calendar */}
                        <div className="flex flex-col gap-3">
                            <h2 className="text-lg font-semibold text-foreground">
                                My Availability
                            </h2>
                            <div className="overflow-hidden rounded-xl border border-border bg-card p-4">
                                <AvailabilityCalendar
                                    availabilities={availabilities}
                                    onDateClick={openSheet}
                                    timeSlots={timeSlots}
                                />
                                <p className="mt-4 text-center text-xs text-muted-foreground">
                                    Click on a date to set or update your
                                    availability.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                <SheetContent side="right" className="w-full sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            {selectedDate
                                ? formatDisplayDate(selectedDate)
                                : 'Availability'}{' '}
                        </SheetTitle>
                        <SheetDescription>
                            Set availability for the selected date.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="space-y-4 px-4 pt-6">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Time Slots
                            </label>
                            <div className="mt-2 space-y-2">
                                {timeSlots.map((slot) => (
                                    <div
                                        key={slot.value}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`slot-${slot.value}`}
                                            checked={data.time_slots.includes(
                                                slot.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                handleTimeSlotChange(
                                                    slot.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`slot-${slot.value}`}
                                            className="text-sm font-normal"
                                        >
                                            {slot.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Specific Time
                            </label>
                            <Input
                                type="text"
                                value={data.specific_time}
                                onChange={(e) =>
                                    setData('specific_time', e.target.value)
                                }
                                placeholder="e.g., after 9am"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm text-foreground"
                            />
                        </div>

                        <div className="flex gap-2 pt-4">
                            <Button
                                onClick={handleSave}
                                disabled={
                                    processing || data.time_slots.length === 0
                                }
                                className="flex-1"
                            >
                                {processing && <Spinner className="size-4" />}
                                {processing ? 'Saving...' : 'Save'}
                            </Button>
                            {(() => {
                                const map = availabilities.reduce(
                                    (acc, av) => {
                                        acc[av.date] = av;

                                        return acc;
                                    },
                                    {} as Record<string, Availability>,
                                );

                                return (
                                    map[selectedDate || ''] && (
                                        <Dialog
                                            open={isDeleteDialogOpen}
                                            onOpenChange={setIsDeleteDialogOpen}
                                        >
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="secondary"
                                                    disabled={processing}
                                                    className="w-1/4"
                                                >
                                                    Delete
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        Confirm Delete
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want to
                                                        delete your availability
                                                        for{' '}
                                                        {selectedDate
                                                            ? formatDisplayDate(
                                                                  selectedDate,
                                                              )
                                                            : 'this date'}
                                                        ? This action cannot be
                                                        undone.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter>
                                                    <DialogClose asChild>
                                                        <Button variant="outline">
                                                            Cancel
                                                        </Button>
                                                    </DialogClose>
                                                    <Button
                                                        variant="secondary"
                                                        onClick={() => {
                                                            handleDelete();
                                                            setIsDeleteDialogOpen(
                                                                false,
                                                            );
                                                        }}
                                                        disabled={processing}
                                                    >
                                                        {processing && (
                                                            <Spinner className="size-4" />
                                                        )}
                                                        {processing
                                                            ? 'Deleting...'
                                                            : 'Delete'}
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    )
                                );
                            })()}
                        </div>

                        <Button
                            variant="secondary"
                            onClick={() => setIsSheetOpen(false)}
                            className="mt-2 w-full"
                        >
                            Cancel
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
