import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const Dashboard = () => {
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold">Dashboard</h1>
                <p>Welcome to your dashboard!</p>
            </div>
        </AuthenticatedLayout>
    );
};

export default Dashboard;
