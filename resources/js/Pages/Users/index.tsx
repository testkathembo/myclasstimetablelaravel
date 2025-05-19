import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Role {
    name: string;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    code: string;
    schools: string | null; // Added schools field
    programs: string | null; // Added programs field
    roles: Role[]; // Added roles field
    password?: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Users = () => {
    const { users, perPage, search } = usePage().props as { users: PaginatedUsers; perPage: number; search: string };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentUser, setCurrentUser] = useState<User | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', user: User | null = null) => {
        setModalType(type);
        setCurrentUser(
            type === 'create'
                ? { id: 0, first_name: '', last_name: '', email: '', phone: '', code: '', schools: null, programs: null, roles: [], password: '' }
                : user
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentUser(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            if (currentUser) {
                const payload = { ...currentUser };
                payload.roles = currentUser.roles.map((role) => role.name); // Ensure roles are strings
                delete payload.faculty; // Ensure no faculty field is sent
                router.post('/users', payload, {
                    onSuccess: () => {
                        alert('User created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating user:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentUser) {
            const payload = { ...currentUser };
            payload.roles = currentUser.roles.map((role) => role.name); // Ensure roles are strings
            delete payload.faculty; // Ensure no faculty field is sent
            router.put(`/users/${currentUser.id}`, payload, {
                onSuccess: () => {
                    alert('User updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating user:', errors);
                },
            });
        } else if (modalType === 'delete' && currentUser) {
            router.delete(`/users/${currentUser.id}`, {
                onSuccess: () => {
                    alert('User deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting user:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/users', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/users', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Users" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Users</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add User
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search users..."
                            className="border rounded p-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                            type="submit"
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Search
                        </button>
                    </form>
                    <div>
                        <label htmlFor="perPage" className="mr-2 text-sm font-medium text-gray-700">
                            Items per page:
                        </label>
                        <select
                            id="perPage"
                            value={itemsPerPage}
                            onChange={handlePerPageChange}
                            className="border rounded p-2"
                        >
                            <option value={5}>5</option>
                            <option value={10}>10</option>
                            <option value={15}>15</option>
                            <option value={20}>20</option>
                        </select>
                    </div>
                </div>
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">ID</th>
                            <th className="px-4 py-2 border">Code</th>
                            <th className="px-4 py-2 border">First Name</th>
                            <th className="px-4 py-2 border">Last Name</th>
                            <th className="px-4 py-2 border">Email</th>
                            <th className="px-4 py-2 border">Schools</th>
                            <th className="px-4 py-2 border">Programs</th>
                            <th className="px-4 py-2 border">Role</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr key={user.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 border">{user.id}</td>
                                <td className="px-4 py-2 border">{user.code}</td>
                                <td className="px-4 py-2 border">{user.first_name}</td>
                                <td className="px-4 py-2 border">{user.last_name}</td>
                                <td className="px-4 py-2 border">{user.email}</td>
                                <td className="px-4 py-2 border">{user.schools || 'N/A'}</td>
                                <td className="px-4 py-2 border">{user.programs || 'N/A'}</td>
                                <td className="px-4 py-2 border">{user.roles.map((role) => role.name).join(', ')}</td>
                                <td className="px-4 py-2 border">
                                    <button
                                        onClick={() => handleOpenModal('edit', user)}
                                        className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', user)}
                                        className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="mt-4 flex justify-between items-center">
                    <p className="text-sm text-gray-600">
                        Showing {users.data.length} of {users.total} users
                    </p>
                    <div className="flex space-x-2">
                        {users.links.map((link, index) => (
                            <button
                                key={index}
                                onClick={() => handlePageChange(link.url)}
                                className={`px-3 py-1 rounded ${
                                    link.active
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md" style={{ width: 'auto', maxWidth: '90%', minWidth: '300px' }}>
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add User'}
                            {modalType === 'edit' && 'Edit User'}
                            {modalType === 'delete' && 'Delete User'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">First Name</label>
                                    <input
                                        type="text"
                                        value={currentUser?.first_name || ''}
                                        onChange={(e) =>
                                            setCurrentUser((prev) => ({ ...prev!, first_name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Last Name</label>
                                    <input
                                        type="text"
                                        value={currentUser?.last_name || ''}
                                        onChange={(e) =>
                                            setCurrentUser((prev) => ({ ...prev!, last_name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Email</label>
                                    <input
                                        type="email"
                                        value={currentUser?.email || ''}
                                        onChange={(e) =>
                                            setCurrentUser({ ...currentUser!, email: e.target.value })
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Phone</label>
                                    <input
                                        type="text"
                                        value={currentUser?.phone || ''}
                                        onChange={(e) =>
                                            setCurrentUser({ ...currentUser!, phone: e.target.value })
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Code</label>
                                    <input
                                        type="text"
                                        value={currentUser?.code || ''}
                                        onChange={(e) =>
                                            setCurrentUser({ ...currentUser!, code: e.target.value })
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Schools</label>
                                    <input
                                        type="text"
                                        value={currentUser?.schools || ''}
                                        onChange={(e) =>
                                            setCurrentUser({ ...currentUser!, schools: e.target.value })
                                        }
                                        className="w-full border rounded p-2"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Programs</label>
                                    <input
                                        type="text"
                                        value={currentUser?.programs || ''}
                                        onChange={(e) =>
                                            setCurrentUser({ ...currentUser!, programs: e.target.value })
                                        }
                                        className="w-full border rounded p-2"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Role</label>
                                    <select
                                        value={currentUser?.roles.map((role) => role.name).join(', ') || ''}
                                        onChange={(e) =>
                                            setCurrentUser((prev) => ({
                                                ...prev!,
                                                roles: [{ name: e.target.value }],
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="student">Student</option>
                                        <option value="admin">Admin</option>
                                        <option value="examofficer">Exam Officer</option>
                                        <option value="lecturer">Lecturer</option>
                                    </select>
                                </div>
                                {modalType === 'create' && (
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700">Password</label>
                                        <input
                                            type="password"
                                            value={currentUser?.password || ''}
                                            onChange={(e) =>
                                                setCurrentUser((prev) => ({ ...prev!, password: e.target.value }))
                                            }
                                            className="w-full border rounded p-2"
                                            required
                                        />
                                    </div>
                                )}
                                <button
                                    type="submit"
                                    className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                >
                                    {modalType === 'create' ? 'Create' : 'Update'}
                                </button>
                                <button
                                    type="button"
                                    onClick={handleCloseModal}
                                    className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                                >
                                    Cancel
                                </button>
                            </form>
                        ) : (
                            <div>
                                <p>Are you sure you want to delete this user?</p>
                                <div className="mt-4 flex justify-end">
                                    <button
                                        onClick={handleSubmit}
                                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        onClick={handleCloseModal}
                                        className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default Users;
