import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pricing Rules',
        href: '#',
    },
];

interface PricingRule {
    id: number;
    service_type: string;
    number_of_children: number | null;
    is_for_pets: boolean;
    charge_to_client: number;
    charge_to_client_notes: string | null;
    paid_to_caregiver: number;
    payment_form: string;
    sitterwise_cut: number;
}

interface Props {
    [key: string]: unknown;
    pricingRules: PricingRule[];
    serviceTypes: Array<{ value: string; label: string }>;
}

export default function PricingRulesIndex() {
    const { pricingRules, serviceTypes } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const form = useForm<{
        service_type: string;
        number_of_children: string;
        is_for_pets: boolean;
        charge_to_client: number | null;
        charge_to_client_notes: string;
        paid_to_caregiver: number | null;
        payment_form: string;
        sitterwise_cut: number | null;
    }>({
        service_type: '',
        number_of_children: '',
        is_for_pets: false,
        charge_to_client: null,
        charge_to_client_notes: '',
        paid_to_caregiver: null,
        payment_form: '',
        sitterwise_cut: null,
    });

    const serviceTypeOptions = serviceTypes;
    const paymentFormOptions = ['Stripe', 'OnPay (Payroll)'];

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData({
            service_type: '',
            number_of_children: '',
            is_for_pets: false,
            charge_to_client: null,
            charge_to_client_notes: '',
            paid_to_caregiver: null,
            payment_form: '',
            sitterwise_cut: null,
        });
        setIsSheetOpen(true);
    };

    const openEditSheet = (rule: PricingRule) => {
        setEditingId(rule.id);
        form.setData({
            service_type: rule.service_type,
            number_of_children: rule.number_of_children?.toString() || '',
            is_for_pets: rule.is_for_pets,
            charge_to_client: rule.charge_to_client ?? null,
            charge_to_client_notes: rule.charge_to_client_notes || '',
            paid_to_caregiver: rule.paid_to_caregiver ?? null,
            payment_form: rule.payment_form,
            sitterwise_cut: rule.sitterwise_cut ?? null,
        });
        setIsSheetOpen(true);
    };

    const getServiceTypeLabel = (value: string): string => {
        const serviceType = serviceTypeOptions.find(
            (option) => option.value === value,
        );

        return serviceType ? serviceType.label : value;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Convert number inputs from string to number
        const dataToSend = {
            ...form.data,
            number_of_children: form.data.number_of_children
                ? parseInt(form.data.number_of_children)
                : null,
            charge_to_client: form.data.charge_to_client ?? 0,
            paid_to_caregiver: form.data.paid_to_caregiver ?? 0,
            sitterwise_cut: form.data.sitterwise_cut ?? 0,
        };

        if (editingId) {
            form.patch(`/pricing-rules/${editingId}`, {
                ...dataToSend, // Pass converted data
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/pricing-rules', {
                ...dataToSend, // Pass converted data
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const handleDeleteClick = (id: number) => {
        setDeletingId(id);
        setIsDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deletingId) {
            form.delete(`/pricing-rules/${deletingId}`);
            setIsDialogOpen(false);
            setDeletingId(null);
        }
    };

    const handleCancelDelete = () => {
        setIsDialogOpen(false);
        setDeletingId(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pricing Rules" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Pricing Rules
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage pricing rules for different services
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Pricing Rule</Button>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Service Type
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Children
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    For Pets
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Client Charge
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Caregiver Pay
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Payment Form
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Sitterwise Cut
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {pricingRules.map((rule) => (
                                <tr
                                    key={rule.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {getServiceTypeLabel(rule.service_type)}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {rule.is_for_pets
                                            ? 'N/A'
                                            : (rule.number_of_children ??
                                              'N/A')}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {rule.is_for_pets ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        ${rule.charge_to_client}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        ${rule.paid_to_caregiver}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {rule.payment_form}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        ${rule.sitterwise_cut}
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(rule)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDeleteClick(rule.id)
                                            }
                                            className="h-8"
                                        >
                                            Delete
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent
                        side="right"
                        className="flex w-full flex-col sm:max-w-lg"
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {editingId
                                    ? 'Edit Pricing Rule'
                                    : 'Add Pricing Rule'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a pricing rule.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="flex-grow space-y-4 overflow-y-auto px-4"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="service_type">
                                    Service Type
                                </Label>
                                <Select
                                    value={form.data.service_type}
                                    onValueChange={(value) =>
                                        form.setData('service_type', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a service type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {serviceTypeOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.service_type && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.service_type}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="number_of_children">
                                    Number of Children
                                </Label>
                                <Input
                                    id="number_of_children"
                                    type="number"
                                    min="1"
                                    value={form.data.number_of_children}
                                    onChange={(e) =>
                                        form.setData(
                                            'number_of_children',
                                            e.target.value,
                                        )
                                    }
                                    disabled={form.data.is_for_pets} // Disable if for pets
                                />
                                {form.errors.number_of_children && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.number_of_children}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_for_pets"
                                    checked={form.data.is_for_pets}
                                    onCheckedChange={(checked: boolean) => {
                                        form.setData('is_for_pets', checked);

                                        if (checked) {
                                            form.setData(
                                                'number_of_children',
                                                '',
                                            ); // Clear if for pets
                                        }
                                    }}
                                />
                                <Label htmlFor="is_for_pets">
                                    Is For Pets?
                                </Label>
                                {form.errors.is_for_pets && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.is_for_pets}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="charge_to_client">
                                    Charge to Client ($)
                                </Label>
                                <Input
                                    id="charge_to_client"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.charge_to_client ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'charge_to_client',
                                            e.target.value === ''
                                                ? null
                                                : parseFloat(e.target.value),
                                        )
                                    }
                                />
                                {form.errors.charge_to_client && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.charge_to_client}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="charge_to_client_notes">
                                    Client Charge Notes
                                </Label>
                                <Textarea
                                    id="charge_to_client_notes"
                                    value={form.data.charge_to_client_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'charge_to_client_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                />
                                {form.errors.charge_to_client_notes && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.charge_to_client_notes}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="paid_to_caregiver">
                                    Paid to Caregiver ($)
                                </Label>
                                <Input
                                    id="paid_to_caregiver"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.paid_to_caregiver ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'paid_to_caregiver',
                                            e.target.value === ''
                                                ? null
                                                : parseFloat(e.target.value),
                                        )
                                    }
                                />
                                {form.errors.paid_to_caregiver && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.paid_to_caregiver}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment_form">
                                    Payment Form
                                </Label>
                                <Select
                                    value={form.data.payment_form}
                                    onValueChange={(value) =>
                                        form.setData('payment_form', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a payment form" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {paymentFormOptions.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.payment_form && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.payment_form}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="sitterwise_cut">
                                    Sitterwise Cut ($)
                                </Label>
                                <Input
                                    id="sitterwise_cut"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.sitterwise_cut ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'sitterwise_cut',
                                            e.target.value === ''
                                                ? null
                                                : parseFloat(e.target.value),
                                        )
                                    }
                                />
                                {form.errors.sitterwise_cut && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.sitterwise_cut}
                                    </p>
                                )}
                            </div>
                            <div className="mt-10 w-full space-y-2">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    {form.processing ? <Spinner /> : null}
                                    {form.processing ? 'Saving...' : 'Save'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    onClick={() => setIsSheetOpen(false)}
                                    className="mt-2 w-full"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Delete</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this pricing rule?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={handleCancelDelete}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirmDelete}
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {form.processing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
