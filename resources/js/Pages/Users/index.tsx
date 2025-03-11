import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface User {
    id: number;
    code: string;
    first_name: string;
    last_name: string;
    faculty: string;
    email: string;
    phone: string;
    role: string;
}

const Users = () => {
    const { users, auth, roles } = usePage().props as { 
        users: User[]; 
        roles: string[]; // Roles should be fetched from the backend
        auth: { user: any }; 
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [currentUser, setCurrentUser] = useState<User>({ 
        id: 0, code: '', first_name: '', last_name: '', faculty: '', 
        email: '', phone: '', role: '' 
    });

    const hasPermission = (permission: string) => auth?.user?.permissions?.includes(permission);

    const handleCreate = () => {
        setModalType('create');
        setCurrentUser({ id: 0, code: '', first_name: '', last_name: '', faculty: '', email: '', phone: '', role: '' });
        setIsModalOpen(true);
    };

    const handleEdit = (user: User) => {
        setModalType('edit');
        setCurrentUser(user);
        setIsModalOpen(true);
    };

    const handleDelete = (user: User) => {
        setModalType('delete');
        setCurrentUser(user);
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const userData = { ...currentUser };
        
        if (modalType === 'create') {
            delete userData.id;
            Inertia.post('/users', userData);
        } else if (modalType === 'edit') {
            Inertia.patch(`/users/${currentUser.id}`, userData);
        } else if (modalType === 'delete') {
            Inertia.delete(`/users/${currentUser.id}`);
        }
        setIsModalOpen(false);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-8xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Users</h1>

                {/* Create Button (Only If User Has Permission) */}
                {hasPermission('create users') && (
                    <div className="mb-4">
                        <button 
                            onClick={handleCreate} 
                            className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            + Add User
                        </button>
                    </div>
                )}

                {/* User Table */}
                <div className="bg-white shadow-md rounded-lg overflow-hidden">
                    <table className="min-w-full border-collapse border border-gray-200">
                        <thead className="bg-gray-100">
                            <tr className="border-b">
                                <th className="px-4 py-2 border">ID</th>
                                <th className="px-4 py-2 border">Code</th>
                                <th className="px-4 py-2 border">First Name</th>
                                <th className="px-4 py-2 border">Last Name</th>
                                <th className="px-4 py-2 border">Faculty</th>
                                <th className="px-4 py-2 border">Email</th>
                                <th className="px-4 py-2 border">Phone</th>
                                <th className="px-4 py-2 border">Role</th>
                                <th className="px-4 py-2 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length > 0 ? (
                                users.map((user) => (
                                    <tr key={user.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-2 border text-center">{user.id}</td>
                                        <td className="px-4 py-2 border">{user.code}</td>
                                        <td className="px-4 py-2 border">{user.first_name}</td>
                                        <td className="px-4 py-2 border">{user.last_name}</td>
                                        <td className="px-4 py-2 border">{user.faculty}</td>
                                        <td className="px-4 py-2 border">{user.email}</td>
                                        <td className="px-4 py-2 border">{user.phone}</td>
                                        <td className="px-4 py-2 border">{user.role}</td>
                                        <td className="px-4 py-2 border flex space-x-2">
                                            {hasPermission('edit users') && (
                                                <button 
                                                    onClick={() => handleEdit(user)} 
                                                    className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                                    Edit
                                                </button>
                                            )}
                                            {hasPermission('delete users') && (
                                                <button 
                                                    onClick={() => handleDelete(user)} 
                                                    className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                                    Delete
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={9} className="px-4 py-3 text-center text-gray-500">No users found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">
                                {modalType === 'create' ? 'Add User' : modalType === 'edit' ? 'Edit User' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Role</label>
                                        <select
                                            value={currentUser.role}
                                            onChange={(e) => setCurrentUser({ ...currentUser, role: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        >
                                            <option value="">Select Role</option>
                                            {roles.map((role) => (
                                                <option key={role} value={role}>{role}</option>
                                            ))}
                                        </select>
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentUser.first_name} {currentUser.last_name}</strong>?</p>
                                )}
                                <div className="mt-4 flex justify-end space-x-2">
                                    <button type="submit" className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">
                                        {modalType === 'delete' ? 'Confirm' : 'Save'}
                                    </button>
                                    <button 
                                        type="button" 
                                        onClick={() => setIsModalOpen(false)} 
                                        className="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 transition">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Users;
