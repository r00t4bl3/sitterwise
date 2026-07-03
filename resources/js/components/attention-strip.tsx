import { AlertTriangle, Calendar } from 'lucide-react';

interface AttentionItem {
    icon: 'AlertTriangle' | 'Calendar';
    title: string;
    description: string;
    actionLabel?: string;
    actionHref?: string;
}

interface AttentionStripProps {
    items: AttentionItem[];
}

const iconMap = {
    AlertTriangle,
    Calendar,
};

export default function AttentionStrip({ items }: AttentionStripProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="rounded-none border border-border bg-card px-6 py-4 shadow-sm">
            <p className="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                <AlertTriangle className="h-4 w-4 text-primary" />
                Needs Your Attention
            </p>
            <div className="space-y-3">
                {items.map((item, i) => {
                    const Icon = iconMap[item.icon] || AlertTriangle;

                    return (
                        <div key={i} className="flex items-start gap-3">
                            <div
                                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                                    item.icon === 'AlertTriangle'
                                        ? 'bg-amber-100'
                                        : 'bg-blue-100'
                                }`}
                            >
                                <Icon
                                    className={`h-4 w-4 ${
                                        item.icon === 'AlertTriangle'
                                            ? 'text-amber-600'
                                            : 'text-blue-600'
                                    }`}
                                />
                            </div>
                            <div className="flex-1 text-xs">
                                <p className="font-medium text-foreground">
                                    {item.title}
                                </p>
                                <p className="text-muted-foreground">
                                    {item.description}
                                </p>
                                {item.actionLabel && (
                                    <a
                                        href={item.actionHref}
                                        className="mt-1 inline-flex font-medium text-blue-600 hover:text-blue-700 hover:underline"
                                    >
                                        {item.actionLabel} →
                                    </a>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
