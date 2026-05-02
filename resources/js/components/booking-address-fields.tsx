import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    form: any;
    isAddressLocked?: boolean;
    addressValue?: string;
    onAddressLock?: (locked: boolean, addressValue?: string) => void;
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

export function BookingAddressFields({
    form,
    isAddressLocked = false,
    addressValue = '',
    onAddressLock,
}: Props) {
    const { props } = usePage();
    const googleApiKey = (props as any).google_places_api_key || '';
    const inputRef = useRef<HTMLInputElement>(null);
    const [inputValue, setInputValue] = useState('');
    const [predictions, setPredictions] = useState<Suggestion[]>([]);
    const [showPredictions, setShowPredictions] = useState(false);
    const [loading, setLoading] = useState(false);
    const autocompleteSuggestionRef = useRef<any>(null);

    useEffect(() => {
        if (!googleApiKey) {
            console.error('Google Places API key is missing');

            return;
        }

        if ((window as any).google?.maps?.places) {
            autocompleteSuggestionRef.current = (
                window as any
            ).google.maps.places.AutocompleteSuggestion;

            return;
        }

        const existingScript = document.querySelector(
            `script[src*="maps.googleapis.com/maps/api"]`,
        );

        if (existingScript) {
            const checkGoogle = setInterval(() => {
                if ((window as any).google?.maps?.places) {
                    autocompleteSuggestionRef.current = (
                        window as any
                    ).google.maps.places.AutocompleteSuggestion;
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
            autocompleteSuggestionRef.current = (
                window as any
            ).google.maps.places.AutocompleteSuggestion;
        };
        document.head.appendChild(script);
    }, [googleApiKey]);

    const handleInputChange = async (value: string) => {
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
            const response =
                await AutocompleteSuggestion.fetchAutocompleteSuggestions({
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
                setPredictions(response.suggestions as unknown as Suggestion[]);
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

            form.setData('address_line1', line1);
            form.setData('address_line2', line2);
            form.setData('address_city', city);
            form.setData('address_state', state);
            form.setData('address_zip', zip);

            const fullAddress =
                place.formattedAddress ||
                `${line1}${line2 ? `, ${line2}` : ''}, ${city}, ${state} ${zip}`;

            setInputValue('');
            setPredictions([]);
            setShowPredictions(false);
            onAddressLock?.(true, fullAddress);
        } catch (error) {
            console.error('Get place details error:', error);
        }
    };

    if (isAddressLocked) {
        return (
            <div className="space-y-3">
                <div>
                    <label className="text-sm font-medium text-foreground">
                        Address
                    </label>
                    <div className="mt-1 flex h-11 items-center gap-2 rounded-[3px] border border-input px-3 py-2 text-sm">
                        <span className="flex-1 text-foreground">
                            {addressValue}
                        </span>
                        <button
                            type="button"
                            onClick={() => {
                                form.setData('address_line1', '');
                                form.setData('address_line2', '');
                                form.setData('address_city', '');
                                form.setData('address_state', '');
                                form.setData('address_zip', '');
                                setInputValue('');
                                onAddressLock?.(false);
                            }}
                            className="text-xs text-ring hover:text-foreground"
                        >
                            Edit
                        </button>
                    </div>
                </div>
                <input type="hidden" value={form.data.address_line1 || ''} />
                <input type="hidden" value={form.data.address_line2 || ''} />
                <input type="hidden" value={form.data.address_city || ''} />
                <input type="hidden" value={form.data.address_state || ''} />
                <input type="hidden" value={form.data.address_zip || ''} />
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="relative">
                <label className="text-sm font-medium text-foreground">
                    Address <span className="text-red-500">*</span>
                </label>
                <input
                    ref={inputRef}
                    type="text"
                    value={inputValue}
                    onChange={(e) => handleInputChange(e.target.value)}
                    onFocus={() =>
                        predictions.length > 0 && setShowPredictions(true)
                    }
                    placeholder="Start typing address..."
                    className="mt-1 h-11 w-full rounded-[3px] border border-input px-3 text-sm"
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
                                key={
                                    suggestion.placePrediction?.place ||
                                    `prediction-${index}`
                                }
                                className="cursor-pointer px-3 py-2 text-sm hover:bg-muted"
                                onClick={() =>
                                    handleSelectPrediction(suggestion)
                                }
                            >
                                {suggestion.placePrediction?.text?.text}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <input type="hidden" value={form.data.address_line1 || ''} />
            <input type="hidden" value={form.data.address_line2 || ''} />
            <input type="hidden" value={form.data.address_city || ''} />
            <input type="hidden" value={form.data.address_state || ''} />
            <input type="hidden" value={form.data.address_zip || ''} />
        </div>
    );
}
