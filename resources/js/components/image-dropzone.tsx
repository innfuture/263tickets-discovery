import { Pencil, Trash2, Upload } from 'lucide-react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const ACCEPTED_LABEL = 'PNG, JPG, WEBP';
const MAX_BYTES = 5 * 1024 * 1024;
const MAX_LABEL = '5MB';

type ImageDropzoneProps = {
    name?: string;
    /** Currently persisted image URL (from the server). */
    existingUrl?: string | null;
    /** Tailwind aspect class for the preview frame (e.g. `aspect-square`). */
    aspectClassName?: string;
    /** Object-fit for the preview image. */
    objectFit?: 'cover' | 'contain';
    /** Padding around the image (useful for logos that shouldn't bleed to the edge). */
    imagePaddingClassName?: string;
    hasError?: boolean;
    onValidationError?: (message: string | null) => void;
    /**
     * Hidden field name that, when present in the form submission with
     * value "1", tells the server to delete the persisted image. Only
     * emitted when the user clicks Remove on an existing (persisted)
     * image and no replacement file is staged.
     */
    removeFieldName?: string;
    altText?: string;
    accept?: string[];
    /** Override the accepted-types label shown in the empty state. */
    acceptedLabel?: string;
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
};

/**
 * Single-image dropzone with an in-image overlay control surface.
 *
 * The component holds three pieces of state:
 *
 *   - `stagedFile`     a File the user just picked but hasn't submitted
 *   - `existingUrl`    a previously persisted image returned by the server
 *   - `markedForRemoval`  user clicked Remove on the existing image and
 *                         hasn't replaced it
 *
 * What renders is the highest-priority of: staged preview → existing
 * image → empty dropzone. The "Change" and "Remove" buttons only show
 * when something is rendered; the file <input> stays hidden and is the
 * sole entry point for picking a file (no drag onto the preview, no
 * sibling metadata bar). That's the "exclusive interaction" guarantee:
 * removal/replacement is only possible through these overlay buttons.
 */
const ImageDropzone = forwardRef<HTMLInputElement, ImageDropzoneProps>(
    function ImageDropzone(
        {
            name = 'banner_image',
            existingUrl = null,
            aspectClassName = 'aspect-video',
            objectFit = 'cover',
            imagePaddingClassName,
            hasError = false,
            onValidationError,
            removeFieldName,
            altText = '',
            accept,
            acceptedLabel,
            inputProps,
        },
        ref,
    ) {
        const inputRef = useRef<HTMLInputElement>(null);
        useImperativeHandle(ref, () => inputRef.current as HTMLInputElement);

        const [stagedFile, setStagedFile] = useState<File | null>(null);
        const [stagedPreview, setStagedPreview] = useState<string | null>(null);
        const [markedForRemoval, setMarkedForRemoval] = useState(false);
        const [isDragging, setIsDragging] = useState(false);

        const acceptList = accept ?? ACCEPTED_TYPES;
        const acceptText = acceptedLabel ?? ACCEPTED_LABEL;

        // Decide what image (if any) to render.
        const displayUrl =
            stagedPreview ?? (markedForRemoval ? null : existingUrl);

        // Free the object URL when it's no longer referenced.
        useEffect(() => {
            return () => {
                if (stagedPreview) {
                    URL.revokeObjectURL(stagedPreview);
                }
            };
        }, [stagedPreview]);

        const validateFile = (f: File): string | null => {
            if (!acceptList.includes(f.type)) {
                return `Unsupported file type. Please use ${acceptText}.`;
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

            // Free the previous staged preview's blob URL.
            if (stagedPreview) {
                URL.revokeObjectURL(stagedPreview);
            }

            onValidationError?.(null);
            setStagedFile(f);
            setStagedPreview(URL.createObjectURL(f));
            // A fresh upload always overrides any pending removal.
            setMarkedForRemoval(false);

            // Sync the <input>'s FileList so a native form submit
            // carries the file under `name`.
            const dt = new DataTransfer();
            dt.items.add(f);
            if (inputRef.current) {
                inputRef.current.files = dt.files;
            }
        };

        const openPicker = () => {
            inputRef.current?.click();
        };

        /**
         * Remove drops whatever's currently displayed:
         *   - staged file  → clear it (back to existing image, if any)
         *   - existing url → mark for server-side deletion
         * Either way the input's FileList is cleared so no file
         * uploads on submit.
         */
        const remove = () => {
            if (stagedPreview) {
                URL.revokeObjectURL(stagedPreview);
            }
            setStagedFile(null);
            setStagedPreview(null);

            if (existingUrl && !stagedFile) {
                setMarkedForRemoval(true);
            }

            if (inputRef.current) {
                inputRef.current.value = '';
            }
            onValidationError?.(null);
        };

        return (
            <div>
                <input
                    ref={inputRef}
                    type="file"
                    name={name}
                    accept={acceptList.join(',')}
                    className="sr-only"
                    // The picker is opened only via the overlay/empty-state
                    // buttons; the input itself is invisible to mouse/keyboard.
                    tabIndex={-1}
                    onChange={(e) => setFromFiles(e.target.files)}
                    aria-hidden="true"
                    {...inputProps}
                />

                {/* Tell the server to delete the persisted image. Only
                    present when there's nothing staged to replace it. */}
                {removeFieldName &&
                existingUrl &&
                markedForRemoval &&
                !stagedFile ? (
                    <input
                        type="hidden"
                        name={removeFieldName}
                        value="1"
                    />
                ) : null}

                {displayUrl ? (
                    <div
                        className={cn(
                            'group relative w-full overflow-hidden rounded-md border bg-muted',
                            aspectClassName,
                            hasError && 'border-destructive',
                        )}
                    >
                        <img
                            src={displayUrl}
                            alt={altText}
                            className={cn(
                                'size-full',
                                objectFit === 'contain'
                                    ? 'object-contain'
                                    : 'object-cover',
                                imagePaddingClassName,
                            )}
                        />

                        {/* Overlay: the only way to change or remove the image. */}
                        <div
                            className={cn(
                                'absolute inset-0 flex items-center justify-center gap-2',
                                'bg-black/0 transition-colors',
                                'group-hover:bg-black/40 group-focus-within:bg-black/40',
                            )}
                        >
                            <div
                                className={cn(
                                    'flex gap-2 opacity-0 transition-opacity',
                                    'group-hover:opacity-100 group-focus-within:opacity-100',
                                )}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    onClick={openPicker}
                                >
                                    <Pencil className="size-4" />
                                    Change
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    onClick={remove}
                                >
                                    <Trash2 className="size-4" />
                                    Remove
                                </Button>
                            </div>
                        </div>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={openPicker}
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
                            aspectClassName,
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
                                {acceptText} up to {MAX_LABEL}
                            </p>
                        </div>
                    </button>
                )}
            </div>
        );
    },
);

export default ImageDropzone;
