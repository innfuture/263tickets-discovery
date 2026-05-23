import { Check, Copy, Link2, Mail, Share2 } from 'lucide-react';
import { useState } from 'react';
import { BrandIcon } from '@/components/brand-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function ShareMenu({ url, title }: { url: string; title: string }) {
    const [copied, setCopied] = useState(false);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // ignore
        }
    };

    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <Share2 className="size-4" />
                    Share
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={copyLink}>
                    {copied ? (
                        <>
                            <Check className="size-4" />
                            Link copied
                        </>
                    ) : (
                        <>
                            <Copy className="size-4" />
                            Copy link
                        </>
                    )}
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={`https://twitter.com/intent/tweet?text=${encodedTitle}&url=${encodedUrl}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <BrandIcon provider="x" size={16} />
                        Share on X
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={`https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <BrandIcon provider="facebook" size={16} />
                        Share on Facebook
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={`mailto:?subject=${encodedTitle}&body=${encodedUrl}`}
                    >
                        <Mail className="size-4" />
                        Email a friend
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={url}>
                        <Link2 className="size-4" />
                        Open public page
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
