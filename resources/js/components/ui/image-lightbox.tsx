import { X } from 'lucide-react';
import * as React from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Click-to-zoom image preview. Renders its children as the trigger
 * (anything clickable — a thumbnail, an avatar, an icon button) and opens
 * a Dialog with the image displayed at viewport-friendly size when clicked.
 *
 * Designed for any "thumbnail in a list → full preview" pattern across
 * the app: ticket category artwork, sponsor logos, lineup photos, gallery
 * items, etc.
 *
 * Behaviour notes:
 *   - Native `<img>` is used so browsers handle decoding + caching.
 *   - The dialog auto-sizes to the image's intrinsic aspect, capped at
 *     90vw / 80vh so portrait + landscape both display sensibly.
 *   - Escape / backdrop click closes.
 *   - When `src` is null/empty, the trigger remains visible but tapping it
 *     does nothing — caller can decide whether to render a fallback inside.
 */
export function ImageLightbox({
    src,
    alt,
    caption,
    children,
}: {
    src: string | null | undefined;
    alt?: string;
    caption?: React.ReactNode;
    children: React.ReactNode;
}) {
    const [open, setOpen] = React.useState(false);
    const hasImage = !!src;

    return (
        <>
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    if (hasImage) setOpen(true);
                }}
                disabled={!hasImage}
                aria-label={hasImage ? `View ${alt ?? 'image'}` : 'No image'}
                className={cn(
                    'rounded-md text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    hasImage && 'cursor-zoom-in hover:opacity-80',
                    !hasImage && 'cursor-default',
                )}
            >
                {children}
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent
                    className="max-w-[min(90vw,1100px)] gap-0 overflow-hidden p-0 sm:max-w-[min(90vw,1100px)]"
                    showCloseButton={false}
                >
                    {/* Visually hidden title so the dialog is accessible
                        while we keep the chrome minimal. */}
                    <DialogTitle className="sr-only">
                        {alt ?? 'Image preview'}
                    </DialogTitle>

                    <div className="relative bg-black">
                        {hasImage ? (
                            <img
                                src={src!}
                                alt={alt ?? ''}
                                className="block max-h-[80vh] w-full object-contain"
                                loading="eager"
                                onError={(e) => {
                                    e.currentTarget.style.display = 'none';
                                }}
                            />
                        ) : null}

                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="absolute top-2 right-2 inline-flex size-9 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-black/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="Close preview"
                        >
                            <X className="size-5" />
                        </button>
                    </div>

                    {caption ? (
                        <div className="border-t bg-card px-4 py-2 text-xs text-muted-foreground">
                            {caption}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}
