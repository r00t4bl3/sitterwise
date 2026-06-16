import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, useId } from 'react';
import InputError from '@/components/input-error';

interface Props {
    form: any;
    isAddressLocked?: boolean;
    addressValue?: string;
    onAddressLock?: (locked: boolean, addressValue?: string) => void;
    errors?: Record<string, string>;
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

const SERVICE_AREA_CITIES = [
    'San Diego',
    'Coronado',
    'La Jolla',
    'Chula Vista',
    'El Cajon',
    'La Mesa',
    'Rancho Santa Fe',
    'Del Mar',
    'Carlsbad',
    'Encinitas',
    'Escondido',
    'San Marcos',
    'Vista',
];

export function BookingAddressFields({
    form,
    isAddressLocked = false,
    addressValue = '',
    onAddressLock,
    errors = {},
}: Props) {
    const { props } = usePage();
    const googleApiKey = (props as any).google_places_api_key || '';
    const inputRef = useRef<HTMLInputElement>(null);
    const listboxId = `baf-listbox-${useId()}`;
    const [inputValue, setInputValue] = useState('');
    const [predictions, setPredictions] = useState<Suggestion[]>([]);
    const [showPredictions, setShowPredictions] = useState(false);
    const [loading, setLoading] = useState(false);
    const [isVerified, setIsVerified] = useState(false);
    const [noPredictions, setNoPredictions] = useState(false);
    const [outsideServiceArea, setOutsideServiceArea] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
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
            const timeout = setTimeout(() => clearInterval(checkGoogle), 5000);

            return () => {
                clearInterval(checkGoogle);
                clearTimeout(timeout);
            };
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${googleApiKey}&libraries=places&loading=async`;
        script.onload = () => {
            const checkPlaces = setInterval(() => {
                if ((window as any).google?.maps?.places) {
                    autocompleteSuggestionRef.current = (
                        window as any
                    ).google.maps.places.AutocompleteSuggestion;
                    clearInterval(checkPlaces);
                }
            }, 100);
            setTimeout(() => clearInterval(checkPlaces), 5000);
        };
        document.head.appendChild(script);
    }, [googleApiKey]);

    const handleInputChange = async (value: string) => {
        setInputValue(value);
        setIsVerified(false);
        setNoPredictions(false);
        setOutsideServiceArea(false);

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

            const suggestions = response.suggestions || [];

            setPredictions(suggestions as unknown as Suggestion[]);
            setShowPredictions(suggestions.length > 0);
            setNoPredictions(suggestions.length === 0);
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

            const outsideArea =
                state !== 'CA' || !SERVICE_AREA_CITIES.includes(city);

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

            if (outsideArea) {
                setIsVerified(false);
                setOutsideServiceArea(true);
                onAddressLock?.(false);
            } else {
                setIsVerified(true);
                setOutsideServiceArea(false);
                onAddressLock?.(true, fullAddress);
            }
        } catch (error) {
            console.error('Get place details error:', error);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!showPredictions || predictions.length === 0) {
            if (e.key === 'Escape') {
                setShowPredictions(false);
                setActiveIndex(-1);
            }

            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setActiveIndex((prev) =>
                    prev >= predictions.length - 1 ? 0 : prev + 1,
                );
                break;
            case 'ArrowUp':
                e.preventDefault();
                setActiveIndex((prev) =>
                    prev <= 0 ? predictions.length - 1 : prev - 1,
                );
                break;
            case 'Enter':
                e.preventDefault();

                if (activeIndex >= 0 && activeIndex < predictions.length) {
                    handleSelectPrediction(predictions[activeIndex]);
                }

                break;
            case 'Escape':
                e.preventDefault();
                setShowPredictions(false);
                setActiveIndex(-1);
                break;
        }
    };

    const handleBlurAutoPick = () => {
        if (!inputValue.trim() || predictions.length === 0) {
            return;
        }

        const firstPrediction =
            predictions[0]?.placePrediction?.text?.text?.trim();
        const typed = inputValue.trim().toLowerCase();

        if (!firstPrediction) {
            return;
        }

        const predictionLower = firstPrediction.toLowerCase();

        const isMatch =
            predictionLower.startsWith(typed) ||
            predictionLower.includes(typed) ||
            typed.includes(predictionLower);

        if (isMatch) {
            handleSelectPrediction(predictions[0]);
        }
    };

    if (isAddressLocked) {
        return (
            <div className="space-y-3">
                <div>
                    <label className="text-sm font-medium text-foreground">
                        Address <span className="text-red-500">*</span>
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
                                setIsVerified(false);
                                setOutsideServiceArea(false);
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
                <p className="mt-1 text-xs text-muted-foreground">
                    We currently serve the San Diego area.
                </p>
                {errors.address_line1 && (
                    <InputError message={errors.address_line1} />
                )}
                {errors.address_city && (
                    <InputError message={errors.address_city} />
                )}
                {errors.address_state && (
                    <InputError message={errors.address_state} />
                )}
                {errors.address_zip && (
                    <InputError message={errors.address_zip} />
                )}
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
                    role="combobox"
                    aria-required="true"
                    aria-expanded={showPredictions}
                    aria-controls={listboxId}
                    aria-activedescendant={
                        activeIndex >= 0
                            ? `${listboxId}-option-${activeIndex}`
                            : undefined
                    }
                    aria-autocomplete="list"
                    value={inputValue}
                    onChange={(e) => handleInputChange(e.target.value)}
                    onFocus={() =>
                        predictions.length > 0 && setShowPredictions(true)
                    }
                    onBlur={() => {
                        setTimeout(() => {
                            setShowPredictions(false);
                            handleBlurAutoPick();
                        }, 200);
                    }}
                    onKeyDown={handleKeyDown}
                    placeholder="Start typing address..."
                    className={`mt-1 h-11 w-full rounded-[3px] border px-3 text-sm ${
                        !isVerified && inputValue.trim()
                            ? 'border-amber-400'
                            : 'border-input'
                    }`}
                    autoComplete="off"
                />
                {loading && (
                    <div className="absolute top-9 right-3">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-600" />
                    </div>
                )}
                {showPredictions && predictions.length > 0 && (
                    <ul
                        id={listboxId}
                        role="listbox"
                        className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-[3px] border border-input bg-background shadow-lg"
                    >
                        {predictions.map((suggestion, index) => (
                            <li
                                key={
                                    suggestion.placePrediction?.place ||
                                    `prediction-${index}`
                                }
                                role="option"
                                aria-selected={activeIndex === index}
                                id={`${listboxId}-option-${index}`}
                                className={`cursor-pointer px-3 py-2 text-sm ${
                                    activeIndex === index
                                        ? 'bg-muted'
                                        : 'hover:bg-muted'
                                }`}
                                onClick={() =>
                                    handleSelectPrediction(suggestion)
                                }
                                onMouseEnter={() => setActiveIndex(index)}
                            >
                                {suggestion.placePrediction?.text?.text}
                            </li>
                        ))}
                    </ul>
                )}
                {noPredictions && !loading && inputValue.trim() && (
                    <p className="mt-1 text-xs text-destructive">
                        This address appears to be outside our service area.
                    </p>
                )}
                {outsideServiceArea && (
                    <p className="mt-1 text-xs text-destructive">
                        This address is outside our service area. We currently
                        serve the San Diego area.
                    </p>
                )}
                <p className="mt-1 text-xs text-muted-foreground">
                    We currently serve the San Diego area.
                </p>
            </div>
            {!isVerified && inputValue.trim() && !errors.address_line1 && (
                <p className="mt-1 text-xs text-amber-600">
                    Please select an address from the suggestions.
                </p>
            )}
            {errors.address_line1 && (
                <InputError message={errors.address_line1} />
            )}
            {errors.address_city && (
                <InputError message={errors.address_city} />
            )}
            {errors.address_state && (
                <InputError message={errors.address_state} />
            )}
            {errors.address_zip && <InputError message={errors.address_zip} />}
            <input type="hidden" value={form.data.address_line1 || ''} />
            <input type="hidden" value={form.data.address_line2 || ''} />
            <input type="hidden" value={form.data.address_city || ''} />
            <input type="hidden" value={form.data.address_state || ''} />
            <input type="hidden" value={form.data.address_zip || ''} />
        </div>
    );
}
