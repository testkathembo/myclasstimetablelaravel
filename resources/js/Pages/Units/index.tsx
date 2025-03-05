import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Unit {
    id: number;
    code: string;
    name: string;
    semester: number; // Added semester field
}
const Units = () => {
interface Semester {th } = usePage().props as { units: Unit[]; auth: { user: any } };
    id: number;
    name: string;
}Unit>({ id: 0, code: '', name: '' });

const Units = () => {    const handleCreate = () => {
    const { units, semesters, auth } = usePage().props as { units: Unit[]; semesters: Semester[]; auth: { user: any } };
    const [isModalOpen, setIsModalOpen] = useState(false); code: '', name: '' });
    const [modalType, setModalType] = useState('');
    const [currentUnit, setCurrentUnit] = useState<Unit>({ id: 0, code: '', name: '', semester: 1 });

    const handleCreate = () => {    const handleEdit = (unit: Unit) => {
        setModalType('create');
        setCurrentUnit({ id: 0, code: '', name: '', semester: 1 });
        setIsModalOpen(true);
    };

    const handleEdit = (unit: Unit) => {    const handleDelete = (unit: Unit) => {
        setModalType('edit');
        setCurrentUnit(unit);
        setIsModalOpen(true);
    };

    const handleDelete = (unit: Unit) => {    const handleSubmit = (e: React.FormEvent) => {
        setModalType('delete');
        setCurrentUnit(unit);...currentUnit };
        setIsModalOpen(true);
    };if (modalType === 'create') {

    const handleSubmit = (e: React.FormEvent) => {s', unitData);
        e.preventDefault();
        const unitData = { ...currentUnit };tUnit.id}`, unitData);
        
        if (modalType === 'create') {Unit.id}`);
            delete unitData.id;
            Inertia.post('/units', unitData);etIsModalOpen(false);
        } else if (modalType === 'edit') {
            Inertia.patch(`/units/${currentUnit.id}`, unitData);
        } else if (modalType === 'delete') {return (
            Inertia.delete(`/units/${currentUnit.id}`);henticatedLayout user={auth.user}>
        }uto">
        setIsModalOpen(false);ld mb-4">Manage Units in BBIT</h1>
    };
                    {/* Create Button */}
    return (>
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-8xl mx-auto">ick={handleCreate} 
                <h1 className="text-2xl font-semibold mb-4">Manage Units in BBIT</h1>text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">

                {/* Create Button */}
                <div className="mb-4">
                    <button 
                        onClick={handleCreate}                 {/* Unit Table */}
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">-white shadow-md rounded-lg overflow-hidden">
                        + Add Unitgray-200">
                    </button>
                </div>
y-2 border">ID</th>
                {/* Unit Table */}h>
                <div className="bg-white shadow-md rounded-lg overflow-hidden">
                    <table className="min-w-full border-collapse border border-gray-200">th>
                        <thead className="bg-gray-100">
                            <tr className="border-b">
                                <th className="px-4 py-2 border">ID</th>
                                <th className="px-4 py-2 border">Code</th>ts.length > 0 ? (
                                <th className="px-4 py-2 border">Name</th> units.map((unit) => (
                                <th className="px-4 py-2 border">Semester</th> {/* Added Semester column */}.id} className="border-b hover:bg-gray-50">
                                <th className="px-4 py-2 border">Actions</th>="px-4 py-2 border text-center">{unit.id}</td>
                            </tr>
                        </thead>
                        <tbody>
                            {units.length > 0 ? (
                                units.map((unit) => (
                                    <tr key={unit.id} className="border-b hover:bg-gray-50">y-1 rounded-md hover:bg-green-700 transition">
                                        <td className="px-4 py-2 border text-center">{unit.id}</td>
                                        <td className="px-4 py-2 border">{unit.code}</td>
                                        <td className="px-4 py-2 border">{unit.name}</td>
                                        <td className="px-4 py-2 border">{unit.semester}</td> {/* Display Semester */}ick={() => handleDelete(unit)} 
                                        <td className="px-4 py-2 border flex space-x-2">Name="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                            <button te
                                                onClick={() => handleEdit(unit)} 
                                                className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                                Edit
                                            </button>
                                            <button 
                                                onClick={() => handleDelete(unit)} 
                                                className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">  <td colSpan={4} className="px-4 py-3 text-center text-gray-500">No units found.</td>
                                                Delete/tr>
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>r Create/Edit/Delete */}
                                    <td colSpan={5} className="px-4 py-3 text-center text-gray-500">No units found.</td> {/* Updated colspan */}alOpen && (
                                </tr>                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                            )} rounded shadow-md w-96">
                        </tbody>lassName="text-xl font-bold mb-4">
                    </table> 'Confirm Delete'}
                </div>

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (   <>
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">block text-sm font-medium text-gray-700">Code</label>
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">      type="text"
                                {modalType === 'create' ? 'Add Unit' : modalType === 'edit' ? 'Edit Unit' : 'Confirm Delete'}
                            </h2>Change={(e) => setCurrentUnit({ ...currentUnit, code: e.target.value })}
                            <form onSubmit={handleSubmit}>w-full border rounded p-2 mt-1"
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Code</label>xt-gray-700 mt-4">Name</label>
                                        <input
                                            type="text"  type="text"
                                            value={currentUnit.code}
                                            onChange={(e) => setCurrentUnit({ ...currentUnit, code: e.target.value })}Change={(e) => setCurrentUnit({ ...currentUnit, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"w-full border rounded p-2 mt-1"
                                            required
                                        />
                                        <label className="block text-sm font-medium text-gray-700 mt-4">Name</label>
                                        <input
                                            type="text" you sure you want to delete <strong>{currentUnit.name}</strong>?</p>
                                            value={currentUnit.name}
                                            onChange={(e) => setCurrentUnit({ ...currentUnit, name: e.target.value })}="mt-4 flex justify-end space-x-2">
                                            className="w-full border rounded p-2 mt-1" className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">
                                            requiredrm' : 'Save'}
                                        />
s                                        <label className="block text-sm font-medium text-gray-700 mt-4">Semester</label> {/* Added Semester input */}
                                        <select" 
                                            value={currentUnit.semester} => setIsModalOpen(false)} 
                                            onChange={(e) => setCurrentUnit({ ...currentUnit, semester: parseInt(e.target.value) })}bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 transition">
                                            className="w-full border rounded p-2 mt-1"ncel
                                            requiredutton>
                                        >>
                                            {semesters.map((semester) => (
                                                <option key={semester.id} value={semester.id}>
                                                    {semester.name}
                                                </option>
                                            ))}
                                        </select>
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentUnit.name}</strong>?</p>
                                )}
                                <div className="mt-4 flex justify-end space-x-2">                                    <button type="submit" className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">                                        {modalType === 'delete' ? 'Confirm' : 'Save'}                                    </button>                                    <button                                         type="button"                                         onClick={() => setIsModalOpen(false)}                                         className="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 transition">                                        Cancel                                    </button>                                </div>                            </form>                        </div>                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Units;
