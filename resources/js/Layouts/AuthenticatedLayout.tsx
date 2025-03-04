import React from 'react';
import Sidebar from '@/components/ui/sidebar';
import Navbar from '@/components/ui/navbar';

interface AuthenticatedLayoutProps {
    user: { code: string }; // Define the user type appropriately
    children: React.ReactNode;
}

const AuthenticatedLayout: React.FC<AuthenticatedLayoutProps> = ({ user, children }) => {
    return (
        <div className="flex">
            <Sidebar />
            <div className="flex-1">
                <Navbar user={user} />
                <div className="p-4">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default AuthenticatedLayout;