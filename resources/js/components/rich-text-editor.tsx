import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Quote,
    Redo,
    Strikethrough,
    Undo,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

export function RichTextEditor({
    name,
    initialHtml = '',
    placeholder = 'Tell people what makes this event special...',
    hasError = false,
}: {
    name: string;
    initialHtml?: string;
    placeholder?: string;
    hasError?: boolean;
}) {
    const hiddenRef = useRef<HTMLInputElement>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
            }),
            Placeholder.configure({ placeholder }),
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-primary underline underline-offset-2',
                    rel: 'noopener noreferrer',
                    target: '_blank',
                },
            }),
        ],
        content: initialHtml,
        editorProps: {
            attributes: {
                class: cn(
                    'max-h-96 min-h-40 overflow-y-auto px-3 py-2 text-sm leading-relaxed outline-none',
                    '[&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold',
                    '[&_h3]:mt-3 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold',
                    '[&_p]:mb-2',
                    '[&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5',
                    '[&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5',
                    '[&_blockquote]:my-2 [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:italic',
                    '[&_a]:text-primary [&_a]:underline',
                    '[&_p.is-editor-empty:first-child]:before:pointer-events-none [&_p.is-editor-empty:first-child]:before:float-left [&_p.is-editor-empty:first-child]:before:h-0 [&_p.is-editor-empty:first-child]:before:text-muted-foreground [&_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]',
                ),
            },
        },
        onUpdate: ({ editor }) => {
            if (hiddenRef.current) {
                hiddenRef.current.value = editor.isEmpty
                    ? ''
                    : editor.getHTML();
            }
        },
        immediatelyRender: false,
    });

    useEffect(() => {
        return () => {
            editor?.destroy();
        };
    }, [editor]);

    if (!editor) {
        return (
            <div
                className={cn(
                    'h-48 rounded-md border border-input bg-background',
                    hasError && 'border-destructive',
                )}
            />
        );
    }

    const setLink = () => {
        const previous = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt('URL', previous ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border border-input bg-background focus-within:ring-2 focus-within:ring-ring/50',
                hasError && 'border-destructive',
            )}
        >
            <input
                ref={hiddenRef}
                type="hidden"
                name={name}
                defaultValue={initialHtml}
            />
            <div className="flex flex-wrap items-center gap-0.5 border-b border-input bg-muted/30 px-2 py-1">
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bold')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBold().run()
                    }
                    aria-label="Bold"
                >
                    <Bold className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('italic')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleItalic().run()
                    }
                    aria-label="Italic"
                >
                    <Italic className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('strike')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleStrike().run()
                    }
                    aria-label="Strikethrough"
                >
                    <Strikethrough className="size-4" />
                </Toggle>
                <div className="mx-1 h-5 w-px bg-border" />
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 2 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                    aria-label="Heading 2"
                >
                    <Heading2 className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 3 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    }
                    aria-label="Heading 3"
                >
                    <Heading3 className="size-4" />
                </Toggle>
                <div className="mx-1 h-5 w-px bg-border" />
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bulletList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                    aria-label="Bullet list"
                >
                    <List className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('orderedList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                    aria-label="Numbered list"
                >
                    <ListOrdered className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('blockquote')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                    aria-label="Quote"
                >
                    <Quote className="size-4" />
                </Toggle>
                <div className="mx-1 h-5 w-px bg-border" />
                <Toggle
                    size="sm"
                    pressed={editor.isActive('link')}
                    onPressedChange={setLink}
                    aria-label="Link"
                >
                    <Link2 className="size-4" />
                </Toggle>
                {editor.isActive('link') ? (
                    <Toggle
                        size="sm"
                        pressed={false}
                        onPressedChange={() =>
                            editor.chain().focus().unsetLink().run()
                        }
                        aria-label="Remove link"
                    >
                        <Link2Off className="size-4" />
                    </Toggle>
                ) : null}
                <div className="ml-auto flex items-center gap-0.5">
                    <Toggle
                        size="sm"
                        pressed={false}
                        onPressedChange={() =>
                            editor.chain().focus().undo().run()
                        }
                        disabled={!editor.can().undo()}
                        aria-label="Undo"
                    >
                        <Undo className="size-4" />
                    </Toggle>
                    <Toggle
                        size="sm"
                        pressed={false}
                        onPressedChange={() =>
                            editor.chain().focus().redo().run()
                        }
                        disabled={!editor.can().redo()}
                        aria-label="Redo"
                    >
                        <Redo className="size-4" />
                    </Toggle>
                </div>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
