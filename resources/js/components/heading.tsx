export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'space-y-1'}>
            <h1
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-3xl font-bold tracking-tight'
                }
            >
                {title}
            </h1>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
