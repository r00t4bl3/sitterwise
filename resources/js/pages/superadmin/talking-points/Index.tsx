import { Head, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Talking Points',
        href: '#',
    },
];

interface TalkingPoint {
    id: number;
    label: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
}

interface Props {
    [key: string]: unknown;
    talkingPoints: TalkingPoint[];
}

function SortableRow({
    point,
    index,
    onEdit,
    onDelete,
}: {
    point: TalkingPoint;
    index: number;
    onEdit: (p: TalkingPoint) => void;
    onDelete: (id: number) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: point.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <tr
            ref={setNodeRef}
            style={style}
            className="border-b border-border transition hover:bg-blush"
        >
            <td className="w-10 px-2 py-3 text-center">
                <button
                    type="button"
                    className="cursor-grab touch-none text-muted-foreground hover:text-foreground"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="h-4 w-4" />
                </button>
            </td>
            <td className="px-4 py-3 text-sm text-muted-foreground">
                {index + 1}
            </td>
            <td className="px-4 py-3 text-sm font-medium text-foreground">
                {point.label}
            </td>
            <td className="max-w-xs truncate px-4 py-3 text-sm text-muted-foreground">
                {point.description || '\u2014'}
            </td>
            <td className="px-4 py-3">
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        point.is_active
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-800'
                    }`}
                >
                    {point.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td className="flex justify-end gap-x-2 px-4 py-3">
                <Button onClick={() => onEdit(point)} className="h-8">
                    Edit
                </Button>
                <Button
                    variant="secondary"
                    onClick={() => onDelete(point.id)}
                    className="h-8"
                >
                    Delete
                </Button>
            </td>
        </tr>
    );
}

export default function TalkingPointsIndex() {
    const { talkingPoints: initialTalkingPoints } = usePage<Props>().props;

    const [points, setPoints] = useState<TalkingPoint[]>(initialTalkingPoints);
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [formLabel, setFormLabel] = useState('');
    const [formDescription, setFormDescription] = useState('');
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [saving, setSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const token =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const openCreateSheet = () => {
        setEditingId(null);
        setFormLabel('');
        setFormDescription('');
        setIsSheetOpen(true);
    };

    const openEditSheet = (point: TalkingPoint) => {
        setEditingId(point.id);
        setFormLabel(point.label);
        setFormDescription(point.description || '');
        setIsSheetOpen(true);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        try {
            if (editingId) {
                const res = await fetch(`/talking-points/${editingId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ label: formLabel, description: formDescription }),
                });
                const updated: TalkingPoint = await res.json();
                setPoints((prev) =>
                    prev.map((p) => (p.id === editingId ? updated : p)),
                );
            } else {
                const res = await fetch('/talking-points', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ label: formLabel, description: formDescription }),
                });
                const created: TalkingPoint = await res.json();
                setPoints((prev) => [...prev, created]);
            }

            setIsSheetOpen(false);
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        setIsDialogOpen(true);
    };

    const handleConfirmDelete = async () => {
        if (!deletingId) {
            return;
        }

        const id = deletingId;
        setPoints((prev) => prev.filter((p) => p.id !== id));
        setIsDialogOpen(false);
        setDeletingId(null);

        try {
            await fetch(`/talking-points/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token },
            });
        } catch {
            // Refetch on failure
            window.location.reload();
        }
    };

    const handleDragEnd = useCallback(
        async (event: DragEndEvent) => {
            const { active, over } = event;
            if (!over || active.id === over.id) {
                return;
            }

            const oldIndex = points.findIndex((p) => p.id === active.id);
            const newIndex = points.findIndex((p) => p.id === over.id);
            if (oldIndex === -1 || newIndex === -1) {
                return;
            }

            const reordered = [...points];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            setPoints(reordered);

            await fetch('/talking-points/reorder', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: reordered.map((p) => p.id) }),
            });
        },
        [points, token],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Talking Points" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Interview Talking Points
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Predefined checklist that admins check off during
                            caregiver interviews
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Talking Point</Button>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="w-10 px-2 py-3" />
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    #
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Label
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Description
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Active
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragEnd={handleDragEnd}
                        >
                            <SortableContext
                                items={points.map((p) => p.id)}
                                strategy={verticalListSortingStrategy}
                            >
                                <tbody>
                                    {points.map((point, index) => (
                                        <SortableRow
                                            key={point.id}
                                            point={point}
                                            index={index}
                                            onEdit={openEditSheet}
                                            onDelete={handleDelete}
                                        />
                                    ))}
                                    {points.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-8 text-center text-sm text-muted-foreground"
                                            >
                                                No talking points yet. Add your
                                                first one to get started.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </SortableContext>
                        </DndContext>
                    </table>
                </div>

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>
                                {editingId
                                    ? 'Edit Talking Point'
                                    : 'Add Talking Point'}
                            </SheetTitle>
                            <SheetDescription>
                                Define a topic that admins should cover during
                                caregiver interviews.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-4 px-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="label">Label</Label>
                                <Input
                                    id="label"
                                    value={formLabel}
                                    onChange={(e) =>
                                        setFormLabel(e.target.value)
                                    }
                                    placeholder="e.g. Discuss weekend availability"
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    Description (optional)
                                </Label>
                                <Textarea
                                    id="description"
                                    value={formDescription}
                                    onChange={(e) =>
                                        setFormDescription(e.target.value)
                                    }
                                    placeholder="Guidance for the interviewer"
                                />
                            </div>
                            <div className="mt-10 w-full space-y-2">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={saving}
                                >
                                    {saving ? 'Saving...' : 'Save'}
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

                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Confirm Delete</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this talking
                                point? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setIsDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button onClick={handleConfirmDelete}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
