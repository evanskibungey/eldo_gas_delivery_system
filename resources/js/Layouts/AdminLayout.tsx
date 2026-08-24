import { PropsWithChildren, useState } from 'react';
import Sidebar, { MobileSidebar } from '@/components/Admin/Sidebar';
import TopBar from '@/components/Admin/TopBar';
import FlashMessage from '@/components/Shared/FlashMessage';

interface Props extends PropsWithChildren {
    title?: string;
    subtitle?: string;
}

export default function AdminLayout({ children, title, subtitle }: Props) {
    const [navOpen, setNavOpen] = useState(false);

    return (
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
    );
}
