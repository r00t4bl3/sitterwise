import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { useEffect, useRef } from 'react';

export function ToasterMessage() {
    const flash = (usePage().props as Record<string, unknown>).flash as Record<
        string,
        string
    > | null;

    const previousFlashRef = useRef<string | null>(null);

    useEffect(() => {
        const flashKey = flash?.success || flash?.error || null;

        if (flashKey && flashKey !== previousFlashRef.current) {
            previousFlashRef.current = flashKey;

            if (flash?.success) {
                toast.success(flash.success, { position: 'top-center' });
            }
            if (flash?.error) {
                toast.error(flash.error, { position: 'top-center' });
            }
        }
    }, [flash]);

    return null;
}
