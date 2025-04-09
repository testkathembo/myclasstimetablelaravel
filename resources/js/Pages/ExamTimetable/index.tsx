import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination';

interface ExamTimetable {
    id: number;
    day: string;
    date: string;
    unit_id: number;
    unit_name: string;
    group: string;
    venue: string;
    no: number;
    chief_invigilator: string;
    start_time: string;
    end_time: string;
    semester_id: number;
}

interface Enrollment {
    id: number;
    unit_name: string; // Assuming enrollments table has a unit_name column
    semester_id: number;
}

interface Semester {
    id: number;
    name: string;
}

interface TimeSlot {
    id: number;
    start_time: string;
    end_time: string;
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

interface FormState {
    id: number;
    day: string;
    date: string;
    enrollment_id: number;
    group: string;
    venue: string;
    no: number;
    chief_invigilator: string;
    start_time: string;
    end_time: string;
    semester_id: number;
}

const ExamTimetable = () => {
    const { examTimetables, perPage, search, semesters, enrollments, timeSlots } = usePage().props as {
        examTimetables: PaginatedExamTimetables;
        perPage: number;
        search: string;
        semesters: Semester[];
        enrollments: Enrollment[];
        timeSlots: TimeSlot[];
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [selectedSemester, setSelectedSemester] = useState<number | null>(null);
    const [selectedTimeSlotId, setSelectedTimeSlotId] = useState<number | null>(null);
    const [formState, setFormState] = useState<FormState | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const filteredEnrollments = selectedSemester
        ? enrollments.filter(enrollment => enrollment.semester_id === selectedSemester)
        : [];

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', timetable: ExamTimetable | null = null) => {
        setModalType(type);
        if (type === 'create') {
            setFormState({
                id: 0,
                day: '',
                date: '',
                enrollment_id: 0,
                group: '',
                venue: '',
                no: 0,
                chief_invigilator: '',
                start_time: '',
                end_time: '',
                semester_id: 0,
            });
        } else if (timetable) {
            setFormState({
                id: timetable.id,
                day: timetable.day,
                date: timetable.date,
                enrollment_id: timetable.unit_id,
                group: timetable.group,
                venue: timetable.venue,
                no: timetable.no,
                chief_invigilator: timetable.chief_invigilator,
                start_time: timetable.start_time,
                end_time: timetable.end_time,
                semester_id: timetable.semester_id,
            });
            setSelectedSemester(timetable.semester_id);
        }
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setFormState(null);
        setSelectedSemester(null);
        setSelectedTimeSlotId(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = modalType === 'create' ? '/exam-timetables' : `/exam-timetables/${formState?.id}`;
        const method = modalType === 'create' ? router.post : router.put;
        method(url, formState, {
            onSuccess: () => {
                alert(`Exam timetable ${modalType === 'create' ? 'created' : 'updated'} successfully!`);
                handleCloseModal();
            },
            onError: (errors) => {
                console.error('Error saving exam timetable:', errors);
            },
        });
    };

    const handleTimeSlotSelect = (slotId: number) => {
        const slot = timeSlots.find(t => t.id === slotId);
        if (slot) {
            setFormState((prev) => ({
                ...prev!,
                start_time: slot.start_time,
                end_time: slot.end_time,
            }));
            setSelectedTimeSlotId(slotId);
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
                            className="border rounded p-2 w-64"
                        />
                        <button type="submit" className="bg-blue-500 text-white px-4 py-2 rounded">Search</button>
                    </form>
                    <div>
                        <label className="mr-2">Rows per page:</label>
                        <select value={itemsPerPage} onChange={handlePerPageChange} className="border rounded p-2">
                            {[5, 10, 15, 20].map(size => (
                                <option key={size} value={size}>{size}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* TABLE omitted for brevity */}

                {/* Modal */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <form onSubmit={handleSubmit}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                <select
                                    className="w-full border rounded p-2 mb-3"
                                    value={formState?.semester_id || ''}
                                    onChange={(e) => {
                                        const id = parseInt(e.target.value);
                                        setSelectedSemester(id);
                                        setFormState((prev) => ({ ...prev!, semester_id: id, enrollment_id: 0 }));
                                    }}
                                    required
                                >
                                    <option value="">Select semester</option>
                                    {semesters.map(s => (
                                        <option key={s.id} value={s.id}>{s.name}</option>
                                    ))}
                                </select>

                                <label className="block text-sm font-medium text-gray-700 mb-1">Enrollment</label>
                                <select
                                    className="w-full border rounded p-2 mb-3"
                                    value={formState?.enrollment_id || ''}
                                    onChange={(e) =>
                                        setFormState((prev) => ({ ...prev!, enrollment_id: parseInt(e.target.value) }))
                                    }
                                    required
                                >
                                    <option value="">Select enrollment</option>
                                    {filteredEnrollments.map(enrollment => (
                                        <option key={enrollment.id} value={enrollment.id}>
                                            {enrollment.unit_name}
                                        </option>
                                    ))}
                                </select>

                                <label className="block text-sm font-medium text-gray-700 mb-1">Time Slot</label>
                                <select
                                    className="w-full border rounded p-2 mb-3"
                                    value={selectedTimeSlotId || ''}
                                    onChange={(e) => handleTimeSlotSelect(parseInt(e.target.value))}
                                >
                                    <option value="">Select time slot</option>
                                    {timeSlots.map(slot => (
                                        <option key={slot.id} value={slot.id}>
                                            {slot.start_time} - {slot.end_time}
                                        </option>
                                    ))}
                                </select>

                                <input type="text" placeholder="Day" value={formState?.day || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, day: e.target.value }))} className="w-full border rounded p-2 mb-3" required />
                                <input type="date" value={formState?.date || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, date: e.target.value }))} className="w-full border rounded p-2 mb-3" required />
                                <input type="text" placeholder="Group" value={formState?.group || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, group: e.target.value }))} className="w-full border rounded p-2 mb-3" />
                                <input type="text" placeholder="Venue" value={formState?.venue || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, venue: e.target.value }))} className="w-full border rounded p-2 mb-3" />
                                <input type="number" placeholder="No" value={formState?.no || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, no: parseInt(e.target.value) }))} className="w-full border rounded p-2 mb-3" />
                                <input type="text" placeholder="Chief Invigilator" value={formState?.chief_invigilator || ''} onChange={(e) => setFormState((prev) => ({ ...prev!, chief_invigilator: e.target.value }))} className="w-full border rounded p-2 mb-3" />
                                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">Submit</button>
                                <button type="button" onClick={handleCloseModal} className="ml-2 bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default ExamTimetable;