import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
}

interface ClientInfoPanelProps {
    client: {
        id: number;
        biography: string | null;
        favorite_caregivers?: Caregiver[];
        blocked_caregivers?: Caregiver[];
        previous_caregivers?: Caregiver[];
    };
}

function CaregiverNames({ caregivers, variant = 'default' }: { caregivers: Caregiver[]; variant?: 'default' | 'blocked' }) {
    const textClass = variant === 'blocked' ? 'text-red-700' : 'text-foreground';

    return (
        <span className="text-sm">
            {caregivers.map((cg, index) => (
                <span key={cg.id}>
                    <Link
                        href={`/caregivers/${cg.id}`}
                        className={`hover:underline ${textClass}`}
                    >
                        {cg.first_name} {cg.last_name}
                    </Link>
                    {index < caregivers.length - 1 ? ', ' : ''}
                </span>
            ))}
        </span>
    );
}

export function ClientInfoPanel({ client }: ClientInfoPanelProps) {
    const [isHovered, setIsHovered] = useState(false);

    if (!client) {
        return null;
    }

    const hasFavorite = client.favorite_caregivers && client.favorite_caregivers.length > 0;
    const hasBlocked = client.blocked_caregivers && client.blocked_caregivers.length > 0;
    const hasPrevious = client.previous_caregivers && client.previous_caregivers.length > 0;

    return (
        <div
            className="mb-4 space-y-3 rounded-[3px] border border-border bg-card p-4 relative"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            {isHovered && (
                <Button size="sm" variant="outline" asChild className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                    <Link href={`/clients/${client.id}`}>Edit on client page</Link>
                </Button>
            )}
            {client.biography && (
                <div>
                    <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Biography
                    </p>
                    <p className="text-sm text-foreground">{client.biography}</p>
                </div>
            )}

            <div>
                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Previous Caregivers
                </p>
                <div>
                    {hasPrevious ? (
                        <CaregiverNames caregivers={client.previous_caregivers ?? []} />
                    ) : (
                        <p className="text-sm text-muted-foreground">None</p>
                    )}
                </div>
            </div>

            <div>
                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Favorite Caregivers
                </p>
                <div>
                    {hasFavorite ? (
                        <CaregiverNames caregivers={client.favorite_caregivers ?? []} />
                    ) : (
                        <p className="text-sm text-muted-foreground">None</p>
                    )}
                </div>
            </div>

            <div>
                <p className="text-xs font-semibold text-red-600 uppercase tracking-wider">
                    Blocked Caregivers
                </p>
                <div>
                    {hasBlocked ? (
                        <CaregiverNames caregivers={client.blocked_caregivers ?? []} variant="blocked" />
                    ) : (
                        <p className="text-sm text-muted-foreground">None</p>
                    )}
                </div>
            </div>
        </div>
    );
}
