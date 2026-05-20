import { ImagePlus, Loader2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml',
];
const MAX_BYTES = 5 * 1024 * 1024;

/**
 * Square-preview logo uploader for sponsors. Behaviourally identical to
 * `LineupPhotoUploader` but with a rounded-square (not circle) preview and
 * SVG support — sponsor logos are typically vector assets.
 */
export function SponsorLogoUploader({
    uploadUrl,
    value,
    onChange,
}: {
    uploadUrl: string;
    value: string;
    onChange: (path: string) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const isUploadedPath = value && !value.startsWith('http');
    const previewSrc = value
        ? isUploadedPath
            ? `/storage/${value}`
            : value
        : null;

    const handleFile = async (file: File) => {
        if (!ACCEPTED_TYPES.includes(file.type)) {
            setError('Use PNG, JPG, WEBP or SVG');

            return;
        }

        if (file.size > MAX_BYTES) {
            setError('File too large (max 5MB)');

            return;
        }

        setError(null);
        setUploading(true);

        const formData = new FormData();
        formData.append('logo', file);

        try {
            const csrfToken =
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? '';

            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Upload failed (${response.status})`);
            }

            const data = (await response.json()) as { path: string };
            onChange(data.path);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Upload failed');
        } finally {
            setUploading(false);

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    };

    const clear = () => {
        onChange('');
        setError(null);
    };

    return (
        <div className="space-y-1.5">
            <input
                ref={inputRef}
                type="file"
                accept={ACCEPTED_TYPES.join(',')}
                className="sr-only"
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (file) {
                        void handleFile(file);
                    }
                }}
            />

            <div className="flex items-center gap-3">
                <div
                    className={cn(
                        'relative size-16 shrink-0 overflow-hidden rounded-md border bg-muted',
                        error && 'border-destructive',
                    )}
                >
                    {previewSrc ? (
                        <img
                            src={previewSrc}
                            alt=""
                            className="size-full object-contain p-1"
                            onError={(e) => {
                                e.currentTarget.style.display = 'none';
                            }}
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center text-muted-foreground">
                            <ImagePlus className="size-5" />
                        </div>
                    )}
                    {uploading ? (
                        <div className="absolute inset-0 flex items-center justify-center bg-background/60">
                            <Loader2 className="size-5 animate-spin" />
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                        disabled={uploading}
                    >
                        <ImagePlus className="size-4" />
                        {value ? 'Replace logo' : 'Upload logo'}
                    </Button>
                    {value ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clear}
                            className="justify-start text-destructive hover:text-destructive"
                        >
                            <X className="size-3.5" />
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
