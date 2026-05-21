import {
    SettingsScaffold,
    type ScaffoldProps,
} from '@/components/settings/settings-scaffold';

type Breadcrumb = { title: string; href: string };

/**
 * Stub page rendered by ScaffoldController. Replace this body with a
 * bespoke component when the feature ships, and update the route in
 * routes/settings.php to point at a dedicated controller method.
 */
export default function Page({
    scaffold,
}: {
    scaffold: ScaffoldProps;
    breadcrumbs: Breadcrumb[];
}) {
    return <SettingsScaffold {...scaffold} />;
}

Page.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({
    breadcrumbs,
});
