import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CaregiverPauseController from '@/actions/App/Http/Controllers/Settings/CaregiverPauseController';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { pause as pauseRoute } from '@/routes/settings/caregiver';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'Pause Account', href: pauseRoute() },
];

interface ActivePause {
    paused_at: string;
    resume_by: string | null;
    pause_reason: string | null;
}

export default function Pause() {
    const { caregiver, activePause } = usePage<{
        caregiver: { status: { value: string; label: string } };
        activePause: ActivePause | null;
    }>().props;

    const isPaused = caregiver.status.value === 'on_hold';

    const pauseForm = useForm({
        resume_by: '',
        pause_reason: '',
    });

    const [submitting, setSubmitting] = useState(false);

    const handlePause = () => {
        setSubmitting(true);
        pauseForm.post(CaregiverPauseController.pause.url(), {
            onFinish: () => setSubmitting(false),
        });
    };

    const handleResume = () => {
        setSubmitting(true);
        pauseForm.post(CaregiverPauseController.resume.url(), {
            onFinish: () => setSubmitting(false),
        });
    };

    const formatPausedDate = (iso: string) => {
        const date = new Date(iso);

        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pause Account" />
            <ToasterMessage />
            <SettingsLayout>
                <div className="space-y-6">
                    {!isPaused ? (
                        <>
                            <div>
                                <h2 className="text-xl font-bold text-foreground">
                                    Need a break? Pause your account.
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Self-service hold/resume. When life happens, you can pause
                                    instead of ignoring job offers.
                                </p>
                            </div>

                            <div className="rounded-lg border border-border bg-card p-6">
                                <h3 className="font-serif text-lg font-semibold text-foreground">
                                    Take the time you need.
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Pausing your account stops new job offers from coming through.
                                    Your profile, ratings, history, and Trustline progress are all
                                    preserved. Resume whenever you're ready — usually with one tap.
                                </p>

                                <div className="mt-6 space-y-4">
                                    <div>
                                        <Label htmlFor="resume_by">
                                            When do you expect to be back?{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <div className="mt-1.5">
                                            <DatePicker
                                                value={pauseForm.data.resume_by}
                                                onChange={(date) => pauseForm.setData('resume_by', date)}
                                                placeholder="Select a date"
                                                disabled={{ before: new Date() }}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="pause_reason">
                                            Anything you want to share?{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Textarea
                                            id="pause_reason"
                                            value={pauseForm.data.pause_reason}
                                            onChange={(e) => pauseForm.setData('pause_reason', e.target.value)}
                                            placeholder="Vacation, family stuff, school, just need a breather — totally up to you whether to say."
                                            className="mt-1.5 min-h-[80px]"
                                        />
                                        {pauseForm.errors.pause_reason && (
                                            <p className="mt-1 text-sm text-destructive">{pauseForm.errors.pause_reason}</p>
                                        )}
                                    </div>

                                    <div className="flex gap-3 pt-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => window.history.back()}
                                            className="flex-1"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            onClick={handlePause}
                                            disabled={submitting}
                                            className="flex-1"
                                        >
                                            {submitting ? 'Pausing...' : 'Pause my account'}
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/50">
                                <p className="text-sm text-teal-800 dark:text-teal-200">
                                    <strong>What happens when you resume:</strong> Just tap "Resume"
                                    on this same screen. Your status changes back to Active and job
                                    offers start coming through again. No admin intervention required
                                    for the routine case.
                                </p>
                            </div>
                        </>
                    ) : (
                        <>
                            <div>
                                <h2 className="text-xl font-bold text-foreground">
                                    You're on a break
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Your account is paused. Come back whenever you're ready.
                                </p>
                            </div>

                            <div className="rounded-lg border border-border bg-card p-6">
                                <h3 className="font-serif text-lg font-semibold text-foreground">
                                    Pause Details
                                </h3>
                                <div className="mt-4 space-y-3">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Paused since
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {activePause ? formatPausedDate(activePause.paused_at) : '—'}
                                        </p>
                                    </div>
                                    {activePause?.resume_by && (
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Expected back by
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {formatPausedDate(activePause.resume_by)}
                                            </p>
                                        </div>
                                    )}
                                    {activePause?.pause_reason && (
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Reason
                                            </p>
                                            <p className="text-sm text-foreground">
                                                {activePause.pause_reason}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="mt-6">
                                    <Button
                                        onClick={handleResume}
                                        disabled={submitting}
                                        className="w-full"
                                    >
                                        {submitting ? 'Resuming...' : 'Resume'}
                                    </Button>
                                </div>
                            </div>

                            <div className="rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/50">
                                <p className="text-sm text-teal-800 dark:text-teal-200">
                                    <strong>Resuming is instant:</strong> Your status changes back to
                                    Active immediately and job offers start coming through again. No
                                    admin intervention needed.
                                </p>
                            </div>
                        </>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
