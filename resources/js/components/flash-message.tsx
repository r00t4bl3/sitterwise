import { usePage } from '@inertiajs/react';
import { CheckCircle2, AlertCircle } from 'lucide-react';
import { toast } from "sonner"
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export function FlashMessage() {
    const flash = (usePage().props as Record<string, unknown>).flash as Record<
        string,
        string
    > | null;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    if (flash.success) {
        toast.success("Event has been created")
    }

    return (
        <>
            {flash?.success && (
                <Alert className="border-green-200 bg-green-50 text-green-800">
                    <CheckCircle2 className="h-4 w-4" />
                    <AlertTitle>Success</AlertTitle>
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}
            {flash?.error && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
            )}
        </>
    );
}
