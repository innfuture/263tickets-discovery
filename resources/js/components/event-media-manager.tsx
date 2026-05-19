import { router, usePage } from '@inertiajs/react';
import { ImagePlus, Trash2, Video } from 'lucide-react';
import { useRef, useState } from 'react';
import ImageDropzone from '@/components/image-dropzone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

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

    const handleDelete = (id: number) => {
        if (!window.confirm('Remove this from the gallery?')) {
            return;
        }

        router.delete(`${uploadUrl}/${id}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            {items.length > 0 ? (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {items.map((item) => (
                        <MediaTile
                            key={item.id}
                            item={item}
                            onDelete={() => handleDelete(item.id)}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground italic">
                    No media yet. Add images or videos that showcase the event.
                </p>
            )}

            <div className="rounded-md border border-dashed bg-muted/20 p-4">
                <Tabs defaultValue="image" className="gap-3">
                    <TabsList>
                        <TabsTrigger value="image">
                            <ImagePlus className="size-3.5" />
                            Image
                        </TabsTrigger>
                        <TabsTrigger value="video">
                            <Video className="size-3.5" />
                            Video URL
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="image" className="mt-0">
                        <ImageUploadForm uploadUrl={uploadUrl} />
                    </TabsContent>

                    <TabsContent value="video" className="mt-0">
                        <VideoUrlForm uploadUrl={uploadUrl} />
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    );
}

function MediaTile({
    item,
    onDelete,
}: {
    item: MediaItem;
    onDelete: () => void;
}) {
    return (
        <div className="group relative aspect-video overflow-hidden rounded-md border bg-muted">
            {item.type === 'video' && item.url ? (
                <div className="relative flex size-full items-center justify-center bg-foreground/5">
                    <iframe
                        src={item.url}
                        className="size-full"
                        title={item.caption ?? 'Video'}
                        allowFullScreen
                        loading="lazy"
                    />
                </div>
            ) : item.url ? (
                <img
                    src={item.url}
                    alt={item.caption ?? ''}
                    className="size-full object-cover"
                    onError={(e) => {
                        e.currentTarget.style.display = 'none';
                    }}
                />
            ) : null}
            <div
                className={cn(
                    'absolute top-2 left-2 flex items-center gap-1 rounded bg-black/60 px-2 py-0.5 text-[10px] font-medium text-white',
                )}
            >
                {item.type === 'video' ? <Video className="size-3" /> : null}
                {item.is_primary
                    ? 'Primary'
                    : item.type === 'video'
                      ? 'Video'
                      : 'Image'}
            </div>
            {item.caption ? (
                <div className="absolute right-0 bottom-0 left-0 bg-linear-to-t from-black/80 to-transparent p-2">
                    <p className="line-clamp-1 text-xs text-white">
                        {item.caption}
                    </p>
                </div>
            ) : null}
            <button
                type="button"
                onClick={onDelete}
                className="absolute top-2 right-2 rounded-full bg-destructive p-1.5 text-destructive-foreground opacity-0 transition group-hover:opacity-100"
                aria-label="Remove media"
            >
                <Trash2 className="size-3.5" />
            </button>
        </div>
    );
}

function ImageUploadForm({ uploadUrl }: { uploadUrl: string }) {
    const formRef = useRef<HTMLFormElement>(null);
    const [caption, setCaption] = useState('');
    const [clientError, setClientError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!formRef.current) {
            return;
        }

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

    return (
        <form
            ref={formRef}
            onSubmit={handleSubmit}
            className="space-y-3"
            encType="multipart/form-data"
        >
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
                />
            </div>

            <Button type="submit" size="sm" disabled={uploading}>
                <ImagePlus className="size-4" />
                {uploading ? 'Uploading...' : 'Upload image'}
            </Button>
        </form>
    );
}

function VideoUrlForm({ uploadUrl }: { uploadUrl: string }) {
    const [videoUrl, setVideoUrl] = useState('');
    const [caption, setCaption] = useState('');
    const [saving, setSaving] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!videoUrl.trim()) {
            return;
        }

        setSaving(true);
        router.post(
            uploadUrl,
            { video_url: videoUrl, caption },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setVideoUrl('');
                    setCaption('');
                },
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-3">
            <div className="grid gap-1.5">
                <Label htmlFor="video-url" className="text-xs">
                    YouTube or Vimeo URL
                </Label>
                <Input
                    id="video-url"
                    type="url"
                    value={videoUrl}
                    onChange={(e) => setVideoUrl(e.target.value)}
                    placeholder="https://www.youtube.com/watch?v=..."
                />
                <p className="text-xs text-muted-foreground">
                    Auto-converted to an embed URL on save.
                </p>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="video-caption" className="text-xs">
                    Caption (optional)
                </Label>
                <Input
                    id="video-caption"
                    value={caption}
                    onChange={(e) => setCaption(e.target.value)}
                    maxLength={280}
                />
            </div>

            <Button type="submit" size="sm" disabled={saving || !videoUrl}>
                <Video className="size-4" />
                {saving ? 'Adding...' : 'Add video'}
            </Button>
        </form>
    );
}
