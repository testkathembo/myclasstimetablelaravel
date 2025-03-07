import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Inertia } from '@inertiajs/inertia';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const Roles = () => {
    const { roles, permissions, auth } = usePage().props as { roles: any[], permissions: any[], auth: any };
    const [newRole, setNewRole] = useState('');
    const [selectedRole, setSelectedRole] = useState<any>(null);
    const [selectedPermissions, setSelectedPermissions] = useState<number[]>([]);

    const handleCreateRole = () => {
        Inertia.post('/roles', { name: newRole });
        setNewRole('');
    };

    const handleAssignPermissions = () => {
        if (selectedRole) {
            Inertia.patch(`/roles/${selectedRole.id}/permissions`, { permissions: selectedPermissions.map(id => permissions.find(p => p.id === id).name) });
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Roles</h1>

                {/* Create New Role */}
                <div className="mb-4">
                    <input
                        type="text"
                        value={newRole}
                        onChange={(e) => setNewRole(e.target.value)}
                        placeholder="Enter role name"
                        className="border p-2 rounded mr-2"
                    />
                    <button onClick={handleCreateRole} className="bg-blue-600 text-white px-4 py-2 rounded">Create Role</button>
                </div>

                {/* Roles List */}
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead>
                        <tr className="border-b">
                            <th className="px-4 py-2 border">Role Name</th>
                            <th className="px-4 py-2 border">Permissions</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roles.map((role) => (
                            <tr key={role.id} className="border-b">
                                <td className="px-4 py-2 border">{role.name}</td>
                                <td className="px-4 py-2 border">
                                    {role.permissions.map((p: any) => p.name).join(', ')}
                                </td>
                                <td className="px-4 py-2 border">
                                    <button onClick={() => setSelectedRole(role)} className="bg-yellow-500 text-white px-3 py-1 rounded">Assign Permissions</button>
                                    <button onClick={() => Inertia.delete(`/roles/${role.id}`)} className="bg-red-600 text-white px-3 py-1 rounded ml-2">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Assign Permissions */}
                {selectedRole && (
                    <div className="mt-6">
                        <h2 className="text-xl font-semibold">Assign Permissions to {selectedRole.name}</h2>
                        <div className="grid grid-cols-3 gap-4 mt-2">
                            {permissions.map((perm) => (
                                <label key={perm.id} className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={selectedPermissions.includes(perm.id)}
                                        onChange={() =>
                                            setSelectedPermissions((prev) =>
                                                prev.includes(perm.id) ? prev.filter((id) => id !== perm.id) : [...prev, perm.id]
                                            )
                                        }
                                        className="mr-2"
                                    />
                                    {perm.name}
                                </label>
                            ))}
                        </div>
                        <button onClick={handleAssignPermissions} className="mt-4 bg-green-600 text-white px-4 py-2 rounded">Save</button>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Roles;
