import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination';

interface ExamTimetable {
    id: number;
    day: string;
    date: string;
    unit_code: string;
    unit_name: string;
    group: string;
    venue: string;
    no: number;
    chief_invigilator: string;
    start_time: string; // Added missing column
    end_time: string;   // Added missing column
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedExamTimetables {
    data: ExamTimetable[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const ExamTimetable = () => {
    const { examTimetables, perPage, search } = usePage().props as { examTimetables: PaginatedExamTimetables; perPage: number; search: string };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentTimetable, setCurrentTimetable] = useState<ExamTimetable | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', timetable: ExamTimetable | null = null) => {
        setModalType(type);
        setCurrentTimetable(
            type === 'create'
                ? { id: 0, day: '', date: '', unit_code: '', unit_name: '', group: '', venue: '', no: 0, chief_invigilator: '', start_time: '', end_time: '' }
                : timetable
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentTimetable(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            router.post('/exam-timetables', currentTimetable, {
                onSuccess: () => {
                    alert('Exam timetable created successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error creating exam timetable:', errors);
                },
            });
        } else if (modalType === 'edit' && currentTimetable) {
            router.put(`/exam-timetables/${currentTimetable.id}`, currentTimetable, {
                onSuccess: () => {
                    alert('Exam timetable updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating exam timetable:', errors);
                },
            });
        } else if (modalType === 'delete' && currentTimetable) {
            router.delete(`/exam-timetables/${currentTimetable.id}`, {
                onSuccess: () => {
                    alert('Exam timetable deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting exam timetable:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/exam-timetables', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/exam-timetables', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Exam Timetable" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Exam
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search exam timetable..."
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
                            Rows per page:
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
                            <th className="px-4 py-2 border">Day</th>
                            <th className="px-4 py-2 border">Date</th>
                            <th className="px-4 py-2 border">Start Time</th> 
                            <th className="px-4 py-2 border">End Time</th>   
                            <th className="px-4 py-2 border">Unit Code</th>
                            <th className="px-4 py-2 border">Unit Name</th>
                            <th className="px-4 py-2 border">Group</th>
                            <th className="px-4 py-2 border">Venue</th>
                            <th className="px-4 py-2 border">No</th>
                            <th className="px-4 py-2 border">Chief Invigilator</th>                            
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {examTimetables.data.map((timetable) => (
                            <tr key={timetable.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border">{timetable.day}</td>
                                <td className="px-4 py-2 border">{timetable.date}</td>
                                <td className="px-4 py-2 border">{timetable.unit_code}</td>
                                <td className="px-4 py-2 border">{timetable.unit_name}</td>
                                <td className="px-4 py-2 border">{timetable.group}</td>
                                <td className="px-4 py-2 border">{timetable.venue}</td>
                                <td className="px-4 py-2 border">{timetable.no}</td>
                                <td className="px-4 py-2 border">{timetable.chief_invigilator}</td>
                                <td className="px-4 py-2 border">{timetable.start_time}</td> {/* Added column */}
                                <td className="px-4 py-2 border">{timetable.end_time}</td>   {/* Added column */}
                                <td className="px-4 py-2 border text-center">
                                    <button
                                        onClick={() => handleOpenModal('edit', timetable)}
                                        className="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', timetable)}
                                        className="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={examTimetables.links} onPageChange={handlePageChange} />
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md w-96">
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add Exam Timetable'}
                            {modalType === 'edit' && 'Edit Exam Timetable'}
                            {modalType === 'delete' && 'Delete Exam Timetable'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Day</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.day || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, day: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Date</label>
                                    <input
                                        type="date"
                                        value={currentTimetable?.date || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, date: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Start Time</label>
                                    <input
                                        type="time"
                                        value={currentTimetable?.start_time || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, start_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">End Time</label>
                                    <input
                                        type="time"
                                        value={currentTimetable?.end_time || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, end_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Unit Code</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.unit_code || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, unit_code: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Unit Name</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.unit_name || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, unit_name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Group</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.group || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, group: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Venue</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.venue || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, venue: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">No</label>
                                    <input
                                        type="number"
                                        value={currentTimetable?.no || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, no: parseInt(e.target.value, 10) }))
                                        }
                                        className="w-full border rounded p-2"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Chief Invigilator</label>
                                    <input
                                        type="text"
                                        value={currentTimetable?.chief_invigilator || ''}
                                        onChange={(e) =>
                                            setCurrentTimetable((prev) => ({ ...prev!, chief_invigilator: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                    />
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
                                <p>Are you sure you want to delete this exam timetable?</p>
                                <div className="mt-4 flex justify-end">
                                    <button
                                        onClick={handleSubmit}
                                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        type="button"
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

export default ExamTimetable;
