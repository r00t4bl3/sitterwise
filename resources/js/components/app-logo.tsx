import { useSidebar } from '@/components/ui/sidebar';

export default function AppLogo() {
    const { state } = useSidebar();

    return (
        <div className="flex items-center justify-center rounded-md bg-transparent">
            <img
                src={state === 'collapsed' ? '/submark.png' : '/sitterwise.png'}
                alt="Sitterwise"
                className="h-10 w-auto object-contain"
            />
        </div>
    );
}
