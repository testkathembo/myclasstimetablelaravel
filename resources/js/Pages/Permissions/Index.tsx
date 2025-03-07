import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Inertia } from '@inertiajs/inertia';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const Permissions = () => {
    const { permissions, auth } = usePage().props as { permissions: any[], auth: any };
    const [newPermission, setNewPermission] = useState('');
    const [editingPermission, setEditingPermission] = useState<any>(null);
    const [editingName, setEditingName] = useState('');

    const handleCreatePermission = () => {
        Inertia.post('/permissions', { name: newPermission });
        setNewPermission('');
    };

    const handleUpdatePermission = () => {
        if (editingPermission) {
            Inertia.patch(`/permissions/${editingPermission.id}`, { name: editingName });
            setEditingPermission(null);
            setEditingName('');
        }
    };

    const handleDeletePermission = (id: number) => {
        Inertia.delete(`/permissions/${id}`);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Permissions</h1>

                {/* Create New Permission */}
                <div className="mb-4">
                    <input
                        type="text"
                        value={newPermission}
                        onChange={(e) => setNewPermission(e.target.value)}
                        placeholder="Enter permission name"
                        className="border p-2 rounded mr-2"
                    />
                    <button onClick={handleCreatePermission} className="bg-blue-600 text-white px-4 py-2 rounded">Create Permission</button>
                </div>

                {/* Permissions List */}
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead>
                        <tr className="border-b">
                            <th className="px-4 py-2 border">Permission Name</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {permissions.map((permission) => (
                            <tr key={permission.id} className="border-b">
                                <td className="px-4 py-2 border">{permission.name}</td>
                                <td className="px-4 py-2 border">
                                    <button onClick={() => { setEditingPermission(permission); setEditingName(permission.name); }} className="bg-yellow-500 text-white px-3 py-1 rounded">Edit</button>
                                    <button onClick={() => handleDeletePermission(permission.id)} className="bg-red-600 text-white px-3 py-1 rounded ml-2">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Edit Permission */}
                {editingPermission && (
                    <div className="mt-6">
                        <h2 className="text-xl font-semibold">Edit Permission</h2>
                        <div className="mb-4">
                            <input
                                type="text"
                                value={editingName}
                                onChange={(e) => setEditingName(e.target.value)}
                                placeholder="Enter permission name"
                                className="border p-2 rounded mr-2"
                            />
                            <button onClick={handleUpdatePermission} className="bg-green-600 text-white px-4 py-2 rounded">Update Permission</button>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Permissions;
