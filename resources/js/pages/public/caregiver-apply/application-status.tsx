import { Head, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ReferenceRequest {
    id: number;
    reference_name: string;
    reference_email: string;
    relationship: string | null;
    is_sponsor: boolean;
    is_completed: boolean;
    submitted_at: string | null;
}

interface ChecklistItem {
    id: number;
    label: string;
    description: string | null;
    completed: boolean;
    completed_at: string | null;
}

interface Props {
    status: { value: string; label: string; color: string };
    caregiver_name: string;
    reference_requests: ReferenceRequest[];
    checklist_items?: ChecklistItem[];
    token: string;
    [key: string]: unknown;
}

export default function ApplicationStatus() {
    const { status, caregiver_name, reference_requests, checklist_items, token } = usePage<Props>().props;
    const [replacing, setReplacing] = useState<number | null>(null);
    const [form, setForm] = useState({ reference_name: '', reference_email: '', relationship: '' });
    const [submitting, setSubmitting] = useState(false);

    const completedCount = reference_requests.filter((r) => r.is_completed).length;
    const totalCount = reference_requests.length;

    function startReplace(ref: ReferenceRequest) {
        setReplacing(ref.id);
        setForm({
            reference_name: ref.reference_name,
            reference_email: ref.reference_email,
            relationship: ref.relationship ?? '',
        });
    }

    function submitReplace(refId: number) {
        if (!form.reference_name.trim() || !form.reference_email.trim()) {
return;
}

        setSubmitting(true);
        router.post(
            `/caregiver/apply/status/${token}/replace-reference/${refId}`,
            form,
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setReplacing(null);
                    router.reload({ only: ['reference_requests'] });
                },
            },
        );
    }

    return (
        <>
            <Head title="Application Status - Sitterwise" />

            <div className="flex min-h-screen items-start justify-center bg-background px-4 py-12 sm:px-6 lg:px-8">
                <div className="w-full max-w-2xl space-y-8">
                    <div className="text-center">
                        <h1 className="text-3xl font-extrabold text-foreground">
                            Application Status
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Hi {caregiver_name}, here's where things stand.
                        </p>
                    </div>

                    <div className="rounded-lg bg-card p-6 shadow-xs border border-border">
                        <h2 className="text-lg font-semibold text-foreground">
                            Application Status
                        </h2>
                        <div className="mt-3">
                            <Badge
                                className="text-sm px-3 py-1"
                                style={{
                                    backgroundColor: status.color + '20',
                                    color: status.color,
                                }}
                            >
                                {status.label}
                            </Badge>
                        </div>
                    </div>

                    {checklist_items && checklist_items.length > 0 && (
                        <div className="rounded-lg bg-card p-6 shadow-xs border border-border">
                            <h2 className="text-lg font-semibold text-foreground">
                                Onboarding Checklist
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Your admin will guide you through each item.
                            </p>
                            <div className="mt-4 space-y-3">
                                {checklist_items.map((item) => {
                                    const isCompleted = item.completed;

                                    return (
                                        <div
                                            key={item.id}
                                            className={`flex items-start gap-4 rounded-lg border p-4 ${
                                                isCompleted
                                                    ? 'border-green-200 bg-green-50'
                                                    : 'border-border bg-card'
                                            }`}
                                        >
                                            <div
                                                className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-bold ${
                                                    isCompleted
                                                        ? 'border-green-500 bg-green-500 text-white'
                                                        : 'border-muted bg-muted text-transparent'
                                                }`}
                                            >
                                                {isCompleted ? '✓' : ''}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-foreground">
                                                    {item.label}
                                                </p>
                                                {item.description && (
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                )}
                                                {item.completed_at && (
                                                    <Badge className="mt-1.5 bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">
                                                        Done · {format(new Date(item.completed_at), 'MMM d')}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="mt-4 border-t border-border pt-4">
                                <p className="text-xs text-muted-foreground">
                                    {checklist_items.filter((i) => i.completed).length}/{checklist_items.length} completed
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="rounded-lg bg-card p-6 shadow-xs border border-border">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-foreground">
                                Reference Check
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {completedCount}/{totalCount} completed
                            </span>
                        </div>

                        <div className="mt-4 space-y-3">
                            {reference_requests.map((ref) => (
                                <div
                                    key={ref.id}
                                    className={`rounded-lg border p-4 ${
                                        ref.is_completed
                                            ? 'border-green-200 bg-green-50'
                                            : 'border-border bg-card'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-sm text-foreground">
                                                    {ref.reference_name}
                                                </span>
                                                {ref.is_sponsor && (
                                                    <Badge variant="outline" className="text-xs">
                                                        Sponsor
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={ref.is_completed ? 'default' : 'secondary'}
                                                    className={
                                                        ref.is_completed
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-muted text-muted-foreground'
                                                    }
                                                >
                                                    {ref.is_completed ? 'Completed' : 'Pending'}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {ref.reference_email}
                                                {ref.relationship && ` · ${ref.relationship}`}
                                            </p>
                                        </div>

                                        {!ref.is_completed && replacing !== ref.id && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => startReplace(ref)}
                                            >
                                                Replace
                                            </Button>
                                        )}
                                    </div>

                                    {replacing === ref.id && (
                                        <div className="mt-4 space-y-3 border-t border-border pt-4">
                                            <div>
                                                <Label>
                                                    Name
                                                </Label>
                                                <Input
                                                    type="text"
                                                    value={form.reference_name}
                                                    onChange={(e) =>
                                                        setForm({ ...form, reference_name: e.target.value })
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>
                                                    Email
                                                </Label>
                                                <Input
                                                    type="email"
                                                    value={form.reference_email}
                                                    onChange={(e) =>
                                                        setForm({ ...form, reference_email: e.target.value })
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>
                                                    Relationship (optional)
                                                </Label>
                                                <Input
                                                    type="text"
                                                    value={form.relationship}
                                                    onChange={(e) =>
                                                        setForm({ ...form, relationship: e.target.value })
                                                    }
                                                />
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() => submitReplace(ref.id)}
                                                    disabled={submitting}
                                                >
                                                    {submitting ? 'Saving...' : 'Save & Re-send'}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setReplacing(null)}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border border-border-teal bg-teal-bg p-4 text-sm text-foreground leading-relaxed">
                        <strong className="mb-1 block font-semibold">
                            Need help?
                        </strong>
                        Reach out to <a href="mailto:hello@sitterwise.com" className="font-semibold text-primary">hello@sitterwise.com</a> or text us at 619-663-4379 if anything's blocking you.
                    </div>
                </div>
            </div>
        </>
    );
}
