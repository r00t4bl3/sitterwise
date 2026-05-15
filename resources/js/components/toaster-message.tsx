import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export interface Message {
    type: 'success' | 'error' | 'info' | 'warning';
    content: string;
}

interface ToasterMessageProps {
    message?: Message | null;
}

let lastFingerprint: string | null = null;

export function ToasterMessage({ message: propMessage }: ToasterMessageProps) {
    const { props } = usePage();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;
    const errors = props.errors;

    const fingerprintRef = useRef(lastFingerprint);

    useEffect(() => {
        // Reset dedup when navigating to a page without flash or errors
        if (
            !flash?.success &&
            !flash?.error &&
            !propMessage?.content &&
            Object.keys(errors).length === 0
        ) {
            lastFingerprint = null;
            fingerprintRef.current = null;
        }

        const showToast = (
            type: Message['type'],
            content: string,
            fingerprint?: string,
        ) => {
            const finalFingerprint = fingerprint || content;

            if (fingerprintRef.current === finalFingerprint) {
                return;
            }

            lastFingerprint = finalFingerprint;
            fingerprintRef.current = finalFingerprint;

            const options = { position: 'top-center' as const };

            switch (type) {
                case 'success':
                    toast.success(content, options);
                    break;
                case 'error':
                    toast.error(content, options);
                    break;
                case 'info':
                    toast.info(content, options);
                    break;
                case 'warning':
                    toast.warning(content, options);
                    break;
            }
        };

        // 1. Handle prop message if provided (highest priority)
        if (propMessage?.content) {
            showToast(propMessage.type, propMessage.content);

            return;
        }

        // 2. Handle flash messages
        if (flash?.success) {
            showToast('success', flash.success);

            return;
        }

        if (flash?.error) {
            showToast('error', flash.error);

            return;
        }

        // 3. Handle validation errors
        const errorKeys = Object.keys(errors);

        if (errorKeys.length > 0) {
            // Create a unique fingerprint for this set of errors
            const errorFingerprint = `errors-${errorKeys.join(',')}-${Object.values(errors).join(',')}`;
            showToast(
                'error',
                'Validation failed. Please check the form.',
                errorFingerprint,
            );
        }
    }, [propMessage, flash, errors]);

    return null;
}
