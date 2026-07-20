import { Link, Head, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    Copy,
    ExternalLink,
    MapPin,
    Star,
    User,
    Phone,
    Mail,
    Heart,
    ArrowLeft,
    Building,
    Home,
    Building2,
    PartyPopper,
    Split,
    UserPlus,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { FeesBreakdown } from '@/components/fees-breakdown';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
import { BookingSheet } from './booking-sheet';
import { NotifyCaregiversSheet } from './notify-caregivers-sheet';
import { ReplaceCaregiverSheet } from './replace-caregiver-sheet';
import { useBookingSheet } from './use-booking-sheet';

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    client_id: number;
    client_name: string;
    client_phone: string | null;
    client_email: string | null;
    caregiver_id: number | null;
    caregiver_name: string | null;
    assignment_resolution: string | null;
    hotel_id: number | null;
    hotel_name: string | null;
    location_type: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    area: string | null;
    region: string | null;
    start_datetime: string;
    end_datetime: string;
    status: string;
    is_lifesaver: boolean;
    lifesaver_override: boolean | null;
    charge_to_client: number | null;
    paid_to_caregiver: number | null;
    sitterwise_cut: number | null;
    tip: number | null;
    reimbursement: number | null;
    bonus: number | null;
    lifesaver_bonus: number | null;
    payment_status?: string | null;
    payment_attempts?: Array<{
        id: number;
        kind: 'service' | 'tip';
        amount: number;
        status: string;
        error_message: string | null;
        created_at: string | null;
        paid_at: string | null;
    }>;
    special_considerations: string[] | null;
    caregiver_notes: string | null;
    children: Array<{
        name: string;
        gender: string | null;
        birth_year: number | null;
        birth_month: number | null;
    }> | null;
    children_notes: string | null;
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
    client_rating: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    caregiver_rating: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    booking_group: {
        id: number;
        bookings_count: number;
        sibling_bookings: Array<{
            id: number;
            ulid: string;
            start_datetime: string;
            end_datetime: string;
            status: string;
            caregiver_name: string | null;
        }>;
    } | null;
}

interface BookingStatus {
    value: string;
    label: string;
    colors: {
        bg: string;
        text: string;
        border: string;
    };
}

interface Props {
    [key: string]: unknown;
    booking: Booking;
    booking_statuses: BookingStatus[];
    caregiver_suggestions: Array<{
        id: number;
        name: string;
        age?: number | null;
        matchIcons?: string[];
        hasBeenNotified?: boolean;
    }>;
    caregiver_all_ids: number[];
    caregiver_total: number;
    service_types: Array<{ value: string; label: string }>;
    location_types: Array<{ value: string; label: string }>;
    pet_types: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    sitter_preferences: Array<{ value: string; label: string }>;
    client_types: Array<{ value: string; label: string }>;
    discovery_sources: Array<{ value: string; label: string }>;
    hotels: Array<{
        id: number;
        name: string;
        line1: string | null;
        line2: string | null;
        city: string | null;
        state: string | null;
        zip: string | null;
    }>;
    caregivers: Array<{ id: number; name: string; [key: string]: unknown }>;
    clients: Array<{ id: number; name: string; [key: string]: unknown }>;
    booking_attributes: Array<{
        id: number;
        name: string;
        slug: string;
        type: string;
        options: string[];
    }>;
}

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
    {
        title: 'Booking Details',
        href: '#',
    },
];

export default function BookingDetail({
    booking,
    booking_statuses,
    caregiver_suggestions,
    caregiver_all_ids,
    caregiver_total,
    service_types,
    location_types,
    pet_types,
    payment_statuses,
    sitter_preferences,
    client_types,
    discovery_sources,
    hotels,
    caregivers,
    clients,
    booking_attributes,
}: Props) {
    const [splitDialogOpen, setSplitDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [replaceSheetOpen, setReplaceSheetOpen] = useState(false);
    const [notifySheetOpen, setNotifySheetOpen] = useState(false);

    // Return to where we came from (e.g. a client's booking history) when a
    // `?from=` context is present; otherwise fall back to the bookings list.
    // Only same-origin relative paths are honored to avoid open redirects.
    const fromParam =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('from')
            : null;
    const backHref =
        fromParam && fromParam.startsWith('/') && !fromParam.startsWith('//')
            ? fromParam
            : '/bookings';
    const backLabel = backHref === '/bookings' ? 'Back to Bookings' : 'Back';

    const sheet = useBookingSheet({
        clients,
        hotels,
        caregivers,
        service_types,
        location_types,
        booking_statuses,
        payment_statuses,
        booking_attributes,
        sitter_preferences,
        pet_types,
        client_types,
        discovery_sources,
    });

    // "Latest ref" for the (non-memoized) openEditSheet so the ?edit=1 effect can
    // call it without adding an unstable dependency.
    const openEditSheetRef = useRef(sheet.openEditSheet);
    openEditSheetRef.current = sheet.openEditSheet;

    const [caregiverSuggestions, setCaregiverSuggestions] = useState<
        Array<{
            id: number;
            name: string;
            age?: number | null;
            matchIcons?: string[];
            hasBeenNotified?: boolean;
            [key: string]: unknown;
        }>
    >(caregiver_suggestions);
    const [caregiverAllIds, setCaregiverAllIds] =
        useState<number[]>(caregiver_all_ids);
    const [caregiverTotal, setCaregiverTotal] = useState(caregiver_total);
    const [caregiverCurrentPage, setCaregiverCurrentPage] = useState(1);
    const [caregiverLastPage, setCaregiverLastPage] = useState(1);
    const [
        loadingCaregiverRecommendations,
        setLoadingCaregiverRecommendations,
    ] = useState(false);
    const [loadingMoreCaregivers, setLoadingMoreCaregivers] = useState(false);
    const [caregiverSearchQuery, setCaregiverSearchQuery] = useState('');
    const [caregiverSpanishOnly, setCaregiverSpanishOnly] = useState(false);

    const cancelForm = useForm({ reason: '', cancel_group: false });
    const [reopenDialogOpen, setReopenDialogOpen] = useState(false);
    const reopenForm = useForm({});

    const submitReopen = () => {
        reopenForm.post(`/bookings/${booking.ulid}/reopen`, {
            preserveScroll: true,
            onSuccess: () => setReopenDialogOpen(false),
        });
    };

    const lifesaverForm = useForm<{ lifesaver_override: boolean | null }>({
        lifesaver_override: null,
    });

    const postLifesaver = (value: boolean | null) => {
        lifesaverForm.transform(() => ({ lifesaver_override: value }));
        lifesaverForm.post(`/bookings/${booking.ulid}/lifesaver`, {
            preserveScroll: true,
        });
    };

    const submitLifesaver = () => postLifesaver(!booking.is_lifesaver);
    const resetLifesaver = () => postLifesaver(null);

    const activeSiblingCount = booking.booking_group
        ? booking.booking_group.sibling_bookings.filter(
              (sibling) => sibling.status !== 'cancelled',
          ).length
        : 0;

    const deleteForm = useForm({});
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteConfirmText, setDeleteConfirmText] = useState('');

    const submitDelete = () => {
        deleteForm.delete(`/bookings/${booking.ulid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                setDeleteConfirmText('');
            },
        });
    };

    const submitCancel = () => {
        cancelForm.post(`/bookings/${booking.ulid}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                setCancelDialogOpen(false);
                cancelForm.reset();
            },
        });
    };

    const currentSiblingIds = booking.booking_group
        ? [
              booking.id,
              ...booking.booking_group.sibling_bookings.map((s) => s.id),
          ]
        : [];

    const splitForm = useForm({
        booking_ids: [booking.id],
    });

    const toggleSplitBooking = (id: number) => {
        const current = splitForm.data.booking_ids;

        if (current.includes(id)) {
            splitForm.setData(
                'booking_ids',
                current.filter((i) => i !== id),
            );
        } else {
            splitForm.setData('booking_ids', [...current, id]);
        }
    };

    const submitSplit = () => {
        splitForm.post(`/bookings/groups/${booking.booking_group?.id}/split`, {
            preserveScroll: true,
        });
    };
    const buildGoogleMapsUrl = () => {
        const parts = [
            booking.address_line1,
            booking.address_city,
            booking.address_state,
            booking.address_zip,
        ].filter(Boolean);

        if (parts.length === 0) {
            return null;
        }

        return `https://www.google.com/maps/search/${encodeURIComponent(parts.join(', '))}`;
    };

    const getLocationIcon = (locationType: string) => {
        switch (locationType) {
            case 'hotel':
                return Building;
            case 'private_home':
                return Home;
            case 'vacation_rental':
                return Building2;
            case 'event_venue':
                return PartyPopper;
            default:
                return MapPin;
        }
    };

    const mapsUrl = buildGoogleMapsUrl();

    const fetchCaregivers = async (
        page = 1,
        filter = 'all',
        search = '',
        spanishOnly = caregiverSpanishOnly,
    ) => {
        if (page === 1) {
            setLoadingCaregiverRecommendations(true);
        }

        try {
            const params = new URLSearchParams({
                client_id: booking.client_id.toString(),
                page: page.toString(),
                per_page: '20',
                age_filter: filter,
            });

            if (search) {
                params.append('search', search);
            }

            if (spanishOnly) {
                params.append('spanish_only', '1');
            }

            if (booking.id) {
                params.append('booking_id', booking.id.toString());
            }

            if (booking.start_datetime) {
                params.append('start_datetime', booking.start_datetime);
            }

            if (booking.end_datetime) {
                params.append('end_datetime', booking.end_datetime);
            }

            const res = await fetch(
                `/bookings/recommended-caregivers?${params}`,
            );
            const json = await res.json();
            const data = json.data;

            if (page === 1) {
                setCaregiverSuggestions(data);
                setCaregiverAllIds(json.all_ids as number[]);
            } else {
                setCaregiverSuggestions((prev) => [...prev, ...data]);
            }

            setCaregiverTotal(json.meta.total as number);
            setCaregiverCurrentPage(json.meta.current_page as number);
            setCaregiverLastPage(json.meta.last_page as number);
        } catch (error) {
            console.error('Error fetching recommended caregivers:', error);
        } finally {
            if (page === 1) {
                setLoadingCaregiverRecommendations(false);
            }
        }
    };

    const handleLoadMore = async (filter = 'all') => {
        if (
            loadingMoreCaregivers ||
            caregiverCurrentPage >= caregiverLastPage
        ) {
            return;
        }

        setLoadingMoreCaregivers(true);
        await fetchCaregivers(
            caregiverCurrentPage + 1,
            filter,
            caregiverSearchQuery,
        );
        setLoadingMoreCaregivers(false);
    };

    const handleAgeFilterChange = (filter: string) => {
        setCaregiverCurrentPage(1);
        setCaregiverLastPage(1);
        fetchCaregivers(1, filter, caregiverSearchQuery);
    };

    const handleSearchChange = (query: string, filter: string) => {
        setCaregiverSearchQuery(query);
        setCaregiverCurrentPage(1);
        setCaregiverLastPage(1);
        fetchCaregivers(1, filter, query);
    };

    const handleSpanishOnlyChange = (value: boolean, filter: string) => {
        setCaregiverSpanishOnly(value);
        setCaregiverCurrentPage(1);
        setCaregiverLastPage(1);
        fetchCaregivers(1, filter, caregiverSearchQuery, value);
    };

    useEffect(() => {
        if (notifySheetOpen || replaceSheetOpen) {
            setCaregiverSearchQuery('');
            setCaregiverSpanishOnly(false);
            fetchCaregivers(1, 'all', '', false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [notifySheetOpen, replaceSheetOpen]);

    // "Create & Notify" / "Unassign & Re-notify" land here with ?notify=1 — open
    // the Notify panel, then strip the flag so a refresh doesn't reopen it.
    // Keyed to the Inertia `url` (not just mount) so it also fires when we're
    // redirected here from an in-app action that reuses this same component.
    const { url } = usePage();
    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] ?? '');
        let changed = false;

        if (params.get('notify') === '1') {
            setNotifySheetOpen(true);
            params.delete('notify');
            changed = true;
        }

        // Landed here from a client's booking history "Edit" link — open the
        // edit sheet straight away (skipped for cancelled bookings).
        if (params.get('edit') === '1') {
            if (booking.status !== 'cancelled') {
                openEditSheetRef.current(
                    booking as unknown as Parameters<
                        typeof openEditSheetRef.current
                    >[0],
                );
            }

            params.delete('edit');
            changed = true;
        }

        if (changed) {
            const query = params.toString();
            window.history.replaceState(
                {},
                '',
                window.location.pathname + (query ? `?${query}` : ''),
            );
        }
    }, [url, booking]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Booking Details" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href={backHref}
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Booking Details
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            View booking details
                        </p>
                    </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="left-panel">
                            <div className="mb-4 flex items-center gap-2">
                                <h2 className="text-lg font-semibold text-foreground">
                                    Booking Information
                                </h2>
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        sheet.openDuplicateSheet(booking as any)
                                    }
                                >
                                    <Copy className="mr-1 h-4 w-4" />
                                    Duplicate
                                </Button>
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <Heart className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-foreground">
                                        {booking.service_type}
                                    </span>
                                    <StatusBadge
                                        status={booking.status}
                                        bookingStatuses={booking_statuses}
                                    />
                                    {booking.booking_group &&
                                        booking.booking_group.bookings_count >
                                            1 && (
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                Multi-Day (
                                                {
                                                    booking.booking_group
                                                        .bookings_count
                                                }
                                                )
                                            </Badge>
                                        )}
                                    {booking.is_lifesaver && (
                                        <Badge className="bg-rose-500 text-xs text-white hover:bg-rose-500">
                                            🛟 Lifesaver
                                        </Badge>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={submitLifesaver}
                                        disabled={lifesaverForm.processing}
                                        className="h-7 text-xs"
                                    >
                                        {booking.is_lifesaver
                                            ? 'Remove Lifesaver flag'
                                            : 'Flag as Lifesaver'}
                                    </Button>
                                    {booking.lifesaver_override !== null && (
                                        <button
                                            type="button"
                                            onClick={resetLifesaver}
                                            disabled={lifesaverForm.processing}
                                            className="text-xs text-muted-foreground underline hover:text-foreground"
                                        >
                                            Reset to automatic
                                        </button>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {formatDisplayDateInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        from{' '}
                                        {formatDisplayTimeInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        to{' '}
                                        {formatDisplayTimeInPT(
                                            booking.end_datetime,
                                        )}
                                    </span>
                                </div>

                                {booking.booking_group &&
                                    booking.booking_group.bookings_count >
                                        1 && (
                                        <div className="ml-6 space-y-1.5 border-l-2 border-border pl-3">
                                            {booking.booking_group.sibling_bookings.map(
                                                (sibling) => (
                                                    <Link
                                                        key={sibling.id}
                                                        href={`/bookings/${sibling.ulid}`}
                                                        className="flex items-center justify-between rounded px-2 py-1 text-xs transition-colors hover:bg-accent"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {formatDisplayDateInPT(
                                                                sibling.start_datetime,
                                                            )}{' '}
                                                            {formatDisplayTimeInPT(
                                                                sibling.start_datetime,
                                                            )}{' '}
                                                            -{' '}
                                                            {formatDisplayTimeInPT(
                                                                sibling.end_datetime,
                                                            )}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            {sibling.caregiver_name && (
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        sibling.caregiver_name
                                                                    }
                                                                </span>
                                                            )}
                                                            <StatusBadge
                                                                status={
                                                                    sibling.status
                                                                }
                                                                bookingStatuses={
                                                                    booking_statuses
                                                                }
                                                            />
                                                        </div>
                                                    </Link>
                                                ),
                                            )}
                                            <Dialog
                                                open={splitDialogOpen}
                                                onOpenChange={
                                                    setSplitDialogOpen
                                                }
                                            >
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mt-2 text-xs"
                                                    >
                                                        <Split className="mr-1 h-3 w-3" />
                                                        Split Group
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            Split Group
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Select which dates
                                                            to move to a new
                                                            group. The current
                                                            booking cannot be
                                                            unchecked. Extracted
                                                            bookings will reset
                                                            to "received" status
                                                            and clear caregiver
                                                            assignment.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div className="space-y-3 py-4">
                                                        {currentSiblingIds.map(
                                                            (id) => {
                                                                const isCurrent =
                                                                    id ===
                                                                    booking.id;
                                                                const sib =
                                                                    id ===
                                                                    booking.id
                                                                        ? null
                                                                        : booking.booking_group?.sibling_bookings.find(
                                                                              (
                                                                                  s,
                                                                              ) =>
                                                                                  s.id ===
                                                                                  id,
                                                                          );

                                                                return (
                                                                    <div
                                                                        key={id}
                                                                        className="flex items-center gap-3"
                                                                    >
                                                                        <Checkbox
                                                                            id={`split-${id}`}
                                                                            checked={splitForm.data.booking_ids.includes(
                                                                                id,
                                                                            )}
                                                                            disabled={
                                                                                isCurrent
                                                                            }
                                                                            onCheckedChange={() =>
                                                                                toggleSplitBooking(
                                                                                    id,
                                                                                )
                                                                            }
                                                                        />
                                                                        <Label
                                                                            htmlFor={`split-${id}`}
                                                                            className={`text-sm ${isCurrent ? 'font-medium' : 'text-muted-foreground'}`}
                                                                        >
                                                                            {isCurrent ? (
                                                                                <span>
                                                                                    {formatDisplayDateInPT(
                                                                                        booking.start_datetime,
                                                                                    )}{' '}
                                                                                    {formatDisplayTimeInPT(
                                                                                        booking.start_datetime,
                                                                                    )}{' '}
                                                                                    -{' '}
                                                                                    {formatDisplayTimeInPT(
                                                                                        booking.end_datetime,
                                                                                    )}
                                                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                                                        (this
                                                                                        booking)
                                                                                    </span>
                                                                                </span>
                                                                            ) : sib ? (
                                                                                <span>
                                                                                    {formatDisplayDateInPT(
                                                                                        sib.start_datetime,
                                                                                    )}{' '}
                                                                                    {formatDisplayTimeInPT(
                                                                                        sib.start_datetime,
                                                                                    )}{' '}
                                                                                    -{' '}
                                                                                    {formatDisplayTimeInPT(
                                                                                        sib.end_datetime,
                                                                                    )}
                                                                                    {sib.caregiver_name && (
                                                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                                                            (
                                                                                            {
                                                                                                sib.caregiver_name
                                                                                            }

                                                                                            )
                                                                                        </span>
                                                                                    )}
                                                                                </span>
                                                                            ) : null}
                                                                        </Label>
                                                                    </div>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                    <DialogFooter>
                                                        <Button
                                                            variant="outline"
                                                            onClick={() =>
                                                                setSplitDialogOpen(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                        <Button
                                                            onClick={
                                                                submitSplit
                                                            }
                                                            disabled={
                                                                splitForm.processing ||
                                                                splitForm.data
                                                                    .booking_ids
                                                                    .length ===
                                                                    0
                                                            }
                                                        >
                                                            {splitForm.processing
                                                                ? 'Splitting...'
                                                                : 'Split Group'}
                                                        </Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    )}

                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    <Link
                                        href={`/clients/${booking.client_id}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        {booking.client_name}
                                    </Link>
                                </div>

                                {booking.caregiver_name && (
                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        <Link
                                            href={`/caregivers/${booking.caregiver_id}`}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {booking.caregiver_name}
                                        </Link>
                                        {booking.assignment_resolution && (
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {{
                                                    backed_out: 'Backed Out',
                                                    backed_out_excused:
                                                        'Backed Out (Excused)',
                                                    reassigned: 'Reassigned',
                                                    completed: 'Completed',
                                                    no_show: 'No-Show',
                                                    cancelled_by_sitterwise:
                                                        'Cancelled',
                                                }[
                                                    booking
                                                        .assignment_resolution
                                                ] ??
                                                    booking.assignment_resolution}
                                            </Badge>
                                        )}
                                        {booking.status !== 'cancelled' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-6 text-[11px]"
                                                onClick={() =>
                                                    setReplaceSheetOpen(true)
                                                }
                                            >
                                                <UserPlus className="mr-1 h-3 w-3" />
                                                Replace
                                            </Button>
                                        )}
                                    </div>
                                )}

                                {booking.client_phone && (
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <a
                                            href={`tel:${booking.client_phone}`}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {formatPhoneDisplay(
                                                booking.client_phone,
                                            )}
                                        </a>
                                    </div>
                                )}

                                {booking.client_email && (
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <a
                                            href={`mailto:${booking.client_email}`}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {booking.client_email}
                                        </a>
                                    </div>
                                )}

                                {booking.hotel_name && (
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.hotel_name}
                                        </span>
                                    </div>
                                )}

                                {booking.area && (
                                    <div className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                        <Badge
                                            variant="outline"
                                            className="text-xs"
                                        >
                                            {booking.area}
                                            {booking.region
                                                ? ` · ${booking.region}`
                                                : ''}
                                        </Badge>
                                    </div>
                                )}

                                {mapsUrl && (
                                    <div className="flex items-start gap-2">
                                        {React.createElement(
                                            getLocationIcon(
                                                booking.location_type,
                                            ),
                                            {
                                                className:
                                                    'mt-0.5 h-4 w-4 text-muted-foreground',
                                            },
                                        )}
                                        <a
                                            href={mapsUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-start gap-1 text-sm text-primary hover:underline"
                                        >
                                            <span>
                                                {booking.address_line1 && (
                                                    <span>
                                                        {booking.address_line1}
                                                        {booking.address_line2 && (
                                                            <span>
                                                                ,{' '}
                                                                {
                                                                    booking.address_line2
                                                                }
                                                            </span>
                                                        )}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_city && (
                                                    <span>
                                                        {booking.address_city}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_state && (
                                                    <span>
                                                        {booking.address_state}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_zip && (
                                                    <span>
                                                        {booking.address_zip}
                                                    </span>
                                                )}
                                            </span>
                                            <ExternalLink className="mt-0.5 h-3 w-3 shrink-0" />
                                        </a>
                                    </div>
                                )}
                            </div>

                            {booking.children_notes ? (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {booking.children_notes}
                                    </p>
                                </div>
                            ) : booking.children &&
                              booking.children.length > 0 ? (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children ({booking.children.length})
                                    </h2>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {booking.children.map((child, i) => (
                                            <li key={i}>
                                                {child.name} (
                                                {calculateAge(
                                                    child.birth_year,
                                                    child.birth_month,
                                                )}
                                                )
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}

                            {booking.pets && booking.pets.length > 0 && (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Pets ({booking.pets.length})
                                    </h2>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {booking.pets.map((pet, i) => (
                                            <li key={i}>
                                                {pet.name} ({pet.breed} /{' '}
                                                {pet.type})
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        <div className="right-panel grid gap-6">
                            <div>
                                <h2 className="text-md mb-2 font-semibold text-foreground">
                                    Notes & Considerations
                                </h2>
                                <div className="space-y-3">
                                    {booking.special_considerations &&
                                        booking.special_considerations.length >
                                            0 && (
                                            <div>
                                                <h3 className="mb-1 text-sm font-medium text-foreground">
                                                    Special Considerations
                                                </h3>
                                                <div className="flex flex-wrap gap-1">
                                                    {booking.special_considerations.map(
                                                        (consideration, i) => (
                                                            <Badge
                                                                key={i}
                                                                variant="outline"
                                                                className="border-yellow-500 text-yellow-700"
                                                            >
                                                                {consideration}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    {booking.caregiver_notes && (
                                        <div>
                                            <h3 className="mb-1 text-sm font-medium text-foreground">
                                                Notes for Caregiver
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                {booking.caregiver_notes}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <FeesBreakdown
                                heading="Fees"
                                charge_to_client={booking.charge_to_client}
                                paid_to_caregiver={booking.paid_to_caregiver}
                                sitterwise_cut={booking.sitterwise_cut}
                                tip={booking.tip}
                                reimbursement={booking.reimbursement}
                                bonus={booking.bonus}
                                lifesaver_bonus={booking.lifesaver_bonus}
                            />

                            {booking.payment_attempts &&
                                booking.payment_attempts.length > 0 && (
                                    <div className="mt-6">
                                        <h2 className="text-md mb-2 font-semibold text-foreground">
                                            Payment attempts
                                        </h2>
                                        <div className="space-y-2">
                                            {booking.payment_attempts.map(
                                                (attempt) => (
                                                    <div
                                                        key={attempt.id}
                                                        className="flex items-start justify-between gap-2 rounded border border-border bg-card px-3 py-2"
                                                    >
                                                        <div className="flex flex-col">
                                                            <span className="text-sm font-medium text-foreground">
                                                                {attempt.kind ===
                                                                'tip'
                                                                    ? 'Tip'
                                                                    : 'Service charge'}{' '}
                                                                · $
                                                                {Number(
                                                                    attempt.amount,
                                                                ).toFixed(2)}
                                                            </span>
                                                            {attempt.error_message && (
                                                                <span className="text-xs text-destructive">
                                                                    {
                                                                        attempt.error_message
                                                                    }
                                                                </span>
                                                            )}
                                                            {attempt.created_at && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {new Date(
                                                                        attempt.created_at,
                                                                    ).toLocaleDateString()}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span
                                                            className={`inline-flex shrink-0 items-center rounded-[3px] border px-2 py-0.5 text-[10px] font-semibold ${
                                                                attempt.status ===
                                                                'succeeded'
                                                                    ? 'border-green-300 bg-green-100 text-green-800'
                                                                    : attempt.status ===
                                                                        'failed'
                                                                      ? 'border-red-300 bg-red-100 text-red-800'
                                                                      : 'border-slate-300 bg-slate-100 text-slate-700'
                                                            }`}
                                                        >
                                                            {attempt.status}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}

                            <div className="mt-6">
                                <h2 className="mb-4 text-lg font-semibold text-foreground">
                                    Reviews & Feedback
                                </h2>
                                <div className="space-y-4">
                                    <div className="rounded-lg border border-border bg-card p-4">
                                        <h3 className="mb-2 text-sm font-medium text-foreground">
                                            Feedback from Client
                                        </h3>
                                        {booking.caregiver_rating ? (
                                            <div className="flex flex-col gap-2">
                                                <div className="flex items-center gap-1">
                                                    {[1, 2, 3, 4, 5].map(
                                                        (star) => (
                                                            <Star
                                                                key={star}
                                                                className={`h-5 w-5 ${
                                                                    star <=
                                                                    booking
                                                                        .caregiver_rating!
                                                                        .rating
                                                                        ? 'fill-yellow-400 text-yellow-400'
                                                                        : 'text-gray-300'
                                                                }`}
                                                            />
                                                        ),
                                                    )}
                                                    <span className="ml-2 text-sm text-muted-foreground">
                                                        (
                                                        {
                                                            booking
                                                                .caregiver_rating
                                                                .rating
                                                        }
                                                        /5)
                                                    </span>
                                                </div>
                                                {booking.caregiver_rating
                                                    .comment && (
                                                    <p className="text-sm text-muted-foreground italic">
                                                        &quot;
                                                        {
                                                            booking
                                                                .caregiver_rating
                                                                .comment
                                                        }
                                                        &quot;
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                No feedback from client yet.
                                            </p>
                                        )}
                                    </div>

                                    <div className="rounded-lg border border-border bg-card p-4">
                                        <h3 className="mb-2 text-sm font-medium text-foreground">
                                            Review from Caregiver
                                        </h3>
                                        {booking.client_rating ? (
                                            <div className="flex flex-col gap-2">
                                                <div className="flex items-center gap-1">
                                                    {[1, 2, 3, 4, 5].map(
                                                        (star) => (
                                                            <Star
                                                                key={star}
                                                                className={`h-5 w-5 ${
                                                                    star <=
                                                                    booking
                                                                        .client_rating!
                                                                        .rating
                                                                        ? 'fill-yellow-400 text-yellow-400'
                                                                        : 'text-gray-300'
                                                                }`}
                                                            />
                                                        ),
                                                    )}
                                                    <span className="ml-2 text-sm text-muted-foreground">
                                                        (
                                                        {
                                                            booking
                                                                .client_rating
                                                                .rating
                                                        }
                                                        /5)
                                                    </span>
                                                </div>
                                                {booking.client_rating
                                                    .comment && (
                                                    <p className="text-sm text-muted-foreground italic">
                                                        &quot;
                                                        {
                                                            booking
                                                                .client_rating
                                                                .comment
                                                        }
                                                        &quot;
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                No review from caregiver yet.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    {booking.status !== 'cancelled' && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                sheet.openEditSheet(
                                    booking as unknown as Parameters<
                                        typeof sheet.openEditSheet
                                    >[0],
                                )
                            }
                        >
                            Edit Booking
                        </Button>
                    )}
                    {['received', 'pending'].includes(booking.status) && (
                        <Button
                            variant="outline"
                            onClick={() => setNotifySheetOpen(true)}
                        >
                            Notify Caregivers
                        </Button>
                    )}
                    {booking.status !== 'cancelled' && (
                        <>
                            {booking.caregiver_id && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setReplaceSheetOpen(true)
                                        }
                                    >
                                        <UserPlus className="mr-1 h-4 w-4" />
                                        Replace Caregiver
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setReopenDialogOpen(true)
                                        }
                                    >
                                        Unassign &amp; Re-notify
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="destructive"
                                onClick={() => setCancelDialogOpen(true)}
                            >
                                Cancel Booking
                            </Button>
                        </>
                    )}
                    <Button
                        variant="destructive"
                        onClick={() => setDeleteDialogOpen(true)}
                    >
                        Delete Booking
                    </Button>
                    <Button asChild>
                        <Link href={backHref}>{backLabel}</Link>
                    </Button>
                </div>
            </div>

            <ReplaceCaregiverSheet
                open={replaceSheetOpen}
                onOpenChange={setReplaceSheetOpen}
                bookingId={booking.id}
                currentCaregiverName={booking.caregiver_name}
                caregiverSuggestions={caregiverSuggestions}
                caregiverTotal={caregiverTotal}
                caregiverCurrentPage={caregiverCurrentPage}
                caregiverLastPage={caregiverLastPage}
                loadingCaregiverRecommendations={
                    loadingCaregiverRecommendations
                }
                loadingMoreCaregivers={loadingMoreCaregivers}
                onLoadMoreCaregivers={handleLoadMore}
                onAgeFilterChange={handleAgeFilterChange}
                onSearchChange={handleSearchChange}
                onSpanishOnlyChange={handleSpanishOnlyChange}
            />

            <NotifyCaregiversSheet
                open={notifySheetOpen}
                onOpenChange={setNotifySheetOpen}
                bookingId={booking.id}
                caregiverSuggestions={caregiverSuggestions}
                caregiverAllIds={caregiverAllIds}
                caregiverTotal={caregiverTotal}
                caregiverCurrentPage={caregiverCurrentPage}
                caregiverLastPage={caregiverLastPage}
                loadingCaregiverRecommendations={
                    loadingCaregiverRecommendations
                }
                loadingMoreCaregivers={loadingMoreCaregivers}
                onLoadMoreCaregivers={handleLoadMore}
                onAgeFilterChange={handleAgeFilterChange}
                onSearchChange={handleSearchChange}
                onSpanishOnlyChange={handleSpanishOnlyChange}
            />

            <BookingSheet {...sheet} />

            <Dialog open={reopenDialogOpen} onOpenChange={setReopenDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unassign &amp; Re-notify</DialogTitle>
                        <DialogDescription>
                            Remove{' '}
                            {booking.caregiver_name
                                ? booking.caregiver_name
                                : 'the caregiver'}{' '}
                            from this booking and reopen it to caregivers? The
                            job resets to “Received” and you’ll pick who to
                            re-notify next.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setReopenDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={submitReopen}
                            disabled={reopenForm.processing}
                        >
                            {reopenForm.processing
                                ? 'Unassigning...'
                                : 'Unassign & Re-notify'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel Booking</DialogTitle>
                        <DialogDescription>
                            This action will mark the booking as cancelled, zero
                            out all financial fields, and release the caregiver.
                            This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Cancellation Reason</Label>
                            <Textarea
                                id="reason"
                                value={cancelForm.data.reason}
                                onChange={(e) =>
                                    cancelForm.setData('reason', e.target.value)
                                }
                                placeholder="Explain why this booking is being cancelled..."
                                rows={3}
                            />
                            {cancelForm.errors.reason && (
                                <p className="text-sm text-destructive">
                                    {cancelForm.errors.reason}
                                </p>
                            )}
                        </div>

                        {activeSiblingCount > 0 && (
                            <div className="flex items-start gap-3 rounded-md border border-border bg-muted/50 p-3">
                                <Checkbox
                                    id="cancel_group"
                                    checked={cancelForm.data.cancel_group}
                                    onCheckedChange={(checked) =>
                                        cancelForm.setData(
                                            'cancel_group',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="cancel_group"
                                    className="text-sm font-normal"
                                >
                                    This is a multi-day booking. Also cancel the
                                    other {activeSiblingCount} date
                                    {activeSiblingCount > 1 ? 's' : ''} in this
                                    group.
                                </Label>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setCancelDialogOpen(false);
                                cancelForm.reset();
                            }}
                        >
                            Keep Booking
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            {cancelForm.processing
                                ? 'Cancelling...'
                                : 'Cancel Booking'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Booking</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this booking?
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
                            <ul className="list-disc space-y-1 pl-4">
                                <li>
                                    This action is permanent and cannot be
                                    undone.
                                </li>
                                <li>
                                    All related data — reviews, ratings,
                                    transactions — will also be deleted.
                                </li>
                            </ul>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delete-confirm">
                                Type{' '}
                                <span className="font-mono font-bold">
                                    DELETE
                                </span>{' '}
                                to confirm:
                            </Label>
                            <Input
                                id="delete-confirm"
                                value={deleteConfirmText}
                                onChange={(e) =>
                                    setDeleteConfirmText(e.target.value)
                                }
                                placeholder="DELETE"
                                className={
                                    deleteConfirmText === 'DELETE'
                                        ? 'border-destructive'
                                        : ''
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDeleteDialogOpen(false);
                                setDeleteConfirmText('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitDelete}
                            disabled={
                                deleteConfirmText !== 'DELETE' ||
                                deleteForm.processing
                            }
                        >
                            {deleteForm.processing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
