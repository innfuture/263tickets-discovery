import { Badge } from '@/components/ui/badge';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

export type MediaItem = {
    id: number;
    type: string; // image | video
    url: string | null;
    caption: string | null;
    is_primary: boolean;
};

export function EventMediaCarousel({
    items,
    fallbackUrl,
    overlay,
}: {
    items: MediaItem[];
    fallbackUrl?: string | null;
    overlay?: React.ReactNode;
}) {
    // Build slide list: media items, or fallback to single banner
    const slides =
        items.length > 0
            ? items
            : fallbackUrl
              ? [
                    {
                        id: 0,
                        type: 'image',
                        url: fallbackUrl,
                        caption: null,
                        is_primary: true,
                    } as MediaItem,
                ]
              : [];

    if (slides.length === 0) {
        return (
            <div className="relative aspect-[5/2] w-full overflow-hidden rounded-xl bg-muted">
                <div className="size-full bg-gradient-to-br from-primary/30 via-primary/10 to-transparent" />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent" />
                {overlay}
            </div>
        );
    }

    if (slides.length === 1) {
        const slide = slides[0];
        return (
            <div className="relative aspect-[5/2] w-full animate-in overflow-hidden rounded-xl bg-muted duration-500 zoom-in-95 fade-in">
                {slide.url ? (
                    <img
                        src={slide.url}
                        alt={slide.caption ?? ''}
                        className="size-full object-cover"
                        onError={(e) => {
                            e.currentTarget.style.display = 'none';
                        }}
                    />
                ) : null}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent" />
                {overlay}
            </div>
        );
    }

    return (
        <Carousel
            opts={{ loop: true, align: 'start' }}
            className="animate-in duration-500 zoom-in-95 fade-in"
        >
            <CarouselContent>
                {slides.map((slide) => (
                    <CarouselItem key={slide.id}>
                        <div className="relative aspect-[5/2] w-full overflow-hidden rounded-xl bg-muted">
                            {slide.url ? (
                                <img
                                    src={slide.url}
                                    alt={slide.caption ?? ''}
                                    className="size-full object-cover"
                                    onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                    }}
                                />
                            ) : null}
                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent" />
                            {slide.caption ? (
                                <Badge
                                    variant="outline"
                                    className="absolute bottom-3 left-3 border-white/40 bg-black/40 text-white"
                                >
                                    {slide.caption}
                                </Badge>
                            ) : null}
                            {slide.is_primary && overlay}
                        </div>
                    </CarouselItem>
                ))}
            </CarouselContent>
            <CarouselPrevious />
            <CarouselNext />
        </Carousel>
    );
}
