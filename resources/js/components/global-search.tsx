import { useState, useEffect, useRef } from 'react';
import { Search, X } from 'lucide-react';
import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

interface SearchResult {
    id: number;
    name: string;
    type: 'booking' | 'caregiver' | 'client';
    url: string;
    ulid?: string;
    corporate_id?: string;
}

function highlightText(text: string, query: string): React.ReactNode {
    if (!query.trim()) return text;

    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    const parts = text.split(regex);

    return parts.map((part, index) =>
        regex.test(part)
            ? <mark key={index} className="bg-yellow-200 dark:bg-yellow-800 rounded px-0.5">{part}</mark>
            : part
    );
}

export function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [showResults, setShowResults] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                wrapperRef.current &&
                !wrapperRef.current.contains(event.target as Node)
            ) {
                setShowResults(false);
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
            setResults([]);
            setShowResults(false);

            return;
        }

        debounceRef.current = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(value)}`);
                const data = await response.json();
                setResults(data);
                setShowResults(true);
            } catch (error) {
                console.error('Search failed:', error);
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);
    };

    const handleItemClick = (item: SearchResult) => {
        router.visit(item.url);
        setQuery('');
        setResults([]);
        setShowResults(false);
    };

    const clearSearch = () => {
        setQuery('');
        setResults([]);
        setShowResults(false);
    };

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'booking':
                return 'Booking';
            case 'caregiver':
                return 'Caregiver';
            case 'client':
                return 'Client';
            default:
                return type;
        }
    };

    const getTypeVariant = (type: string): 'default' | 'secondary' | 'outline' => {
        switch (type) {
            case 'booking':
                return 'default';
            case 'caregiver':
                return 'secondary';
            case 'client':
                return 'outline';
            default:
                return 'default';
        }
    };

    return (
        <div ref={wrapperRef} className="relative">
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => handleInputChange(e.target.value)}
                    onFocus={() => results.length > 0 && setShowResults(true)}
                    placeholder="Search bookings, caregivers, clients..."
                    className="h-9 w-full rounded-md border border-input bg-background pl-9 pr-8 text-sm outline-none focus:border-ring focus:ring-1 focus:ring-ring md:w-[200px] lg:w-[300px]"
                />
                {query && (
                    <button
                        type="button"
                        onClick={clearSearch}
                        className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>
            {showResults && (
                <div className="absolute right-0 top-full z-[100] mt-1 max-h-[400px] w-full min-w-[300px] overflow-auto rounded-md border border-border bg-card shadow-md md:w-[400px]">
                    {loading ? (
                        <div className="p-4 text-center text-sm text-muted-foreground">
                            Searching...
                        </div>
                    ) : results.length === 0 ? (
                        <div className="p-4 text-center text-sm text-muted-foreground">
                            No results found
                        </div>
                    ) : (
                        <div className="py-1">
                            {results.map((item) => (
                                <button
                                    key={`${item.type}-${item.id}`}
                                    type="button"
                                    onClick={() => handleItemClick(item)}
                                    className="flex w-full items-center justify-between px-4 py-3 text-left text-sm cursor-pointer hover:bg-accent"
                                >
                                    <div className="flex flex-col gap-1">
                                        <span className="font-medium">{highlightText(item.name, query)}</span>
                                        {item.type === 'booking' && item.ulid && (
                                            <span className="text-xs text-muted-foreground">
                                                Booking ID: {highlightText(item.ulid, query)}
                                            </span>
                                        )}
                                    </div>
                                    <Badge variant={getTypeVariant(item.type)}>
                                        {getTypeLabel(item.type)}
                                    </Badge>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
