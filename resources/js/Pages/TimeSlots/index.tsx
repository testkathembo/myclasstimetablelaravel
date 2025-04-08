import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface TimeSlot {
    id: number;
    day: string;
    date: string;
    start_time: string;
    end_time: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTimeSlots {
    data: TimeSlot[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const TimeSlots = () => {
    const { timeSlots, perPage, search } = usePage().props as { timeSlots: PaginatedTimeSlots; perPage: number; search: string };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentTimeSlot, setCurrentTimeSlot] = useState<TimeSlot | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', timeSlot: TimeSlot | null = null) => {
        setModalType(type);
        setCurrentTimeSlot(
            type === 'create'
                ? { id: 0, day: '', date: '', start_time: '', end_time: '' }
                : timeSlot
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentTimeSlot(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            router.post('/timeslots', currentTimeSlot, {
                onSuccess: () => {
                    alert('Time slot created successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error creating time slot:', errors);
                },
            });
        } else if (modalType === 'edit' && currentTimeSlot) {
            router.put(`/timeslots/${currentTimeSlot.id}`, currentTimeSlot, {
                onSuccess: () => {
                    alert('Time slot updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating time slot:', errors);
                },
            });
        } else if (modalType === 'delete' && currentTimeSlot) {
            router.delete(`/timeslots/${currentTimeSlot.id}`, {
                onSuccess: () => {
                    alert('Time slot deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting time slot:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/timeslots', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handleDateChange = (date: string) => {
        const selectedDate = new Date(date);
        const day = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }); // Get the day of the week
        setCurrentTimeSlot((prev) => ({ ...prev!, date, day })); // Update both date and day
    };

    return (
        <AuthenticatedLayout>
            <Head title="Time Slots" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Time Slots</h1>
                <button
                    onClick={() => handleOpenModal('create')}
                    className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                >
                    + Add Time Slot
                </button>
                <form onSubmit={handleSearch} className="flex items-center space-x-2">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Search classrooms..."
                        className="border rounded p-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <button
                        type="submit"
                        className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                    >
                        Search
                    </button>
                </form>
                <table className="min-w-full border-collapse border border-gray-200 mt-4">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">Day</th>
                            <th className="px-4 py-2 border">Date</th>
                            <th className="px-4 py-2 border">Start Time</th>
                            <th className="px-4 py-2 border">End Time</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {timeSlots.data.map((timeSlot) => (
                            <tr key={timeSlot.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border">{timeSlot.day}</td>
                                <td className="px-4 py-2 border">{timeSlot.date}</td>
                                <td className="px-4 py-2 border">{timeSlot.start_time}</td>
                                <td className="px-4 py-2 border">{timeSlot.end_time}</td>
                                <td className="px-4 py-2 border text-center">
                                    <button
                                        onClick={() => handleOpenModal('edit', timeSlot)}
                                        className="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', timeSlot)}
                                        className="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md">
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add Time Slot'}
                            {modalType === 'edit' && 'Edit Time Slot'}
                            {modalType === 'delete' && 'Delete Time Slot'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Date</label>
                                    <input
                                        type="date"
                                        value={currentTimeSlot?.date || ''}
                                        onChange={(e) => handleDateChange(e.target.value)} // Update date and fetch day
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Day</label>
                                    <input
                                        type="text"
                                        value={currentTimeSlot?.day || ''}
                                        readOnly // Make this field read-only
                                        className="w-full border rounded p-2 bg-gray-100"
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Start Time</label>
                                    <input
                                        type="time"
                                        value={currentTimeSlot?.start_time || ''}
                                        onChange={(e) =>
                                            setCurrentTimeSlot((prev) => ({ ...prev!, start_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">End Time</label>
                                    <input
                                        type="time"
                                        value={currentTimeSlot?.end_time || ''}
                                        onChange={(e) =>
                                            setCurrentTimeSlot((prev) => ({ ...prev!, end_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
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
                                <p>Are you sure you want to delete this time slot?</p>
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

export default TimeSlots;
