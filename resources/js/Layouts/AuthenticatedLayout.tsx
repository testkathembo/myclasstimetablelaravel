import React from 'react';
import { Inertia } from '@inertiajs/inertia';
import { Head, Link, usePage } from '@inertiajs/react';
import Sidebar from '@/components/ui/sidebar';
import Navbar from '@/components/ui/navbar';

interface AuthenticatedLayoutProps {
  children: React.ReactNode;
}

const AuthenticatedLayout: React.FC<AuthenticatedLayoutProps> = ({ children }) => {
    const { auth } = usePage().props as { auth: { user: { code: string } } };

    const handleLogout = () => {
        Inertia.post('/logout', {}, {
            onSuccess: () => Inertia.visit('/login'), // Ensure redirection to login page
        });
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-screen">
                {/* Sidebar */}
                <Sidebar />

                {/* Main Content */}
                <div className="flex-1 flex flex-col">
                    {/* Navbar */}
                    <Navbar user={auth.user} />

                    {/* Page Content */}
                    <main className="p-6 bg-gray-100 min-h-screen">
                        {children}
                    </main>

                    {/* Logout Button */}
                    <div className="p-4 text-center">
                        <button 
                            onClick={handleLogout} 
                            className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default AuthenticatedLayout;
