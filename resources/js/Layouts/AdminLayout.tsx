import { PropsWithChildren, useState } from 'react';
import Sidebar, { MobileSidebar } from '@/components/Admin/Sidebar';
import TopBar from '@/components/Admin/TopBar';
import FlashMessage from '@/components/Shared/FlashMessage';
import { AdminRealtimeProvider, useAdminLiveRefresh } from '@/components/Admin/AdminRealtime';

interface Props extends PropsWithChildren {
    title?: string;
    subtitle?: string;
    /**
     * Inertia props to re-fetch when orders change. Declared here rather than
     * with a hook in the page, because a page component renders ABOVE this
     * layout — a hook called in the page body sits outside the provider below
     * and would never see its context.
     */
    liveRefresh?: string[];
}

/** Registers the page's refresh props from INSIDE the provider's subtree. */
function LiveRefreshRegistrar({ props }: { props: string[] }) {
    useAdminLiveRefresh(props);

    return null;
}

export default function AdminLayout({ children, title, subtitle, liveRefresh }: Props) {
    const [navOpen, setNavOpen] = useState(false);

    return (
        // Wraps the whole panel so new-order alerts reach an admin on any page,
        // not only the orders board.
        <AdminRealtimeProvider>
            {liveRefresh && liveRefresh.length > 0 && (
                <LiveRefreshRegistrar props={liveRefresh} />
            )}

            <div className="flex h-screen overflow-hidden bg-slate-50">
                <Sidebar />
                <MobileSidebar open={navOpen} onOpenChange={setNavOpen} />

                <div className="flex flex-1 flex-col overflow-hidden min-w-0">
                    <TopBar title={title} subtitle={subtitle} onMenuClick={() => setNavOpen(true)} />

                    <main className="flex-1 overflow-y-auto p-4 sm:p-6">
                        {children}
                    </main>
                </div>

                <FlashMessage />
            </div>
        </AdminRealtimeProvider>
    );
}
