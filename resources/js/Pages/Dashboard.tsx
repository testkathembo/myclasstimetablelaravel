import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import Sidebar from '@/Components/sidebar';
import Navbar from '@/Components/navbar';

export default function Dashboard({ auth }: PageProps) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Dashboard" />
            
            {/* Full Layout: Navbar at the Top + Sidebar on the Left */}
            <div className="h-screen flex flex-col">
                
                {/* Top Navbar */}
                <Navbar />

                {/* Main Layout with Sidebar & Content */}
                <div className="flex flex-1">
                    
                    {/* Sidebar */}
                    <Sidebar />

                    {/* Main Content Area */}
                    <div className="flex-1 p-6 bg-gray-100">
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>

                        <div className="py-12">
                            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                    <div className="p-6 text-gray-900">You're logged in!</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
