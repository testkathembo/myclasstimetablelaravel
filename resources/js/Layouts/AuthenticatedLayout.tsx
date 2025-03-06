import React from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '@/components/ui/sidebar';
import Navbar from '@/components/ui/navbar';

const AuthenticatedLayout = ({ children }: { children: React.ReactNode }) => {
    const { auth } = usePage().props as { auth: { user: any } };

    return (
        <div className="h-screen flex flex-col">
            <Navbar user={auth.user} />
            <div className="flex flex-1">
                <Sidebar />
                <main className="flex-1 p-6 bg-gray-100">
                    {children}
                </main>
            </div>
        </div>
    );
};

export default AuthenticatedLayout;