import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

const SemesterTable = ({ semesters, handleEdit, handleDelete }: { semesters: Semester[], handleEdit: (semester: Semester) => void, handleDelete: (semester: Semester) => void }) => {
    return (
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
                    {semesters.length > 0 ? (
                        semesters.map((semester) => (
                            <tr key={semester.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border text-center">{semester.id}</td>
                                <td className="px-4 py-2 border">{semester.name}</td>                               
                                <td className="px-4 py-2 border flex space-x-2">
                                    <button 
                                        onClick={() => handleEdit(semester)} 
                                        className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                        Edit
                                    </button>
                                    <button 
                                        onClick={() => handleDelete(semester)} 
                                        className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={5} className="px-4 py-3 text-center text-gray-500">No semesters found.</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

const Semesters = () => {
    const { semesters, auth } = usePage().props as { semesters: Semester[]; auth: { user: any } };
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [currentSemester, setCurrentSemester] = useState<Semester>({ id: 0, name: '', start_date: '', end_date: '' });

    const handleCreate = () => {
        setModalType('create');
        setCurrentSemester({ id: 0, name: '', start_date: '', end_date: '' });
        setIsModalOpen(true);
    };

    const handleEdit = (semester: Semester) => {
        setModalType('edit');
        setCurrentSemester(semester);
        setIsModalOpen(true);
    };

    const handleDelete = (semester: Semester) => {
        if (confirm(`Are you sure you want to delete the semester "${semester.name}"?`)) {
            Inertia.delete(`/all-semesters/${semester.id}`);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (modalType === 'create') {
            Inertia.post('/all-semesters', currentSemester);
        } else if (modalType === 'edit') {
            Inertia.patch(`/all-semesters/${currentSemester.id}`, currentSemester);
        } else if (modalType === 'delete') {
            Inertia.delete(`/all-semesters/${currentSemester.id}`);
        }
        setIsModalOpen(false);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-3 max-w-2xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Semesters</h1>

                {/* Create Button */}
                <div className="mb-4">
                    <button 
                        onClick={handleCreate} 
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        + Add Semester
                    </button>
                </div>

                {/* Semester Table */}
                <SemesterTable semesters={semesters} handleEdit={handleEdit} handleDelete={handleDelete} />

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">
                                {modalType === 'create' ? 'Add Semester' : modalType === 'edit' ? 'Edit Semester' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Name</label>
                                        <input
                                            type="text"
                                            value={currentSemester.name}
                                            onChange={(e) => setCurrentSemester({ ...currentSemester, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />                                        
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentSemester.name}</strong>?</p>
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

export default Semesters;
