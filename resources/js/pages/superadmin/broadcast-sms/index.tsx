import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Send } from 'lucide-react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Props {
    recipientCount: number;
    // Forwarded from BroadcastSmsController so the preview always matches what
    // is actually sent (single source of truth on the backend).
    complianceFooter: string;
}

function getSmsSegments(text: string): { chars: number; segments: number } {
    const chars = text.length;
    let segments = 1;

    if (chars > 160) {
        segments = Math.ceil(chars / 153);
    }

    return { chars, segments };
}

export default function BroadcastSms({
    recipientCount,
    complianceFooter,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        message_body: '',
    });

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>()
        .props;
    const showFlashSuccess = flash?.success;

    const { chars, segments } = getSmsSegments(data.message_body);
    const fullMessage = data.message_body + complianceFooter;
    const { segments: fullSegments } = getSmsSegments(fullMessage);
    const showSegmentWarning = fullSegments > 3;

    const [showConfirmModal, setShowConfirmModal] = useState(false);

    function handleReviewAndSend() {
        if (!data.message_body.trim()) {
            return;
        }

        setShowConfirmModal(true);
    }

    function handleConfirmSend() {
        setShowConfirmModal(false);
        post('/broadcast-sms', {
            preserveScroll: true,
            onSuccess: () => {
                setData('message_body', '');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Broadcast SMS" />
            <ToasterMessage />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Broadcast SMS
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Send an SMS to all active, opted-in caregivers
                        </p>
                    </div>
                </div>

                {showFlashSuccess && (
                    <div className="rounded-lg border border-green-500/30 bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        <p className="font-medium">{flash.success}</p>
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-lg border border-red-500/30 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                        <p className="font-medium">{flash.error}</p>
                    </div>
                )}

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-lg border border-red-500/30 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                        <p className="font-medium">
                            {Object.values(errors).join(', ')}
                        </p>
                    </div>
                )}

                <div className="rounded-lg border bg-card p-4 text-sm text-muted-foreground">
                    This message will be sent to{' '}
                    <strong>{recipientCount} active caregivers</strong>.
                </div>

                <div className="space-y-2">
                    <label htmlFor="message" className="text-sm font-medium">
                        Message
                    </label>
                    <Textarea
                        id="message"
                        placeholder="Type your broadcast message here..."
                        className="min-h-32"
                        value={data.message_body}
                        onChange={(e) =>
                            setData('message_body', e.target.value)
                        }
                        maxLength={918}
                    />
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            {chars} characters / {segments} SMS segment
                            {segments !== 1 ? 's' : ''}
                        </span>
                        {showSegmentWarning && (
                            <span className="flex items-center gap-1 text-amber-600">
                                <AlertCircle className="size-3" />
                                Message exceeds 3 segments
                            </span>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border bg-muted/50 p-3 text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        Compliance footer (auto-appended):
                    </span>
                    <pre className="mt-1 font-sans whitespace-pre-wrap">
                        {complianceFooter}
                    </pre>
                </div>

                <div>
                    <Button
                        onClick={handleReviewAndSend}
                        disabled={!data.message_body.trim() || processing}
                        className="gap-2"
                    >
                        <Send className="size-4" />
                        Review & Send
                    </Button>
                </div>

                <Dialog
                    open={showConfirmModal}
                    onOpenChange={setShowConfirmModal}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirm Broadcast</DialogTitle>
                            <DialogDescription>
                                Review your message before sending.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div>
                                <span className="text-sm font-medium">
                                    Message:
                                </span>
                                <pre className="mt-1 rounded-lg border bg-muted/50 p-3 text-sm whitespace-pre-wrap">
                                    {fullMessage}
                                </pre>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Send to{' '}
                                <strong>{recipientCount} caregivers</strong>?
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setShowConfirmModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleConfirmSend}
                                disabled={processing}
                            >
                                Send Now
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Broadcast SMS',
        href: '/broadcast-sms',
    },
];
