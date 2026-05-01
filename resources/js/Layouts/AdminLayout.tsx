import { PropsWithChildren } from 'react';
import Sidebar from '@/Components/Admin/Sidebar';
import TopBar from '@/Components/Admin/TopBar';
import FlashMessage from '@/Components/Shared/FlashMessage';

interface Props extends PropsWithChildren {
    title?: string;
    subtitle?: string;
}

export default function AdminLayout({ children, title, subtitle }: Props) {
    return (
        <div className="flex h-screen overflow-hidden bg-slate-50">
            <Sidebar />

            <div className="flex flex-1 flex-col overflow-hidden min-w-0">
                <TopBar title={title} subtitle={subtitle} />

                <main className="flex-1 overflow-y-auto p-6">
                    {children}
                </main>
            </div>

            <FlashMessage />
        </div>
    );
}
