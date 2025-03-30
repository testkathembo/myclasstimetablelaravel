import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface User {
    id: number;
    name: string;
    email: string;
    faculty: string;
    phone: string;
    code: string;
}

const Users = () => {
    const { users } = usePage().props as { users: User[] }; // Receive users from the backend

    return (
        <AuthenticatedLayout>
            <Head title="Users" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Users</h1>
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">ID</th>
                            <th className="px-4 py-2 border">Name</th>
                            <th className="px-4 py-2 border">Email</th>
                            <th className="px-4 py-2 border">Faculty</th>
                            <th className="px-4 py-2 border">Phone</th>
                            <th className="px-4 py-2 border">Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border text-center">{user.id}</td>
                                <td className="px-4 py-2 border">{user.name}</td>
                                <td className="px-4 py-2 border">{user.email}</td>
                                <td className="px-4 py-2 border">{user.faculty}</td>
                                <td className="px-4 py-2 border">{user.phone}</td>
                                <td className="px-4 py-2 border">{user.code}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
};

export default Users;
