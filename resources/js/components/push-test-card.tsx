import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePushSubscription } from '@/hooks/use-push-subscription';
import { pushTest } from '@/routes/settings';

export default function PushTestCard() {
    const { props } = usePage<{
        push_subscribed?: boolean;
    }>();
    const { isSupported, subscription, loading, error, subscribe } =
        usePushSubscription();
    const [testSending, setTestSending] = useState(false);

    if (!isSupported) {
        return null;
    }

    const handleSendTest = () => {
        setTestSending(true);
        router.post(
            pushTest().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTestSending(false),
            },
        );
    };

    const isSubscribed = !!subscription || !!props.push_subscribed;

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title="Push Notifications"
                description="Test push notification delivery to your device"
            />

            <div className="rounded-lg border p-4">
                {isSubscribed ? (
                    <div className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Push notifications are enabled for your browser.
                        </p>
                        <Button onClick={handleSendTest} disabled={testSending}>
                            {testSending ? 'Sending...' : 'Send Test Push'}
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Enable push notifications to receive alerts on your
                            device.
                        </p>
                        {error && (
                            <p className="text-sm text-red-600">{error}</p>
                        )}
                        <Button onClick={subscribe} disabled={loading}>
                            {loading
                                ? 'Enabling...'
                                : 'Enable Push Notifications'}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
