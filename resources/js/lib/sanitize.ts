import DOMPurify from 'dompurify';

/**
 * Allowlist of tags + attrs we accept from rich-text inputs (event
 * descriptions, organizer profile bodies). Restricted to what the
 * Tiptap toolbar can produce — anything else gets stripped.
 *
 * RELAXED_ALLOWED_TAGS keeps the union of all tags safe to render
 * inside a buyer/storefront context. Add to this list deliberately;
 * never accept `<script>`, `<iframe>`, `<object>`, or `on*` attrs.
 */
const ALLOWED_TAGS = [
    'a',
    'p',
    'br',
    'span',
    'div',
    'strong',
    'em',
    'b',
    'i',
    'u',
    's',
    'sub',
    'sup',
    'code',
    'pre',
    'blockquote',
    'hr',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'ul',
    'ol',
    'li',
];

const ALLOWED_ATTR = ['href', 'title', 'target', 'rel', 'class'];

/**
 * Sanitize a server-supplied HTML string before rendering it via
 * dangerouslySetInnerHTML. Forces `rel="noopener noreferrer"` on any
 * anchor that opens in a new tab.
 */
export function sanitizeHtml(html: string): string {
    const cleaned = DOMPurify.sanitize(html, {
        ALLOWED_TAGS,
        ALLOWED_ATTR,
        FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'form'],
        FORBID_ATTR: ['style', 'srcset', 'formaction'],
    });

    if (typeof document === 'undefined') {
        return cleaned;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = cleaned;
    wrapper.querySelectorAll('a[target="_blank"]').forEach((anchor) => {
        anchor.setAttribute('rel', 'noopener noreferrer');
    });

    return wrapper.innerHTML;
}
