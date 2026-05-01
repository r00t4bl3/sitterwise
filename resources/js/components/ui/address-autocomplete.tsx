import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Label } from './label';
import { Button } from './button';
import { Input } from './input';

interface Props {
    form: any;
    label?: string;
    prefix?: string;
}

interface Suggestion {
    placePrediction?: {
        place: string;
        text: {
            text: string;
        };
        toPlace: () => {
            fetchFields: (options: { fields: string[] }) => Promise<void>;
            addressComponents: Array<{
                longName: string;
                shortName: string;
                types: string[];
            }>;
            formattedAddress: string;
        };
    };
}

export function AddressAutocomplete({ form, label = 'Address', prefix = 'address_' }: Props) {
    const getIndex = (): number | null => {
        const match = prefix.match(/^addresses\.(\d+)\.$/);
        return match ? parseInt(match[1], 10) : null;
    };

    const getField = (field: string): string => {
        const dotKey = prefix + field;
        if (form.data[dotKey] !== undefined) {
            return form.data[dotKey];
        }
        const idx = getIndex();
        if (idx !== null) {
            return form.data.addresses?.[idx]?.[field] || '';
        }
        return form.data[field] || '';
    };
    const { props } = usePage();
    const googleApiKey = (props as any).google_places_api_key || '';
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [inputValue, setInputValue] = useState('');
    const [predictions, setPredictions] = useState<Suggestion[]>([]);
    const [showPredictions, setShowPredictions] = useState(false);
    const [loading, setLoading] = useState(false);
    const [initialValue, setInitialValue] = useState('');
    const [addressValue, setAddressValue] = useState(() => {
        const parts = [
            getField('line1'),
            getField('line2'),
            getField('city'),
            getField('state'),
            getField('zip'),
        ].filter(Boolean);
        return parts.length > 0 ? parts.join(', ') : '';
    });
    const [isEditing, setIsEditing] = useState(false);
    const [isLocked, setIsLocked] = useState(!!getField('line1'));
    const autocompleteSuggestionRef = useRef<any>(null);
    const formRef = useRef(form);

    // Keep formRef current to avoid stale closures
    useEffect(() => {
        formRef.current = form;
    }, [form]);

    // Update addressValue when form data changes
    useEffect(() => {
        const line1 = getField('line1');
        const line2 = getField('line2');
        const city = getField('city');
        const state = getField('state');
        const zip = getField('zip');
        const parts = [line1, line2, city, state, zip].filter(Boolean);
        const newValue = parts.length > 0 ? parts.join(', ') : '';
        setAddressValue(newValue);
        setIsLocked(!!line1);
    }, [form.data.addresses]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                if (showPredictions) {
                    setShowPredictions(false);
                    setInputValue('');
                    setInitialValue('');
                    setIsLocked(true);
                    setIsEditing(false);
                } else if (isEditing) {
                    setInputValue('');
                    setInitialValue('');
                    setIsEditing(false);
                    setIsLocked(true);
                }
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [showPredictions, isEditing]);

    useEffect(() => {
        if (!googleApiKey) {
            return;
        }

        if ((window as any).google?.maps?.places) {
            autocompleteSuggestionRef.current = (window as any).google.maps.places.AutocompleteSuggestion;
            return;
        }

        const existingScript = document.querySelector(`script[src*="maps.googleapis.com/maps/api"]`);

        if (existingScript) {
            const checkGoogle = setInterval(() => {
                if ((window as any).google?.maps?.places) {
                    autocompleteSuggestionRef.current = (window as any).google.maps.places.AutocompleteSuggestion;
                    clearInterval(checkGoogle);
                }
            }, 100);
            setTimeout(() => clearInterval(checkGoogle), 5000);
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${googleApiKey}&libraries=places`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            autocompleteSuggestionRef.current = (window as any).google.maps.places.AutocompleteSuggestion;
        };
        document.head.appendChild(script);
    }, [googleApiKey]);

    const handleInputChange = async (value: string) => {
        setInitialValue(value);
        setInputValue(value);

        if (!value.trim()) {
            setPredictions([]);
            setShowPredictions(false);
            return;
        }

        if (!autocompleteSuggestionRef.current) {
            return;
        }

        setLoading(true);

        try {
            const AutocompleteSuggestion = autocompleteSuggestionRef.current;
            const response = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                input: value,
                includedPrimaryTypes: ['street_address'],
                locationRestriction: {
                    north: 33.12,
                    south: 32.5,
                    east: -116.9,
                    west: -117.31,
                },
            });

            if (response.suggestions) {
                const filtered = response.suggestions.filter((s: any) => {
                    const text = s.placePrediction?.text?.text || '';
                    return text.includes('CA') || text.includes(', California');
                });
                setPredictions(filtered as unknown as Suggestion[]);
                setShowPredictions(true);
            }
        } catch (error) {
            console.error('Autocomplete error:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSelectPrediction = async (suggestion: Suggestion) => {
        if (!suggestion.placePrediction) {
            return;
        }

        try {
            const place = suggestion.placePrediction.toPlace();

            await place.fetchFields({
                fields: ['addressComponents', 'formattedAddress'],
            });

            const addressComponents = place.addressComponents;
            let line1 = '';
            let line2 = '';
            let city = '';
            let state = '';
            let zip = '';

            addressComponents?.forEach((component: any) => {
                const types = component.types;
                const longName = component.longText || component.longName;
                const shortName = component.shortText || component.shortName;

                if (types.includes('street_number')) {
                    line1 = longName;
                } else if (types.includes('route')) {
                    line1 = line1 ? `${line1} ${longName}` : longName;
                } else if (types.includes('subpremise')) {
                    line2 = longName;
                } else if (types.includes('locality')) {
                    city = longName;
                } else if (types.includes('administrative_area_level_1')) {
                    state = shortName;
                } else if (types.includes('postal_code')) {
                    zip = longName;
                }
            });

            const setField = (field: string, value: string) => {
                const idx = getIndex();
                if (idx !== null) {
                    const currentAddress = form.data.addresses?.[idx] || {};
                    const updatedAddress = { ...currentAddress, [field]: value };
                    const updatedAddresses = [...(form.data.addresses || [])];
                    updatedAddresses[idx] = updatedAddress;
                    form.setData('addresses', updatedAddresses);
                } else {
                    form.setData(prefix + field, value);
                }
            };

            // Update all address fields at once to avoid race conditions
            const idx = getIndex();
            const currentForm = formRef.current;
            
            if (idx !== null) {
                const currentAddress = currentForm.data.addresses?.[idx] || {};
                
                const updatedAddress = {
                    ...currentAddress,
                    line1: line1 || '',
                    line2: line2 || '',
                    city: city || '',
                    state: state || '',
                    zip: zip || '',
                };
                const updatedAddresses = [...(currentForm.data.addresses || [])];
                updatedAddresses[idx] = updatedAddress;
                currentForm.setData('addresses', updatedAddresses);
            } else {
                setField('line1', line1);
                setField('line2', line2);
                setField('city', city);
                setField('state', state);
                setField('zip', zip);
            }

            const fullAddress =
                place.formattedAddress ||
                `${line1}${line2 ? `, ${line2}` : ''}, ${city}, ${state} ${zip}`;

            setAddressValue(fullAddress);
            setIsLocked(true);
            setInputValue('');
            setPredictions([]);
            setShowPredictions(false);
        } catch (error) {
            console.error('Get place details error:', error);
        }
    };

    const handleUnlock = () => {
        setInputValue(addressValue);
        setInitialValue(addressValue);
        setIsEditing(true);
        setIsLocked(false);
    };

    if (isLocked) {
        return (
            <div className="space-y-3" ref={containerRef}>
                <Label>
                    {label}
                </Label>
                    <div className="mt-1 flex items-center gap-2 rounded-[3px] border border-input px-3 py-2 text-sm">
                        <span className="flex-1 text-foreground">{addressValue}</span>
                        <Button
                            variant="link"
                            type="button"
                            onClick={handleUnlock}
                            size="xs"
                        >
                            Edit
                        </Button>
                    </div>
            </div>
        );
    }

    return (
        <div className="space-y-3" ref={containerRef}>
            <div className="relative">
                <Label>
                    {label}
                </Label>
                <Input
                    ref={inputRef}
                        type="text"
                        value={inputValue}
                        onChange={(e) => handleInputChange(e.target.value)}
                        onFocus={() => predictions.length > 0 && setShowPredictions(true)}
                        placeholder="Start typing address..."
                        autoComplete="off"
                    />
                {loading && (
                    <div className="absolute top-9 right-3">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-600" />
                    </div>
                )}
                {showPredictions && predictions.length > 0 && (
                    <ul className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-[3px] border border-input bg-background shadow-lg">
                        {predictions.map((suggestion, index) => (
                            <li
                                key={suggestion.placePrediction?.place || `prediction-${index}`}
                                className="cursor-pointer px-3 py-2 text-sm hover:bg-muted"
                                onClick={() => handleSelectPrediction(suggestion)}
                            >
                                {suggestion.placePrediction?.text?.text}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}