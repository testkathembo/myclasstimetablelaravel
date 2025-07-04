import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface School {
    id: number;
    name: string;
}

interface Program {
    id: number;
    name: string;
    school_id: number;
}

interface Unit {
    id: number;
    name: string;
    code: string;
    program_id: number | null;
    credit_hours: number | null; // Added credit_hours field
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUnits {
    data: Unit[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Units = () => {
    const { units, programs, schools, perPage, search } = usePage().props as {
        units: PaginatedUnits;
        programs: Program[];
        schools: School[];
        perPage: number;
        search: string;
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentUnit, setCurrentUnit] = useState<Unit | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', unit: Unit | null = null) => {
        setModalType(type);
        setCurrentUnit(
            type === 'create'
                ? { id: 0, name: '', code: '', program_id: null, credit_hours: null }
                : unit
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentUnit(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        console.log('Submitting:', currentUnit); // Debug the currentUnit object

        if (modalType === 'create') {
            if (currentUnit) {
                router.post('/units', {
                    name: currentUnit.name,
                    code: currentUnit.code,
                    credit_hours: currentUnit.credit_hours, // Ensure this field is sent
                    program_id: currentUnit.program_id,
                }, {
                    onSuccess: () => {
                        alert('Unit created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating unit:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentUnit) {
            router.put(`/units/${currentUnit.id}`, {
                name: currentUnit.name,
                code: currentUnit.code,
                credit_hours: currentUnit.credit_hours, // Ensure this field is sent
                program_id: currentUnit.program_id,
            }, {
                onSuccess: () => {
                    alert('Unit updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating unit:', errors);
                },
            });
        } else if (modalType === 'delete' && currentUnit) {
            router.delete(`/units/${currentUnit.id}`, {
                onSuccess: () => {
                    alert('Unit deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting unit:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/units', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/units', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Units" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Units</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Unit
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search units..."
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
                            <th className="px-4 py-2 border">Name</th>
                            <th className="px-4 py-2 border">Code</th>
                            <th className="px-4 py-2 border">Credit Hours</th>
                            <th className="px-4 py-2 border">Program</th>
                            <th className="px-4 py-2 border">School</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {units.data.map((unit) => {
                            const program = programs.find((p) => p.id === unit.program_id);
                            const school = program ? schools.find((s) => s.id === program.school_id) : null;

                            return (
                                <tr key={unit.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 border">{unit.id}</td>
                                    <td className="px-4 py-2 border">{unit.name}</td>
                                    <td className="px-4 py-2 border">{unit.code}</td>
                                    <td className="px-4 py-2 border">{unit.credit_hours || 'N/A'}</td>
                                    <td className="px-4 py-2 border">{program?.name || 'N/A'}</td>
                                    <td className="px-4 py-2 border">{school?.name || 'N/A'}</td>
                                    <td className="px-4 py-2 border">
                                        <button
                                            onClick={() => handleOpenModal('edit', unit)}
                                            className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 mr-2"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => handleOpenModal('delete', unit)}
                                            className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <div className="mt-4 flex justify-between items-center">
                    <p className="text-sm text-gray-600">
                        Showing {units.data.length} of {units.total} units
                    </p>
                    <div className="flex space-x-2">
                        {units.links.map((link, index) => (
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
                            {modalType === 'create' && 'Add Unit'}
                            {modalType === 'edit' && 'Edit Unit'}
                            {modalType === 'delete' && 'Delete Unit'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={currentUnit?.name || ''}
                                        onChange={(e) =>
                                            setCurrentUnit((prev) => ({ ...prev!, name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Code</label>
                                    <input
                                        type="text"
                                        value={currentUnit?.code || ''}
                                        onChange={(e) =>
                                            setCurrentUnit((prev) => ({ ...prev!, code: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Credit Hours</label>
                                    <input
                                        type="number"
                                        value={currentUnit?.credit_hours || ''}
                                        onChange={(e) =>
                                            setCurrentUnit((prev) => ({
                                                ...prev!,
                                                credit_hours: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Program</label>
                                    <select
                                        value={currentUnit?.program_id || ''}
                                        onChange={(e) =>
                                            setCurrentUnit((prev) => ({
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
                                                {program.name} ({schools.find((s) => s.id === program.school_id)?.name || 'N/A'})
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
                                <p>Are you sure you want to delete this unit?</p>
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

export default Units;
