import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const AssignRole = () => {
    const { users, roles, auth } = usePage().props as { users: any[]; roles: any[]; auth: { user: any } };
    const [selectedUser, setSelectedUser] = useState<number | null>(null);
    const [selectedRole, setSelectedRole] = useState<string | null>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedUser && selectedRole) {
            Inertia.post(`/users/${selectedUser}/roles`, { role: selectedRole });
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-8xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Assign Role to User</h1>

                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">Select User</label>
                        <select
                            onChange={(e) => setSelectedUser(parseInt(e.target.value))}
                            className="w-full border rounded p-2 mt-1"
                        >
                            <option value="">Select a user</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">Select Role</label>
                        <select
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="w-full border rounded p-2 mt-1"
                        >
                            <option value="">Select a role</option>
                            {roles.map((role) => (
                                <option key={role.name} value={role.name}>
                                    {role.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="mt-4">
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            Assign Role
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
};

export default AssignRole;
