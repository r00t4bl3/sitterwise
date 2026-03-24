import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center justify-center rounded-md bg-transparent">
            <img
                src="/sitterwise.png"
                alt="Sitterwise"
                className="h-10 w-auto object-contain"
            />
        </div>
    );
}
