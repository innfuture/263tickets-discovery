import { router, usePage } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ImageDropzone from '@/components/image-dropzone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type MediaItem = {
    id: number;
    type: string;
    url: string | null;
    caption: string | null;
    is_primary: boolean;
};

export function EventMediaManager({
    eventSlug,
    items,
}: {
    eventSlug: string;
    items: MediaItem[];
}) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const uploadUrl = `/${teamSlug}/events/${eventSlug}/media`;

    const formRef = useRef<HTMLFormElement>(null);
    const [caption, setCaption] = useState('');
    const [clientError, setClientError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!formRef.current) return;

        const fileInput = formRef.current.querySelector<HTMLInputElement>(
            'input[name="image"]',
        );

        if (!fileInput?.files || fileInput.files.length === 0) {
            setClientError('Please choose an image to upload.');
            return;
        }

        const formData = new FormData(formRef.current);
        setUploading(true);
        router.post(uploadUrl, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setCaption('');
                setClientError(null);
                if (formRef.current) {
                    formRef.current.reset();
                }
            },
        });
    };

    const handleDelete = (id: number) => {
        if (!window.confirm('Remove this image from the gallery?')) {
            return;
        }
        router.delete(`${uploadUrl}/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-4">
            {items.length > 0 ? (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {items.map((item) => (
                        <div
                            key={item.id}
                            className="group relative aspect-video overflow-hidden rounded-md border bg-muted"
                        >
                            {item.url ? (
                                <img
                                    src={item.url}
                                    alt={item.caption ?? ''}
                                    className="size-full object-cover"
                                    onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                    }}
                                />
                            ) : null}
                            {item.is_primary ? (
                                <div className="absolute top-2 left-2 rounded bg-black/60 px-2 py-0.5 text-[10px] font-medium text-white">
                                    Primary
                                </div>
                            ) : null}
                            {item.caption ? (
                                <div className="absolute right-0 bottom-0 left-0 bg-gradient-to-t from-black/80 to-transparent p-2">
                                    <p className="line-clamp-1 text-xs text-white">
                                        {item.caption}
                                    </p>
                                </div>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => handleDelete(item.id)}
                                className="absolute top-2 right-2 rounded-full bg-destructive p-1.5 text-destructive-foreground opacity-0 transition group-hover:opacity-100"
                                aria-label="Remove image"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground italic">
                    No additional images yet. Add gallery images to showcase the
                    venue, prior events, or marketing photos.
                </p>
            )}

            <form
                ref={formRef}
                onSubmit={handleSubmit}
                className="space-y-3 rounded-md border border-dashed bg-muted/20 p-4"
                encType="multipart/form-data"
            >
                <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <ImagePlus className="size-4" />
                    Add gallery image
                </div>

                <ImageDropzone
                    name="image"
                    hasError={!!clientError}
                    onValidationError={setClientError}
                />

                <div className="grid gap-1.5">
                    <Label htmlFor="media-caption" className="text-xs">
                        Caption (optional)
                    </Label>
                    <Input
                        id="media-caption"
                        name="caption"
                        value={caption}
                        onChange={(e) => setCaption(e.target.value)}
                        maxLength={280}
                        placeholder="Backstage in 2024"
                    />
                </div>

                <Button type="submit" size="sm" disabled={uploading}>
                    <ImagePlus className="size-4" />
                    {uploading ? 'Uploading...' : 'Upload image'}
                </Button>
            </form>
        </div>
    );
}
