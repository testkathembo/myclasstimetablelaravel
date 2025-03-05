import React, { useState, useEffect } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Classroom {
    id: number;
    name: string;
    capacity: number;
    location: string;
}

const Index = () => {
    const { classrooms, locations, auth }: { classrooms: Classroom[], locations: string[], auth: { user: any } } = usePage().props;

    // Single State Object for Classroom Form
    const [currentClassroom, setCurrentClassroom] = useState<Classroom>({
        id: 0,
        name: '',
        capacity: 0,
        location: '',
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');

    // Open Modal
    const handleCreate = () => {
        setModalType('create');
        setCurrentClassroom({ id: 0, name: '', capacity: 0, location: '' });
        setIsModalOpen(true);
    };

    const handleEdit = (classroom: Classroom) => {
        setModalType('edit');
        setCurrentClassroom(classroom);
        setIsModalOpen(true);
    };

    const handleDelete = (classroom: Classroom) => {
        setModalType('delete');
        setCurrentClassroom(classroom);
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            Inertia.post('/classrooms', { ...currentClassroom });
        } else if (modalType === 'edit') {
            Inertia.patch(`/classrooms/${currentClassroom.id}`, { ...currentClassroom });
        } else if (modalType === 'delete') {
            Inertia.delete(`/classrooms/${currentClassroom.id}`);
        }
        setIsModalOpen(false);
    };

    // Ensure Modal Works Properly
    useEffect(() => {
        if (isModalOpen) {
            document.body.style.overflow = 'hidden'; // Prevents background scrolling
        } else {
            document.body.style.overflow = 'auto';
        }
    }, [isModalOpen]);

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Classrooms</h1>

                {/* Create Button */}
                <div className="mb-4">
                    <button onClick={handleCreate} className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        + Add Classroom
                    </button>
                </div>

                <table className="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr>
                            <th className="py-2 px-4 border-b">Name</th>
                            <th className="py-2 px-4 border-b">Capacity</th>
                            <th className="py-2 px-4 border-b">Location</th>
                            <th className="py-2 px-4 border-b">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classrooms.map((classroom) => (
                            <tr key={classroom.id}>
                                <td className="py-2 px-4 border-b">{classroom.name}</td>
                                <td className="py-2 px-4 border-b">{classroom.capacity}</td>
                                <td className="py-2 px-4 border-b">{classroom.location}</td>
                                <td className="py-2 px-4 border-b">
                                    <button onClick={() => handleEdit(classroom)} className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                        Edit
                                    </button>
                                    <button onClick={() => handleDelete(classroom)} className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">
                                {modalType === 'create' ? 'Add Classroom' : modalType === 'edit' ? 'Edi Classroom' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Name</label>
                                        <input
                                            type="text"
                                            value={currentClassroom.name}
                                            onChange={(e) => setCurrentClassroom({ ...currentClassroom, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                        <label className="block text-sm font-medium text-gray-700">Capacity</label>
                                        <input
                                            type="number"
                                            value={currentClassroom.capacity}
                                            onChange={(e) => setCurrentClassroom({ ...currentClassroom, capacity: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                        <label className="block text-sm font-medium text-gray-700">Location</label>
                                        <input
                                            type="text"
                                            value={currentClassroom.location}
                                            onChange={(e) => setCurrentClassroom({ ...currentClassroom, location: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentClassroom.name}</strong>?</p>
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

export default Index;
