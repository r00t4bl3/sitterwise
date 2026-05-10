import { useState, useEffect, useRef } from 'react';
import { X } from 'lucide-react';

function highlightText(text: string, query: string): React.ReactNode {
    if (!query.trim()) {
        return text;
    }

    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    const parts = text.split(regex);

    return parts.map((part, index) =>
        regex.test(part) ? (
            <mark
                key={index}
                className="rounded bg-yellow-200 px-0.5 dark:bg-yellow-800"
            >
                {part}
            </mark>
        ) : (
            part
        ),
    );
}

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
    const [activeIndex, setActiveIndex] = useState(-1);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const suggestionsRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const totalItems = suggestions.length + (showAddNew && onAddNew ? 1 : 0);

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

    useEffect(() => {
        if (activeIndex < 0 || !suggestionsRef.current) return;
        const items = suggestionsRef.current.querySelectorAll('[data-suggestion]');
        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    const handleInputChange = (value: string) => {
        setQuery(value);
        setActiveIndex(-1);

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
        setActiveIndex(-1);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!showSuggestions || totalItems === 0) {
            if (e.key === 'Escape') {
                setShowSuggestions(false);
                setActiveIndex(-1);
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setActiveIndex((prev) =>
                    prev >= totalItems - 1 ? 0 : prev + 1,
                );
                break;

            case 'ArrowUp':
                e.preventDefault();
                setActiveIndex((prev) =>
                    prev <= 0 ? totalItems - 1 : prev - 1,
                );
                break;

            case 'Enter':
                e.preventDefault();
                if (activeIndex >= 0 && activeIndex < suggestions.length) {
                    handleItemClick(suggestions[activeIndex]);
                } else if (
                    activeIndex === suggestions.length &&
                    showAddNew &&
                    onAddNew
                ) {
                    onAddNew();
                    setShowSuggestions(false);
                    setActiveIndex(-1);
                }
                break;

            case 'Escape':
                e.preventDefault();
                setShowSuggestions(false);
                setActiveIndex(-1);
                break;
        }
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
                onKeyDown={handleKeyDown}
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
                <div
                    ref={suggestionsRef}
                    className="absolute top-full right-0 left-0 z-[100] mt-1 max-h-60 overflow-auto rounded-[3px] border border-border bg-card shadow-md"
                >
                    {loading ? (
                        <div className="p-3 text-sm text-muted-foreground">
                            Loading...
                        </div>
                    ) : suggestions.length === 0 ? (
                        <div>
                            {showAddNew && onAddNew ? (
                                <button
                                    type="button"
                                    data-suggestion
                                    onClick={() => {
                                        onAddNew();
                                        setShowSuggestions(false);
                                        setActiveIndex(-1);
                                    }}
                                    onMouseEnter={() =>
                                        setActiveIndex(suggestions.length)
                                    }
                                    className={`w-full px-3 py-2 text-left text-sm text-foreground ${
                                        activeIndex === suggestions.length
                                            ? 'bg-accent'
                                            : 'hover:bg-accent'
                                    }`}
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
                        suggestions.map((item, index) => (
                            <button
                                key={item.id}
                                type="button"
                                data-suggestion
                                onClick={() => handleItemClick(item)}
                                onMouseEnter={() => setActiveIndex(index)}
                                className={`w-full px-3 py-2 text-left text-sm text-foreground ${
                                    activeIndex === index
                                        ? 'bg-accent'
                                        : 'hover:bg-accent'
                                }`}
                            >
                                {renderItem ? renderItem(item) : highlightText(item.name, query)}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}