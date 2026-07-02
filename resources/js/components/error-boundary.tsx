import { Component, type ErrorInfo, type ReactNode } from 'react';

interface ErrorBoundaryProps {
    children: ReactNode;
    fallback?: ReactNode;
}

interface ErrorBoundaryState {
    hasError: boolean;
}

/**
 * Catches render-time errors in its subtree and shows a contained message
 * instead of letting the throw blank the entire page (React unmounts the whole
 * tree on an uncaught render error).
 */
export class ErrorBoundary extends Component<
    ErrorBoundaryProps,
    ErrorBoundaryState
> {
    state: ErrorBoundaryState = { hasError: false };

    static getDerivedStateFromError(): ErrorBoundaryState {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('ErrorBoundary caught an error:', error, info);
    }

    render(): ReactNode {
        if (this.state.hasError) {
            return (
                this.props.fallback ?? (
                    <div className="rounded-md border border-destructive/20 bg-destructive/10 p-4 text-sm text-destructive">
                        Something went wrong displaying this section. Try
                        reopening it or refreshing the page; contact support if
                        it keeps happening.
                    </div>
                )
            );
        }

        return this.props.children;
    }
}
