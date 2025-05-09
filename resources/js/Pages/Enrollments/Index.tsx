import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Pagination } from '@/components/ui/pagination';

const Enrollments = () => {
    const {
        enrollments = {},
        students = [],
        units = [],
        semesters = [],
        lecturers = [],
        lecturerUnitAssignments = [],
        auth,
    } = usePage().props;

    const enrollmentData = enrollments.data || [];
    const [form, setForm] = useState({ student_code: '', semester_id: '', unit_ids: [] });
    const [search, setSearch] = useState('');
    const [lecturerAssignment, setLecturerAssignment] = useState({ unit_id: '', lecturer_id: '' });
    const [currentPage, setCurrentPage] = useState(1);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [lecturerUnits, setLecturerUnits] = useState([]);
    const [lecturerName, setLecturerName] = useState('');

    const itemsPerPage = 5;
    const totalPages = Math.ceil(lecturerUnitAssignments.length / itemsPerPage);
    const paginatedAssignments = lecturerUnitAssignments.slice(
        (currentPage - 1) * itemsPerPage,
        currentPage * itemsPerPage
    );

    const handleUnitSelection = (unitId) => {
        setForm((prevForm) => {
            const unit_ids = prevForm.unit_ids.includes(unitId)
                ? prevForm.unit_ids.filter((id) => id !== unitId)
                : [...prevForm.unit_ids, unitId];
            return { ...prevForm, unit_ids };
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        Inertia.post('/enrollments', form);
    };

    const handleSearch = (e) => {
        e.preventDefault();
        Inertia.get('/enrollments', { search });
    };

    const handlePageChange = (url) => {
        if (url) {
            Inertia.get(url);
        }
    };

    const handleLecturerAssignmentSubmit = (e) => {
        e.preventDefault();
        Inertia.post('/assign-lecturers', lecturerAssignment);
    };

    const handleDeleteAssignment = async (unitId) => {
        if (confirm('Are you sure you want to delete this assignment?')) {
            try {
                await Inertia.delete(`/assign-lecturers/${unitId}`, {
                    onError: (error) => {
                        alert(`Failed to delete assignment: ${error.message || 'Unknown error'}`);
                    },
                    onSuccess: () => {
                        alert('Lecturer assignment deleted successfully.');
                    },
                });
            } catch (error) {
                console.error('Error deleting lecturer assignment:', error);
                alert('An unexpected error occurred. Please try again.');
            }
        }
    };

    const handleViewLecturerAssignments = async (lecturerId) => {
        try {
            const response = await fetch(`/lecturer-units/${lecturerId}`);
            const data = await response.json();
            setLecturerUnits(data.units || []);
            setLecturerName(`${data.lecturer.first_name} ${data.lecturer.last_name}`);
            setIsModalOpen(true);
        } catch (error) {
            console.error('Failed to fetch lecturer units:', error);
        }
    };

    const handleEditAssignment = (assignment) => {
        setLecturerAssignment({
            unit_id: assignment.unit_id,
            lecturer_id: assignment.lecturer?.id || '',
        });
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setLecturerUnits([]);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Enrollments</h1>

                <form onSubmit={handleSubmit} className="mb-6">
                    <div className="grid grid-cols-3 gap-4">
                        <select
                            value={form.student_code}
                            onChange={(e) => setForm({ ...form, student_code: e.target.value })}
                            className="border p-2 rounded"
                            required
                        >
                            <option value="">Select Student</option>
                            {students.map((student) => (
                                <option key={student.id} value={student.code}>
                                    {student.first_name} {student.last_name} ({student.code})
                                </option>
                            ))}
                        </select>
                        <select
                            value={form.semester_id}
                            onChange={(e) => setForm({ ...form, semester_id: e.target.value })}
                            className="border p-2 rounded"
                            required
                        >
                            <option value="">Select Semester</option>
                            {semesters.map((semester) => (
                                <option key={semester.id} value={semester.id}>
                                    {semester.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="mt-4">
                        <h2 className="text-lg font-semibold mb-2">Select Units</h2>
                        <div className="grid grid-cols-3 gap-4">
                            {units.map((unit) => (
                                <label key={unit.id} className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        value={unit.id}
                                        checked={form.unit_ids.includes(unit.id)}
                                        onChange={() => handleUnitSelection(unit.id)}
                                    />
                                    <span>{unit.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <button type="submit" className="mt-4 bg-blue-600 text-white px-4 py-2 rounded">
                        Enroll
                    </button>
                </form>

                <div className="flex items-center justify-between mb-4">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search enrollments..."
                        className="border p-2 rounded w-full max-w-md"
                    />
                    <button
                        onClick={handleSearch}
                        className="ml-4 bg-blue-600 text-white px-4 py-2 rounded"
                    >
                        Search
                    </button>
                </div>

                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">Student</th>
                            <th className="px-4 py-2 border">Unit</th>
                            <th className="px-4 py-2 border">Semester</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollmentData.length > 0 ? (
                            enrollmentData.map((enrollment) => (
                                <tr key={enrollment.id}>
                                    <td className="px-4 py-2 border">
                                        {enrollment.student?.first_name} {enrollment.student?.last_name}
                                    </td>
                                    <td className="px-4 py-2 border">{enrollment.unit?.name}</td>
                                    <td className="px-4 py-2 border">
                                        {enrollment.semester?.name || 'N/A'}
                                    </td>
                                    <td className="px-4 py-2 border">
                                        <button
                                            onClick={() => Inertia.delete(`/enrollments/${enrollment.id}`)}
                                            className="bg-red-600 text-white px-3 py-1 rounded"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={4} className="px-4 py-3 text-center text-gray-500">
                                    No enrollments found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                {enrollments.links && (
                    <div className="mt-4">
                        <Pagination links={enrollments.links} onPageChange={handlePageChange} />
                    </div>
                )}

                <div className="mt-10">
                    <h2 className="text-xl font-semibold mb-4">Current Lecturer Assignments</h2>
                    <form onSubmit={handleLecturerAssignmentSubmit} className="mb-6">
                        <div className="grid grid-cols-3 gap-4">
                            <select
                                value={lecturerAssignment.unit_id}
                                onChange={(e) =>
                                    setLecturerAssignment({ ...lecturerAssignment, unit_id: e.target.value })
                                }
                                className="border p-2 rounded"
                                required
                            >
                                <option value="">Select Unit</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {unit.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={lecturerAssignment.lecturer_id}
                                onChange={(e) =>
                                    setLecturerAssignment({ ...lecturerAssignment, lecturer_id: e.target.value })
                                }
                                className="border p-2 rounded"
                                required
                            >
                                <option value="">Select Lecturer</option>
                                {lecturers.map((lecturer) => (
                                    <option key={lecturer.id} value={lecturer.id}>
                                        {lecturer.first_name} {lecturer.last_name} ({lecturer.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button type="submit" className="mt-4 bg-green-600 text-white px-4 py-2 rounded">
                            Assign Lecturer
                        </button>
                    </form>

                    <table className="min-w-full border-collapse border border-gray-200">
                        <thead className="bg-gray-100">
                            <tr>
                                <th className="px-4 py-2 border">Unit</th>
                                <th className="px-4 py-2 border">Lecturer</th>
                                <th className="px-4 py-2 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedAssignments.length > 0 ? (
                                paginatedAssignments.map((assignment, idx) => (
                                    <tr key={idx}>
                                        <td className="px-4 py-2 border">{assignment.unit?.name}</td>
                                        <td className="px-4 py-2 border">
                                            {assignment.lecturer?.first_name} {assignment.lecturer?.last_name}
                                        </td>
                                        <td className="px-4 py-2 border">
                                            <button
                                                onClick={() => handleViewLecturerAssignments(assignment.lecturer?.id)}
                                                className="bg-purple-700 text-white px-3 py-1 rounded mr-2"
                                            >
                                                View
                                            </button>
                                            <button
                                                onClick={() => handleEditAssignment(assignment)}
                                                className="bg-yellow-500 text-white px-3 py-1 rounded mr-2"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => handleDeleteAssignment(assignment.unit_id)}
                                                className="bg-red-600 text-white px-3 py-1 rounded"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={3} className="text-center text-gray-500 px-4 py-3">
                                        No assignments found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination Controls */}
                    <div className="mt-4 flex justify-center">
                        <button
                            onClick={() => setCurrentPage((prev) => Math.max(prev - 1, 1))}
                            className="px-4 py-2 bg-gray-300 rounded-l"
                            disabled={currentPage === 1}
                        >
                            Previous
                        </button>
                        <span className="px-4 py-2 bg-white border">{currentPage}</span>
                        <button
                            onClick={() => setCurrentPage((prev) => Math.min(prev + 1, totalPages))}
                            className="px-4 py-2 bg-gray-300 rounded-r"
                            disabled={currentPage === totalPages}
                        >
                            Next
                        </button>
                    </div>
                </div>

                {/* Modal for Lecturer's Assigned Units */}
                {isModalOpen && (
                    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div className="bg-white p-6 rounded shadow-lg max-w-lg w-full">
                            <h2 className="text-2xl font-semibold mb-4 text-center">Lecturer Details</h2>
                            <div className="mb-4">
                                <p className="text-lg font-medium">
                                    <span className="font-semibold">Lecturer:</span> {lecturerName}
                                </p>
                                <p className="text-lg font-medium mt-2">
                                    <span className="font-semibold">Assigned Units:</span>
                                </p>
                            </div>
                            <ul className="list-disc pl-5">
                                {lecturerUnits.length > 0 ? (
                                    lecturerUnits.map((unit, index) => (
                                        <li key={index} className="mb-2 text-gray-700">
                                            <span className="font-semibold">Unit Name:</span> {unit.unit_name} <br />
                                            <span className="font-semibold">Unit Code:</span> {unit.unit_code} <br />
                                            <span className="font-semibold">Semester:</span> {unit.semester_name}
                                        </li>
                                    ))
                                ) : (
                                    <li className="text-gray-500">No units assigned.</li>
                                )}
                            </ul>
                            <div className="mt-6 flex justify-end">
                                <button
                                    onClick={closeModal}
                                    className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Enrollments;