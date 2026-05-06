import { useState, useEffect, useRef } from 'react';
import { X } from 'lucide-react';

interface AutocompleteProps {
    value: number | null;
    onChange: (id: number | null) => void;
    suggestions: Array<{ id: number; name: string; [key: string]: unknown }>;
    onSearch: (query: string) => void;
    placeholder: string;
    loading?: boolean;
    displayValue?: string;
    showAddNew?: boolean;
    onAddNew?: () => void;
    onItemClick?: (item: { id: number; name: string }) => void;
    renderItem?: (item: { id: number; name: string }) => React.ReactNode;
}

export function Autocomplete({
    value,
    onChange,
    suggestions,
    onSearch,
    placeholder,
    loading,
    displayValue,
    showAddNew,
    onAddNew,
    onItemClick,
    renderItem,
}: AutocompleteProps) {
    const [query, setQuery] = useState(displayValue ?? '');
    const [showSuggestions, setShowSuggestions] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (displayValue !== undefined) {
            setQuery(displayValue);
        }
    }, [displayValue]);

    useEffect(() => {
        if (suggestions.length > 0) {
            setShowSuggestions(true);
        }
    }, [suggestions]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                wrapperRef.current &&
                !wrapperRef.current.contains(event.target as Node)
            ) {
                setShowSuggestions(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleInputChange = (value: string) => {
        setQuery(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        if (!value.trim()) {
            onChange(null);

            return;
        }

        debounceRef.current = setTimeout(() => {
            onSearch(value);
        }, 100);
    };

    const handleItemClick = (item: { id: number; name: string }) => {
        if (onItemClick) {
            onItemClick(item);
        } else {
            onChange(item.id);
            setQuery(item.name);
        }
        setShowSuggestions(false);
    };

    return (
        <div ref={wrapperRef} className="relative">
            <input
                type="text"
                value={query ?? ''}
                onChange={(e) => handleInputChange(e.target.value)}
                onFocus={() =>
                    suggestions.length > 0 && setShowSuggestions(true)
                }
                placeholder={placeholder}
                className="h-11 w-full rounded-[3px] border border-input px-3 pr-10 text-sm outline-none focus:border-ring"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => {
                        setQuery('');
                        onChange(null);
                    }}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
            {showSuggestions && (
                <div className="absolute top-full right-0 left-0 z-[100] mt-1 max-h-60 overflow-auto rounded-[3px] border border-border bg-card shadow-md">
                    {loading ? (
                        <div className="p-3 text-sm text-muted-foreground">
                            Loading...
                        </div>
                    ) : suggestions.length === 0 ? (
                        <div>
                            {showAddNew && onAddNew ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onAddNew();
                                        setShowSuggestions(false);
                                    }}
                                    className="w-full px-3 py-2 text-left text-sm text-foreground hover:bg-accent"
                                >
                                    + Add new client
                                </button>
                            ) : (
                                <div className="p-3 text-sm text-muted-foreground">
                                    No results
                                </div>
                            )}
                        </div>
                    ) : (
                        suggestions.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => handleItemClick(item)}
                                className="w-full px-3 py-2 text-left text-sm text-foreground hover:bg-accent"
                            >
                                {renderItem ? renderItem(item) : item.name}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}