import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Semester {
    id: number;
    name: string;
}

interface Program {
    id: number;
    name: string;
}

interface Class {
    id: number;
    name: string;
    semester_id: number | null;
    program_id: number | null;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedClasses {
    data: Class[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Classes = () => {
    const { classes, semesters, programs, perPage, search } = usePage().props as {
        classes: PaginatedClasses;
        semesters: Semester[];
        programs: Program[];
        perPage: number;
        search: string;
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentClass, setCurrentClass] = useState<Class | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', classItem: Class | null = null) => {
        setModalType(type);
        setCurrentClass(
            type === 'create'
                ? { id: 0, name: '', semester_id: null, program_id: null }
                : classItem
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentClass(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            if (currentClass) {
                router.post('/classes', currentClass, {
                    onSuccess: () => {
                        alert('Class created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating class:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentClass) {
            router.put(`/classes/${currentClass.id}`, currentClass, {
                onSuccess: () => {
                    alert('Class updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating class:', errors);
                },
            });
        } else if (modalType === 'delete' && currentClass) {
            router.delete(`/classes/${currentClass.id}`, {
                onSuccess: () => {
                    alert('Class deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting class:', errors);
                },
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Classes" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Classes</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Class
                    </button>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/classes', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
                    }} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search classes..."
                            className="border rounded p-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                            type="submit"
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Search
                        </button>
                    </form>
                </div>
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">ID</th>
                            <th className="px-4 py-2 border">Name</th>
                            <th className="px-4 py-2 border">Semester</th>
                            <th className="px-4 py-2 border">Program</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classes.data.map((classItem) => (
                            <tr key={classItem.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 border">{classItem.id}</td>
                                <td className="px-4 py-2 border">{classItem.name}</td>
                                <td className="px-4 py-2 border">
                                    {semesters.find((semester) => semester.id === classItem.semester_id)?.name || 'N/A'}
                                </td>
                                <td className="px-4 py-2 border">
                                    {programs.find((program) => program.id === classItem.program_id)?.name || 'N/A'}
                                </td>
                                <td className="px-4 py-2 border">
                                    <button
                                        onClick={() => handleOpenModal('edit', classItem)}
                                        className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', classItem)}
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
                        Showing {classes.data.length} of {classes.total} classes
                    </p>
                    <div className="flex space-x-2">
                        {classes.links.map((link, index) => (
                            <button
                                key={index}
                                onClick={() => router.get(link.url || '#')}
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
                            {modalType === 'create' && 'Add Class'}
                            {modalType === 'edit' && 'Edit Class'}
                            {modalType === 'delete' && 'Delete Class'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={currentClass?.name || ''}
                                        onChange={(e) =>
                                            setCurrentClass((prev) => ({ ...prev!, name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Semester</label>
                                    <select
                                        value={currentClass?.semester_id || ''}
                                        onChange={(e) =>
                                            setCurrentClass((prev) => ({
                                                ...prev!,
                                                semester_id: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="" disabled>Select a semester</option>
                                        {semesters.map((semester) => (
                                            <option key={semester.id} value={semester.id}>
                                                {semester.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Program</label>
                                    <select
                                        value={currentClass?.program_id || ''}
                                        onChange={(e) =>
                                            setCurrentClass((prev) => ({
                                                ...prev!,
                                                program_id: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="" disabled>Select a program</option>
                                        {programs.map((program) => (
                                            <option key={program.id} value={program.id}>
                                                {program.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
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
                                <p>Are you sure you want to delete this class?</p>
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

export default Classes;
