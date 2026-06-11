import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Pet {
    tempId: string;
    name: string;
    type: string;
    breed: string;
    notes: string;
}

interface BookingPetsSectionProps {
    pets: Pet[];
    onAdd: () => void;
    onRemove: (tempId: string) => void;
    onUpdate: (tempId: string, field: string, value: string) => void;
    petTypes: Array<{ value: string; label: string }>;
}

export function BookingPetsSection({
    pets,
    onAdd,
    onRemove,
    onUpdate,
    petTypes,
}: BookingPetsSectionProps) {
    return (
        <div>
            <div className="flex items-center justify-between">
                <Label className="text-sm font-medium text-foreground">
                    Pets
                </Label>
                <Button type="button" onClick={onAdd} size="xs">
                    <Plus className="h-3 w-3" />
                    Add Pet
                </Button>
            </div>
            <div className="mt-1 grid gap-4">
                {pets.map((pet) => (
                    <div
                        key={pet.tempId}
                        className="rounded-lg border bg-card p-4"
                    >
                        <div className="mb-3 flex items-start justify-between">
                            <p className="text-sm font-medium text-foreground">
                                {pet.name || 'Add New Pet'}
                            </p>
                            <Button
                                type="button"
                                onClick={() =>
                                    onRemove(pet.tempId)
                                }
                                size="sm"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-1">
                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                    Name
                                </Label>
                                <Input
                                    value={pet.name}
                                    onChange={(e) =>
                                        onUpdate(
                                            pet.tempId,
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Name"
                                />
                            </div>
                            <div>
                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                    Type
                                </Label>
                                <Select
                                    value={pet.type || ''}
                                    onValueChange={(value) =>
                                        onUpdate(
                                            pet.tempId,
                                            'type',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {petTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {pet.type === 'dog' && (
                                <div>
                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                        Breed
                                    </Label>
                                    <Input
                                        value={pet.breed || ''}
                                        onChange={(e) =>
                                            onUpdate(
                                                pet.tempId,
                                                'breed',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Breed"
                                    />
                                </div>
                            )}
                            <div>
                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                    Notes
                                </Label>
                                <Input
                                    value={pet.notes || ''}
                                    onChange={(e) =>
                                        onUpdate(
                                            pet.tempId,
                                            'notes',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Notes"
                                />
                            </div>
                        </div>
                    </div>
                ))}
                {pets.length === 0 && (
                    <div className="rounded-lg border border-dashed bg-card/50 p-8 text-center">
                        <p className="text-sm text-muted-foreground">
                            No pets added
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
