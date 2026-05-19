import { Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const ACCEPTED_LABEL = 'PNG, JPG, WEBP';
const MAX_BYTES = 5 * 1024 * 1024;
const MAX_LABEL = '5MB';

function humanSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ImageDropzone({
    name = 'banner_image',
    hasError = false,
    onValidationError,
}: {
    name?: string;
    hasError?: boolean;
    onValidationError?: (message: string | null) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const validateFile = (f: File): string | null => {
        if (!ACCEPTED_TYPES.includes(f.type)) {
            return `Unsupported file type. Please use ${ACCEPTED_LABEL}.`;
        }

        if (f.size > MAX_BYTES) {
            return `File too large. Maximum size is ${MAX_LABEL}.`;
        }

        return null;
    };

    const setFromFiles = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        const f = files[0];
        const err = validateFile(f);

        if (err) {
            onValidationError?.(err);

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            return;
        }

        onValidationError?.(null);
        setFile(f);
        setPreview(URL.createObjectURL(f));

        const dt = new DataTransfer();
        dt.items.add(f);

        if (inputRef.current) {
            inputRef.current.files = dt.files;
        }
    };

    const clear = () => {
        setFile(null);
        setPreview(null);
        onValidationError?.(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div>
            <input
                ref={inputRef}
                type="file"
                name={name}
                accept={ACCEPTED_TYPES.join(',')}
                className="sr-only"
                onChange={(e) => setFromFiles(e.target.files)}
                aria-label="Banner image upload"
            />

            {file && preview ? (
                <div
                    className={cn(
                        'overflow-hidden rounded-md border',
                        hasError && 'border-destructive',
                    )}
                >
                    <img
                        src={preview}
                        alt=""
                        className="aspect-video w-full object-cover"
                    />
                    <div className="flex items-center justify-between gap-2 bg-muted px-3 py-2 text-sm">
                        <div className="min-w-0">
                            <p className="truncate font-medium">{file.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {humanSize(file.size)}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clear}
                        >
                            <X className="size-4" />
                            Remove
                        </Button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    onDragOver={(e) => {
                        e.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragEnter={(e) => {
                        e.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragLeave={(e) => {
                        e.preventDefault();
                        setIsDragging(false);
                    }}
                    onDrop={(e) => {
                        e.preventDefault();
                        setIsDragging(false);
                        setFromFiles(e.dataTransfer.files);
                    }}
                    className={cn(
                        'flex w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed border-input bg-muted/30 px-4 py-8 text-sm transition outline-none hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        isDragging && 'border-primary bg-primary/5',
                        hasError && 'border-destructive',
                    )}
                >
                    <div className="flex size-10 items-center justify-center rounded-full border bg-background">
                        <Upload className="size-5 text-muted-foreground" />
                    </div>
                    <div className="text-center">
                        <p className="font-medium">
                            Click to upload or drag and drop
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {ACCEPTED_LABEL} up to {MAX_LABEL}
                        </p>
                    </div>
                </button>
            )}
        </div>
    );
}
