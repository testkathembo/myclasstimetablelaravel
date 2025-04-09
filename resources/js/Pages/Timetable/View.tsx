import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination';

interface Timetable {
    id: number;
    unit_name: string;
    classroom_name: string;
    lecturer_name: string;
    start_time: string;
    end_time: string;
}

interface Semester {
    id: number;
    name: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTimetables {
    data: Timetable[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const ViewTimetable = () => {
    const { timetables, semesters, selectedSemester, perPage, search } = usePage().props as {
        timetables: PaginatedTimetables;
        semesters: Semester[];
        selectedSemester: number | null;
        perPage: number;
        search: string;
    };

    const [semesterId, setSemesterId] = useState(selectedSemester);
    const [searchQuery, setSearchQuery] = useState(search);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/timetable', { semester_id: semesterId, search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { semester_id: semesterId, search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
        }
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/timetable', { semester_id: semesterId, search: searchQuery, per_page: newPerPage }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Timetable" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>
                <div className="flex justify-between items-center mb-4">
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <select
                            value={semesterId || ''}
                            onChange={(e) => setSemesterId(Number(e.target.value))}
                            className="border rounded p-2"
                        >
                            <option value="">All Semesters</option>
                            {semesters.map((semester) => (
                                <option key={semester.id} value={semester.id}>
                                    {semester.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search timetable..."
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
                            <th className="px-4 py-2 border">Unit</th>
                            <th className="px-4 py-2 border">Classroom</th>
                            <th className="px-4 py-2 border">Lecturer</th>
                            <th className="px-4 py-2 border">Start Time</th>
                            <th className="px-4 py-2 border">End Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        {timetables.data.map((timetable) => (
                            <tr key={timetable.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-2 border">{timetable.unit_name}</td>
                                <td className="px-4 py-2 border">{timetable.classroom_name}</td>
                                <td className="px-4 py-2 border">{timetable.lecturer_name}</td>
                                <td className="px-4 py-2 border">{timetable.start_time}</td>
                                <td className="px-4 py-2 border">{timetable.end_time}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={timetables.links} onPageChange={handlePageChange} />
            </div>
        </AuthenticatedLayout>
    );
};

export default ViewTimetable;
