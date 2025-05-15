import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Group {
    id: number;
    name: string;
    class: { id: number; name: string };
    capacity: number;
}

interface Class {
    id: number;
    name: string;
}

interface Enrollment {
    id: number;
    group_id: number;
    student_id: number;
    group: Group;
    student: { id: number; name: string };
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEnrollments {
    data: Enrollment[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Enrollments = () => {
    const { enrollments, groups, classes } = usePage().props as {
        enrollments: PaginatedEnrollments | null;
        groups: Group[] | null;
        classes: Class[] | null;
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [currentEnrollment, setCurrentEnrollment] = useState<{ student_number: string; class_id: number; group_id: number } | null>(null);
    const [filteredGroups, setFilteredGroups] = useState<Group[]>([]);

    const handleOpenModal = () => {
        setCurrentEnrollment({ student_number: '', class_id: 0, group_id: 0 });
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setCurrentEnrollment(null);
    };

    const handleClassChange = (classId: number) => {
        setCurrentEnrollment((prev) => ({ ...prev!, class_id: classId, group_id: 0 }));
        setFilteredGroups(groups?.filter((group) => group.class.id === classId) || []);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (currentEnrollment) {
            router.post('/enrollments', currentEnrollment, {
                onSuccess: () => {
                    alert('Student enrolled successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error enrolling student:', errors);
                },
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Enrollments" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Enrollments</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={handleOpenModal}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Enroll Student
                    </button>
                </div>
                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">ID</th>
                            <th className="px-4 py-2 border">Student</th>
                            <th className="px-4 py-2 border">Group</th>
                            <th className="px-4 py-2 border">Class</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollments?.data?.length ? (
                            enrollments.data.map((enrollment) => (
                                <tr key={enrollment.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 border">{enrollment.id}</td>
                                    <td className="px-4 py-2 border">{enrollment.student.name}</td>
                                    <td className="px-4 py-2 border">{enrollment.group.name}</td>
                                    <td className="px-4 py-2 border">{enrollment.group.class.name}</td>
                                    <td className="px-4 py-2 border">
                                        <button
                                            onClick={() => router.delete(`/enrollments/${enrollment.id}`)}
                                            className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={5} className="text-center py-4">
                                    No enrollments found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md" style={{ width: 'auto', maxWidth: '90%', minWidth: '300px' }}>
                        <h2 className="text-xl font-bold mb-4">Enroll Student</h2>
                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700">Student Number</label>
                                <input
                                    type="text"
                                    value={currentEnrollment?.student_number || ''}
                                    onChange={(e) =>
                                        setCurrentEnrollment((prev) => ({
                                            ...prev!,
                                            student_number: e.target.value,
                                        }))
                                    }
                                    className="w-full border rounded p-2"
                                    required
                                />
                            </div>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700">Class</label>
                                <select
                                    value={currentEnrollment?.class_id || ''}
                                    onChange={(e) => handleClassChange(parseInt(e.target.value, 10))}
                                    className="w-full border rounded p-2"
                                    required
                                >
                                    <option value="" disabled>Select a class</option>
                                    {classes?.map((classItem) => (
                                        <option key={classItem.id} value={classItem.id}>
                                            {classItem.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700">Group</label>
                                <select
                                    value={currentEnrollment?.group_id || ''}
                                    onChange={(e) =>
                                        setCurrentEnrollment((prev) => ({
                                            ...prev!,
                                            group_id: parseInt(e.target.value, 10),
                                        }))
                                    }
                                    className="w-full border rounded p-2"
                                    required
                                >
                                    <option value="" disabled>Select a group</option>
                                    {filteredGroups.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <button
                                type="submit"
                                className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                            >
                                Enroll
                            </button>
                            <button
                                type="button"
                                onClick={handleCloseModal}
                                className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                            >
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default Enrollments;