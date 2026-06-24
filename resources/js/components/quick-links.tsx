import {
    ExternalLink,
    Link,
    Globe,
    FileText,
    BookOpen,
    Phone,
    Mail,
    HelpCircle,
    Home,
    Calendar,
    Users,
    ChevronRight,
} from 'lucide-react';

const iconMap: Record<string, typeof Link> = {
    ExternalLink,
    Link,
    Globe,
    FileText,
    BookOpen,
    Phone,
    Mail,
    HelpCircle,
    Home,
    Calendar,
    Users,
};

interface QuickLink {
    id: number;
    title: string;
    url: string;
    description: string | null;
    icon: string | null;
    is_external: boolean;
}

interface Props {
    links: QuickLink[];
}

export default function QuickLinks({ links }: Props) {
    if (!links || links.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-4">
            <h3 className="text-lg leading-none font-semibold tracking-tight">
                Quick Links
            </h3>
            <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                <div className="p-6">
                    <div className="space-y-2">
                        {links.map((link) => {
                            const Icon = iconMap[link.icon ?? 'Link'] ?? Link;

                            return (
                                <a
                                    key={link.id}
                                    href={link.url}
                                    target={
                                        link.is_external ? '_blank' : '_self'
                                    }
                                    rel={
                                        link.is_external
                                            ? 'noopener noreferrer'
                                            : ''
                                    }
                                    className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-100">
                                            <Icon className="h-4 w-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {link.title}
                                            </p>
                                            {link.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {link.description}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                </a>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
