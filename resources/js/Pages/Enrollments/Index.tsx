import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const Enrollments = () => {
    const { enrollments = {}, students, units, semesters, auth } = usePage().props;
    const enrollmentData = enrollments.data || [];
    const [form, setForm] = useState({ student_id: '', semester_id: '', unit_ids: [] });

    const handleUnitSelection = (unitId) => {
        setForm((prevForm) => {
            const unit_ids = prevForm.unit_ids.includes(unitId)
                ? prevForm.unit_ids.filter((id) => id !== unitId) // Remove unit if already selected
                : [...prevForm.unit_ids, unitId]; // Add unit if not selected
            return { ...prevForm, unit_ids };
        });
    };

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

                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">Student</th>
                            <th className="px-4 py-2 border">Unit</th>
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
                                <td colSpan={3} className="px-4 py-3 text-center text-gray-500">
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
