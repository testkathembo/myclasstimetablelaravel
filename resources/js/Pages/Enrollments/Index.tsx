import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const Enrollments = () => {
    const { enrollments = {}, students, units, groups, auth } = usePage().props;
    const enrollmentData = enrollments.data || []; // Safely access the data key
    const [form, setForm] = useState({ student_id: '', unit_id: '', group_id: '' });

    const handleSubmit = (e) => {
        e.preventDefault();
        Inertia.post('/enrollments', form);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Enrollments</h1>

                <form onSubmit={handleSubmit} className="mb-6">
                    <div className="grid grid-cols-3 gap-4">
                        <select
                            value={form.student_id}
                            onChange={(e) => setForm({ ...form, student_id: e.target.value })}
                            className="border p-2 rounded"
                            required
                        >
                            <option value="">Select Student</option>
                            {students.map((student) => (
                                <option key={student.id} value={student.id}>
                                    {student.first_name} {student.last_name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={form.unit_id}
                            onChange={(e) => setForm({ ...form, unit_id: e.target.value })}
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
                            value={form.group_id}
                            onChange={(e) => setForm({ ...form, group_id: e.target.value })}
                            className="border p-2 rounded"
                            required
                        >
                            <option value="">Select Group</option>
                            {groups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name} ({group.students_count}/35)
                                </option>
                            ))}
                        </select>
                    </div>
                    <button type="submit" className="mt-4 bg-blue-600 text-white px-4 py-2 rounded">
                        Enroll
                    </button>
                </form>

                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">Student</th>
                            <th className="px-4 py-2 border">Unit</th>
                            <th className="px-4 py-2 border">Group</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollmentData.length > 0 ? (
                            enrollmentData.map((enrollment) => (
                                <tr key={enrollment.id}>
                                    <td className="px-4 py-2 border">
                                        {enrollment.student.first_name} {enrollment.student.last_name}
                                    </td>
                                    <td className="px-4 py-2 border">{enrollment.unit.name}</td>
                                    <td className="px-4 py-2 border">{enrollment.group.name}</td>
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
            </div>
        </AuthenticatedLayout>
    );
};

export default Enrollments;
