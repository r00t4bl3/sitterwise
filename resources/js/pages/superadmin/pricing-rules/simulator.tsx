import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pricing Rules',
        href: '/pricing-rules',
    },
    {
        title: 'Simulator',
        href: '#',
    },
];

interface PricingRule {
    id: number;
    service_type: string;
    number_of_children: number | null;
    is_for_pets: boolean;
    charge_to_client: number;
    paid_to_caregiver: number;
    payment_form: string;
    sitterwise_cut: number;
}

interface ServiceType {
    value: string;
    label: string;
}

interface SimulateResult {
    matched_rule: PricingRule | null;
    is_fallback: boolean;
    hourly: {
        charge_to_client: number;
        paid_to_caregiver: number;
        sitterwise_cut: number;
    } | null;
    totals: {
        charge_to_client: number;
        paid_to_caregiver: number;
        sitterwise_cut: number;
    } | null;
}

interface Props {
    [key: string]: unknown;
    pricingRules: PricingRule[];
    serviceTypes: ServiceType[];
    maxChildren: number;
}

export default function PricingSimulator() {
    const { pricingRules, serviceTypes, maxChildren } = usePage<Props>().props;

    const [serviceType, setServiceType] = useState('');
    const [numberOfChildren, setNumberOfChildren] = useState('');
    const [isForPets, setIsForPets] = useState(false);
    const [hours, setHours] = useState('');
    const [result, setResult] = useState<SimulateResult | null>(null);
    const [isCalculating, setIsCalculating] = useState(false);
    const [error, setError] = useState('');

    const handleCalculate = async () => {
        setError('');
        setResult(null);
        setIsCalculating(true);

        try {
            const payload: Record<string, unknown> = {
                service_type: serviceType,
                is_for_pets: isForPets,
                hours: parseFloat(hours) || 0,
            };

            if (numberOfChildren) {
                payload.number_of_children = parseInt(numberOfChildren);
            }

            const response = await fetch('/pricing-rules/simulator/calculate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const data = await response.json();
                setError(
                    data.errors
                        ? Object.values(data.errors).flat().join(', ')
                        : 'Calculation failed',
                );

                return;
            }

            const data: SimulateResult = await response.json();
            setResult(data);
        } catch {
            setError('An unexpected error occurred');
        } finally {
            setIsCalculating(false);
        }
    };

    const getServiceTypeLabel = (value: string): string => {
        const st = serviceTypes.find((s) => s.value === value);

        return st ? st.label : value;
    };

    // Gap analysis helpers
    const serviceTypesForGrid = serviceTypes;
    const childrenRange = Array.from(
        { length: maxChildren + 1 },
        (_, i) => i,
    );

    const findRule = (
        st: string,
        children: number | null,
        pets: boolean,
    ): PricingRule | undefined => {
        if (pets && st !== 'petsitter') return undefined;

        return pricingRules.find((rule) => {
            if (rule.service_type !== st) return false;
            if (pets) {
                return rule.is_for_pets && rule.number_of_children === null;
            }

            return (
                rule.number_of_children === children &&
                !rule.is_for_pets
            );
        });
    };

    const hasFallback = (st: string): boolean => {
        return pricingRules.some((rule) => rule.service_type === st);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pricing Simulator" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="font-serif text-2xl font-bold text-foreground">
                        Pricing Simulator
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Preview pricing breakdowns and identify gaps in your
                        pricing rules
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Calculator Panel */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Price Preview</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="service_type">
                                    Service Type
                                </Label>
                                <Select
                                    value={serviceType}
                                    onValueChange={setServiceType}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a service type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {serviceTypes.map((st) => (
                                            <SelectItem
                                                key={st.value}
                                                value={st.value}
                                            >
                                                {st.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="number_of_children">
                                    Number of Children
                                </Label>
                                <Input
                                    id="number_of_children"
                                    type="number"
                                    min="0"
                                    value={numberOfChildren}
                                    onChange={(e) =>
                                        setNumberOfChildren(e.target.value)
                                    }
                                    disabled={isForPets}
                                />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_for_pets"
                                    checked={isForPets}
                                    onCheckedChange={(checked: boolean) => {
                                        setIsForPets(checked);
                                        if (checked) {
                                            setNumberOfChildren('');
                                        }
                                    }}
                                />
                                <Label htmlFor="is_for_pets">
                                    Is For Pets?
                                </Label>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="hours">
                                    Number of Hours
                                </Label>
                                <Input
                                    id="hours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={hours}
                                    onChange={(e) =>
                                        setHours(e.target.value)
                                    }
                                />
                            </div>

                            <Button
                                className="w-full"
                                onClick={handleCalculate}
                                disabled={isCalculating || !serviceType || !hours}
                            >
                                {isCalculating ? <Spinner /> : null}
                                {isCalculating ? 'Calculating...' : 'Calculate'}
                            </Button>

                            {error ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Error</AlertTitle>
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            ) : null}

                            {result ? (
                                <div className="space-y-3 rounded-lg border border-border bg-muted p-4">
                                    {result.is_fallback ? (
                                        <Alert>
                                            <AlertTitle>
                                                No Exact Match
                                            </AlertTitle>
                                            <AlertDescription>
                                                No pricing rule matches this
                                                combination. Showing fallback
                                                rates from the first{' '}
                                                {getServiceTypeLabel(serviceType)}{' '}
                                                rule.
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    {result.matched_rule ? (
                                        <>
                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground uppercase">
                                                    Matched Rule
                                                </span>
                                                <p className="text-sm font-medium text-foreground">
                                                    {getServiceTypeLabel(
                                                        result.matched_rule.service_type,
                                                    )}
                                                    {result.matched_rule
                                                        .number_of_children !==
                                                    null
                                                        ? `, ${result.matched_rule.number_of_children} child${result.matched_rule.number_of_children !== 1 ? 'ren' : ''}`
                                                        : ''}
                                                    {result.matched_rule
                                                        .is_for_pets
                                                        ? ' (Pets)'
                                                        : ''}
                                                    {' — '}
                                                    {result.matched_rule.payment_form}
                                                </p>
                                            </div>

                                            <Separator />

                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground uppercase">
                                                    Hourly Rates
                                                </span>
                                                <div className="mt-1 space-y-1">
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Charge to Client
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .hourly!
                                                                .charge_to_client.toFixed(
                                                                    2,
                                                                )}
                                                            /hr
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Paid to Caregiver
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .hourly!
                                                                .paid_to_caregiver.toFixed(
                                                                    2,
                                                                )}
                                                            /hr
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Sitterwise Cut
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .hourly!
                                                                .sitterwise_cut.toFixed(
                                                                    2,
                                                                )}
                                                            /hr
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <Separator />

                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground uppercase">
                                                    Totals ({hours || 0} hours)
                                                </span>
                                                <div className="mt-1 space-y-1">
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Charge to Client
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .totals!
                                                                .charge_to_client.toFixed(
                                                                    2,
                                                                )}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Paid to Caregiver
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .totals!
                                                                .paid_to_caregiver.toFixed(
                                                                    2,
                                                                )}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-sm">
                                                        <span>
                                                            Sitterwise Cut
                                                        </span>
                                                        <span className="font-medium">
                                                            $
                                                            {result
                                                                .totals!
                                                                .sitterwise_cut.toFixed(
                                                                    2,
                                                                )}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <Separator />
                                        </>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No pricing rule found for this
                                            service type.
                                        </p>
                                    )}
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* Gap Analysis Panel */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Gap Analysis</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Shows which service type × children
                                combinations have pricing rules.
                            </p>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[600px]">
                                    <thead>
                                        <tr className="bg-table-header">
                                            <th className="px-3 py-2 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                                Service Type
                                            </th>
                                            {childrenRange.map((n) => (
                                                <th
                                                    key={n}
                                                    className="px-3 py-2 text-center text-[11px] font-semibold tracking-wider text-white uppercase"
                                                >
                                                    {n === 0 ? 'None' : n}
                                                </th>
                                            ))}
                                            <th className="px-3 py-2 text-center text-[11px] font-semibold tracking-wider text-white uppercase">
                                                Pets
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {serviceTypesForGrid.map((st) => (
                                            <tr
                                                key={st.value}
                                                className="border-b border-border transition hover:bg-blush"
                                            >
                                                <td className="px-3 py-2 text-sm font-medium text-foreground">
                                                    {st.label}
                                                </td>
                                                {childrenRange.map((n) => {
                                                    const rule = findRule(
                                                        st.value,
                                                        n,
                                                        false,
                                                    );

                                                    return (
                                                        <td
                                                            key={n}
                                                            className="px-3 py-2 text-center text-sm"
                                                        >
                                                            {rule ? (
                                                                <Badge className="bg-green-100 text-green-800 hover:bg-green-100">
                                                                    $
                                                                    {rule.charge_to_client}/
                                                                    $
                                                                    {rule.paid_to_caregiver}
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-red-400">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                                <td className="px-3 py-2 text-center text-sm">
                                                    {st.value ===
                                                    'petsitter' ? (
                                                        (() => {
                                                            const rule =
                                                                findRule(
                                                                    st.value,
                                                                    null,
                                                                    true,
                                                                );

                                                            return rule ? (
                                                                <Badge className="bg-green-100 text-green-800 hover:bg-green-100">
                                                                    $
                                                                    {rule.charge_to_client}/
                                                                    $
                                                                    {rule.paid_to_caregiver}
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-red-400">
                                                                    —
                                                                </span>
                                                            );
                                                        })()
                                                    ) : (
                                                        <span className="text-gray-300">
                                                            N/A
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="mt-2 text-xs text-muted-foreground">
                                Cell values: Charge to Client / Paid to Caregiver
                            </p>

                            {serviceTypesForGrid.some(
                                (st) =>
                                    !hasFallback(st.value),
                            ) ? (
                                <Alert className="mt-4">
                                    <AlertTitle>
                                        Missing Service Types
                                    </AlertTitle>
                                    <AlertDescription>
                                        {serviceTypesForGrid
                                            .filter(
                                                (st) =>
                                                    !hasFallback(st.value),
                                            )
                                            .map((st) => st.label)
                                            .join(', ')}{' '}
                                        have no pricing rules at all. Any
                                        booking with these service types
                                        will have null rates.
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
