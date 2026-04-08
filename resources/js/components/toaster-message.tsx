import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export interface Message {
    type: 'success' | 'error' | 'info' | 'warning';
    content: string;
}

interface ToasterMessageProps {
    message?: Message | null;
}

export function ToasterMessage({ message }: ToasterMessageProps) {
    const previousMessageRef = useRef<string | null>(null);

    useEffect(() => {
        // Handle prop messages
        if (message && message.content !== previousMessageRef.current) {
            previousMessageRef.current = message.content;

            switch (message.type) {
                case 'success':
                    toast.success(message.content, { position: 'top-center' });
                    break;
                case 'error':
                    toast.error(message.content, { position: 'top-center' });
                    break;
                case 'info':
                    toast.info(message.content, { position: 'top-center' });
                    break;
                case 'warning':
                    toast.warning(message.content, { position: 'top-center' });
                    break;
            }
        }
    }, [message]);

    return null;
}
