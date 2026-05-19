import { CircleAlert } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export function FieldError({ message }: { message?: string | null }) {
    if (!message) {
        return null;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    aria-label={`Validation error: ${message}`}
                    className="rounded-full text-destructive focus:outline-none focus-visible:ring-2 focus-visible:ring-destructive/40"
                >
                    <CircleAlert className="size-4" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                align="end"
                className="max-w-xs bg-destructive text-white"
            >
                {message}
            </TooltipContent>
        </Tooltip>
    );
}
