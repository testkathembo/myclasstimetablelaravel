import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import Sidebar from '@/components/ui/sidebar';
import Navbar from '@/components/ui/navbar';

export default function Dashboard({ auth }: PageProps & { auth: { user: { code: string } } }) {
    

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Dashboard" />
            
            {/* Full Layout: Navbar at the Top + Sidebar on the Left */}
            <div className="h-screen flex flex-col">
                
               
                {/* Main Layout with Sidebar & Content */}
                <div className="flex flex-1">
                    
                    
                    {/* Main Content Area */}
                    

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
