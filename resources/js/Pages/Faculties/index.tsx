import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Faculty {
    id: number;
    name: string;
}

const Faculties = () => {
    const { faculties, auth } = usePage().props as { faculties: Faculty[]; auth: { user: any } };
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [currentFaculty, setCurrentFaculty] = useState<Faculty>({ id: 0, name: '' });

    const handleCreate = () => {
        setModalType('create');
        setCurrentFaculty({ id: 0, name: '' });
        setIsModalOpen(true);
    };

    const handleEdit = (faculty: Faculty) => {
        setModalType('edit');
        setCurrentFaculty(faculty);
        setIsModalOpen(true);
    };

    const handleDelete = (faculty: Faculty) => {
        setModalType('delete');
        setCurrentFaculty(faculty);
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (modalType === 'create') {
            Inertia.post('/faculties', currentFaculty);
        } else if (modalType === 'edit') {
            Inertia.patch(`/faculties/${currentFaculty.id}`, currentFaculty);
        } else if (modalType === 'delete') {
            Inertia.delete(`/faculties/${currentFaculty.id}`);
        }
        setIsModalOpen(false);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Faculties</h1>

                {/* Create Button */}
                <div className="mb-4">
                    <button 
                        onClick={handleCreate} 
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        + Add Faculty
                    </button>
                </div>

                {/* Faculty Table */}
                <div className="bg-white shadow-md rounded-lg overflow-hidden">
                    <table className="min-w-full border-collapse border border-gray-200">
                        <thead className="bg-gray-100">
                            <tr className="border-b">
                                <th className="px-4 py-2 border">ID</th>
                                <th className="px-4 py-2 border">Name</th>
                                <th className="px-4 py-2 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {faculties.length > 0 ? (
                                faculties.map((faculty) => (
                                    <tr key={faculty.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-2 border text-center">{faculty.id}</td>
                                        <td className="px-4 py-2 border">{faculty.name}</td>
                                        <td className="px-4 py-2 border flex space-x-2">
                                            <button 
                                                onClick={() => handleEdit(faculty)} 
                                                className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                                Edit
                                            </button>
                                            <button 
                                                onClick={() => handleDelete(faculty)} 
                                                className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={3} className="px-4 py-3 text-center text-gray-500">No faculties found.</td>
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
                                {modalType === 'create' ? 'Add Faculty' : modalType === 'edit' ? 'Edit Faculty' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Name</label>
                                        <input
                                            type="text"
                                            value={currentFaculty.name}
                                            onChange={(e) => setCurrentFaculty({ ...currentFaculty, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentFaculty.name}</strong>?</p>
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

export default Faculties;
