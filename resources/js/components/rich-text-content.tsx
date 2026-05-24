import { sanitizeHtml } from '@/lib/sanitize';
import { cn } from '@/lib/utils';

export function RichTextContent({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    if (!html?.trim()) {
        return null;
    }

    const safe = sanitizeHtml(html);

    return (
        <div
            className={cn(
                'text-sm leading-relaxed',
                '[&_h1]:mt-6 [&_h1]:mb-3 [&_h1]:text-xl [&_h1]:font-bold',
                '[&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold',
                '[&_h3]:mt-4 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold',
                '[&_p]:mb-3',
                '[&_p:last-child]:mb-0',
                '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2',
                '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-5',
                '[&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-5',
                '[&_li]:mb-1',
                '[&_blockquote]:my-3 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:italic',
                '[&_strong]:font-semibold',
                '[&_em]:italic',
                '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs',
                '[&_hr]:my-4 [&_hr]:border-border',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: safe }}
        />
    );
}
