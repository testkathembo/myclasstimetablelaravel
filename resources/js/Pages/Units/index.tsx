import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Unit {
    id: number;
    code: string;
    name: string;
}

const UnitTable = ({ units, handleEdit, handleDelete }: { units: Unit[], handleEdit: (unit: Unit) => void, handleDelete: (unit: Unit) => void }) => {
    return (
        <div className="bg-white shadow-md rounded-lg overflow-hidden">
            <table className="min-w-full border-collapse border border-gray-200">
                <thead className="bg-gray-100">
                    <tr className="border-b">
                        <th className="px-4 py-2 border">ID</th>
                        <th className="px-4 py-2 border">Code</th>
                        <th className="px-4 py-2 border">Name</th>
                        <th className="px-4 py-2 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {units.length > 0 ? (
                        units.map((unit) => (
                            <tr key={unit.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border text-center">{unit.id}</td>
                                <td className="px-4 py-2 border">{unit.code}</td>
                                <td className="px-4 py-2 border">{unit.name}</td>
                                <td className="px-4 py-2 border flex space-x-2">
                                    <button 
                                        onClick={() => handleEdit(unit)} 
                                        className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                        Edit
                                    </button>
                                    <button 
                                        onClick={() => handleDelete(unit)} 
                                        className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={4} className="px-4 py-3 text-center text-gray-500">No units found.</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

const Units = () => {
    const { units, auth } = usePage().props as { units: Unit[]; auth: { user: any } };
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [currentUnit, setCurrentUnit] = useState<Unit>({ id: 0, code: '', name: '' });

    const handleCreate = () => {
        setModalType('create');
        setCurrentUnit({ id: 0, code: '', name: '' });
        setIsModalOpen(true);
    };

    const handleEdit = (unit: Unit) => {
        setModalType('edit');
        setCurrentUnit(unit);
        setIsModalOpen(true);
    };

    const handleDelete = (unit: Unit) => {
        setModalType('delete');
        setCurrentUnit(unit);
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (modalType === 'create') {
            Inertia.post('/units', currentUnit);
        } else if (modalType === 'edit') {
            Inertia.patch(`/units/${currentUnit.id}`, currentUnit);
        } else if (modalType === 'delete') {
            Inertia.delete(`/units/${currentUnit.id}`);
        }
        setIsModalOpen(false);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Units</h1>

                {/* Create Button */}
                <div className="mb-4">
                    <button 
                        onClick={handleCreate} 
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        + Add Unit
                    </button>
                </div>

                {/* Unit Table */}
                <UnitTable units={units} handleEdit={handleEdit} handleDelete={handleDelete} />

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">
                                {modalType === 'create' ? 'Add Unit' : modalType === 'edit' ? 'Edit Unit' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Code</label>
                                        <input
                                            type="text"
                                            value={currentUnit.code}
                                            onChange={(e) => setCurrentUnit({ ...currentUnit, code: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                        <label className="block text-sm font-medium text-gray-700 mt-4">Name</label>
                                        <input
                                            type="text"
                                            value={currentUnit.name}
                                            onChange={(e) => setCurrentUnit({ ...currentUnit, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentUnit.name}</strong>?</p>
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

export default Units;
