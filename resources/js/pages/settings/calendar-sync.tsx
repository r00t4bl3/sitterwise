import { Head, router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

interface Props {
    [key: string]: unknown;
    feedUrl: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'Calendar Sync', href: '/settings/caregiver/calendar-sync' },
];

const instructions: Array<{ app: string; steps: string }> = [
    {
        app: 'Google Calendar',
        steps: 'Other calendars → + → From URL → paste the link → Add calendar.',
    },
    {
        app: 'Apple Calendar',
        steps: 'File → New Calendar Subscription → paste the link → Subscribe.',
    },
    {
        app: 'Outlook',
        steps: 'Add calendar → Subscribe from web → paste the link → Import.',
    },
];

export default function CalendarSync() {
    const { feedUrl } = usePage<Props>().props;
    const [copiedText, copy] = useClipboard();
    const [regenerating, setRegenerating] = useState(false);

    const copied = copiedText === feedUrl;

    const regenerate = () => {
        if (
            !window.confirm(
                'Regenerate your calendar link? Your current link will stop working and you will need to re-add the new one in your calendar app.',
            )
        ) {
            return;
        }

        router.post(
            '/settings/caregiver/calendar-sync/regenerate',
            {},
            {
                preserveScroll: true,
                onStart: () => setRegenerating(true),
                onFinish: () => setRegenerating(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendar Sync" />
            <ToasterMessage />
            <SettingsLayout>
                <div className="space-y-8">
                    <div>
                        <h2 className="text-xl font-bold text-foreground">
                            Calendar Sync
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Subscribe to your confirmed jobs from any calendar
                            app. Your calendar refreshes automatically — new
                            jobs appear and cancelled ones drop off on their
                            own.
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="feed-url">
                            Your private calendar link
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="feed-url"
                                value={feedUrl}
                                readOnly
                                onFocus={(e) => e.currentTarget.select()}
                                className="font-mono text-xs"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => copy(feedUrl)}
                                className="shrink-0"
                            >
                                {copied ? (
                                    <Check className="h-4 w-4" />
                                ) : (
                                    <Copy className="h-4 w-4" />
                                )}
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Keep this link private — anyone with it can see your
                            job schedule.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold text-foreground">
                            How to subscribe
                        </h3>
                        <ul className="space-y-2">
                            {instructions.map((item) => (
                                <li
                                    key={item.app}
                                    className="text-sm text-muted-foreground"
                                >
                                    <span className="font-medium text-foreground">
                                        {item.app}:
                                    </span>{' '}
                                    {item.steps}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="space-y-2 border-t border-border pt-6">
                        <h3 className="text-sm font-semibold text-foreground">
                            Regenerate link
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            If your link was shared by mistake, regenerate it to
                            revoke access. You will need to re-add the new link
                            in your calendar app.
                        </p>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={regenerate}
                            disabled={regenerating}
                        >
                            Regenerate Link
                        </Button>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
