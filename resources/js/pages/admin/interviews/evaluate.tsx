import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Props {
    application: { id: number; submitted_at: string };
    caregiver: {
        id: number;
        first_name: string;
        last_name: string;
        initials: string;
        email: string;
    };
    sponsor: { name: string; relationship: string | null } | null;
    existing: {
        scores: { soft_skills: Record<string, number>; professionalism: Record<string, number> };
        composite: number;
        notes: string;
        status: string;
    } | null;
    [key: string]: unknown;
}

const SOFT_SKILLS: Array<{ key: string; label: string; description: string }> = [
    { key: 'confidence', label: 'Confidence / presence', description: 'How she carries herself in the room, eye contact' },
    { key: 'warmth', label: 'Warmth / smiles', description: 'Does she light up? Would kids feel at ease?' },
    { key: 'experience', label: 'Experience level', description: 'Depth and relevance of childcare background' },
    { key: 'communicativeness', label: 'Communicativeness', description: 'Clear, responsive, easy to talk to' },
    { key: 'humor', label: 'Sense of humor', description: 'Relaxed, relatable, good energy' },
    { key: 'preparedness', label: 'Preparedness', description: 'Came informed, thoughtful, and engaged' },
];

const PROFESSIONALISM: Array<{ key: string; label: string; description: string }> = [
    { key: 'on_time', label: 'On time', description: 'Punctual and respectful of the schedule' },
    { key: 'id_prepared', label: 'Prepared with ID', description: 'Brought required identity documents' },
    { key: 'dress_code', label: 'In dress code', description: 'Presented professionally for the interview' },
];

function HeartRating({ value, onChange }: { value: number; onChange: (v: number) => void }) {
    const [hovered, setHovered] = useState<number | null>(null);

    const activeValue = hovered ?? value;

    function heartColor(): string {
        if (activeValue === 4) {
return 'text-green-500';
}

        if (activeValue === 3) {
return 'text-blue-500';
}

        if (activeValue === 2) {
return 'text-amber-400';
}

        if (activeValue === 1) {
return 'text-red-400';
}

        return 'text-gray-200';
    }

    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4].map((heart) => (
                <button
                    key={heart}
                    type="button"
                    onClick={() => onChange(heart)}
                    onMouseEnter={() => setHovered(heart)}
                    onMouseLeave={() => setHovered(null)}
                    className={`cursor-pointer text-2xl leading-none transition-colors ${
                        heart <= activeValue ? heartColor() : 'text-gray-200 hover:text-gray-300'
                    }`}
                >
                    ♥
                </button>
            ))}
        </div>
    );
}

export default function InterviewEvaluate() {
    const { application, caregiver, sponsor, existing } = usePage<Props>().props;

    const [scores, setScores] = useState<Record<string, Record<string, number>>>(
        existing?.scores ?? {
            soft_skills: Object.fromEntries(SOFT_SKILLS.map((s) => [s.key, 0])),
            professionalism: Object.fromEntries(PROFESSIONALISM.map((s) => [s.key, 0])),
        },
    );
    const [notes, setNotes] = useState(existing?.notes ?? '');
    const [submitting, setSubmitting] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState<{ open: boolean; title: string; message: string; onConfirm: () => void }>({
        open: false, title: '', message: '', onConfirm: () => {},
    });

    const allScores = [
        ...Object.values(scores.soft_skills),
        ...Object.values(scores.professionalism),
    ];
    const composite = allScores.reduce((a, b) => a + b, 0);
    const percentage = Math.round((composite / 36) * 100);

    function setScore(category: string, key: string, value: number) {
        setScores((prev) => ({
            ...prev,
            [category]: { ...prev[category], [key]: value },
        }));
    }

    function handleSave(status: 'draft' | 'declined' | 'completed') {
        if (!notes.trim()) {
return;
}

        setSubmitting(true);
        router.post(
            `/applications/${application.id}/interview`,
            { scores, notes, status },
            {
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Caregiver Applications', href: '/applications' },
        { title: `${caregiver.first_name} ${caregiver.last_name}`, href: `/applications/${application.id}` },
        { title: 'Interview Evaluation', href: `/applications/${application.id}/interview` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Interview - ${caregiver.first_name} ${caregiver.last_name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Candidate Context Header */}
                <div className="flex items-center gap-4 rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-lg font-bold text-primary">
                        {caregiver.initials}
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-foreground">
                            {caregiver.first_name} {caregiver.last_name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Interviewer: <span className="font-medium">You</span>
                            {sponsor && (
                                <>
                                    {' · '}Sponsor: <span className="font-medium">{sponsor.name}</span>
                                    {sponsor.relationship && ` (${sponsor.relationship})`}
                                </>
                            )}
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                    <div className="p-6 space-y-6">
                        {/* Rating Legend */}
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            <span className="font-medium text-muted-foreground">Rating scale:</span>
                            <span className="flex items-center gap-1"><span className="text-green-500">♥♥♥♥</span> Strong</span>
                            <span className="flex items-center gap-1"><span className="text-blue-500">♥♥♥</span> Good fit</span>
                            <span className="flex items-center gap-1"><span className="text-amber-400">♥♥</span> Has potential</span>
                            <span className="flex items-center gap-1"><span className="text-red-400">♥</span> Concern</span>
                        </div>

                        {/* Soft Skills */}
                        <div>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Soft skills
                            </h3>
                            <div className="space-y-4">
                                {SOFT_SKILLS.map((skill) => (
                                    <div
                                        key={skill.key}
                                        className="flex items-center justify-between rounded-lg border border-border bg-card p-4"
                                    >
                                        <div className="flex-1">
                                            <h4 className="text-sm font-medium text-foreground">
                                                {skill.label}
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                {skill.description}
                                            </p>
                                        </div>
                                        <HeartRating
                                            value={scores.soft_skills[skill.key] ?? 0}
                                            onChange={(v) => setScore('soft_skills', skill.key, v)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Professionalism */}
                        <div>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Professionalism
                            </h3>
                            <div className="space-y-4">
                                {PROFESSIONALISM.map((skill) => (
                                    <div
                                        key={skill.key}
                                        className="flex items-center justify-between rounded-lg border border-border bg-card p-4"
                                    >
                                        <div className="flex-1">
                                            <h4 className="text-sm font-medium text-foreground">
                                                {skill.label}
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                {skill.description}
                                            </p>
                                        </div>
                                        <HeartRating
                                            value={scores.professionalism[skill.key] ?? 0}
                                            onChange={(v) => setScore('professionalism', skill.key, v)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Composite Score */}
                        <div className="flex items-center gap-3 rounded-lg bg-muted/50 p-4">
                            <div>
                                <span className="text-sm font-medium text-foreground">
                                    Interview score
                                </span>
                                <div className="flex items-baseline gap-1">
                                    <span className="text-2xl font-bold text-foreground">
                                        {composite}
                                    </span>
                                    <span className="text-sm text-muted-foreground">/ 36</span>
                                    <span className="text-sm text-muted-foreground">
                                        · {percentage}%
                                    </span>
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Auto-calculated from all 9 ratings above. Used for internal sorting. Doesn't replace your notes.
                            </p>
                        </div>

                        {/* Notes */}
                        <div>
                            <h3 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Notes / overall impressions
                            </h3>
                            <textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Capture the things a rating can't. Strengths, concerns, anything specific you noticed."
                                className="min-h-[120px] w-full rounded-lg border border-border bg-card p-4 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap gap-3">
                            <Button
                                variant="outline"
                                onClick={() => handleSave('draft')}
                                disabled={submitting}
                            >
                                {submitting ? 'Saving...' : 'Save draft'}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    setConfirmDialog({
                                        open: true,
                                        title: 'Decline Candidate',
                                        message: 'Decline this candidate? Their status will be set to Inactive.',
                                        onConfirm: () => {
                                            setConfirmDialog((prev) => ({ ...prev, open: false }));
                                            handleSave('declined');
                                        },
                                    });
                                }}
                                disabled={submitting}
                            >
                                Decline candidate
                            </Button>
                            <Button
                                onClick={() => {
                                    setConfirmDialog({
                                        open: true,
                                        title: 'Advance to Background Check',
                                        message: 'Save and advance this candidate to background check?',
                                        onConfirm: () => {
                                            setConfirmDialog((prev) => ({ ...prev, open: false }));
                                            handleSave('completed');
                                        },
                                    });
                                }}
                                disabled={submitting}
                            >
                                Save & advance to background check
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog
                open={confirmDialog.open}
                onOpenChange={(open) => setConfirmDialog((prev) => ({ ...prev, open }))}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{confirmDialog.title}</DialogTitle>
                        <DialogDescription>{confirmDialog.message}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDialog((prev) => ({ ...prev, open: false }))}
                        >
                            Cancel
                        </Button>
                        <Button onClick={confirmDialog.onConfirm}>
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
