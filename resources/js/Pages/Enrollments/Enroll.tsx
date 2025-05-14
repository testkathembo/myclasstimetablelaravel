import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Unit {
    id: number;
    name: string;
    code: string;
}

interface Semester {
    id: number;
    name: string;
}

interface Props {
    units: Unit[];
    semesters: Semester[];
    activeSemester: Semester | null;
}

const Enroll = ({ units, semesters, activeSemester }: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        unit_id: '',
        semester_id: activeSemester ? activeSemester.id : '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/enroll', {
            onSuccess: () => alert('Enrollment successful!'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Enroll in Units" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Enroll in Units</h1>
                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">Unit</label>
                        <select
                            value={data.unit_id}
                            onChange={(e) => setData('unit_id', e.target.value)}
                            className="w-full border rounded p-2"
                        >
                            <option value="">Select a unit</option>
                            {units.map((unit) => (
                                <option key={unit.id} value={unit.id}>
                                    {unit.code} - {unit.name}
                                </option>
                            ))}
                        </select>
                        {errors.unit_id && <p className="text-red-500 text-sm mt-1">{errors.unit_id}</p>}
                    </div>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">Semester</label>
                        <select
                            value={data.semester_id}
                            onChange={(e) => setData('semester_id', e.target.value)}
                            className="w-full border rounded p-2"
                        >
                            <option value="">Select a semester</option>
                            {semesters.map((semester) => (
                                <option key={semester.id} value={semester.id}>
                                    {semester.name}
                                </option>
                            ))}
                        </select>
                        {errors.semester_id && <p className="text-red-500 text-sm mt-1">{errors.semester_id}</p>}
                    </div>
                    <button
                        type="submit"
                        className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        disabled={processing}
                    >
                        Enroll
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
};

export default Enroll;
