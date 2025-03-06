import { useState } from "react";
import { Inertia } from "@inertiajs/inertia";
import { usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

interface Unit {
    id: number;
    name: string;
}

interface Semester {
    id: number;
    name: string;
    units: Unit[];
}

const ManageUnits = () => {
    const { semesters, units, auth } = usePage().props as { semesters: Semester[]; units: Unit[]; auth: { user: any } };
    const [selectedSemester, setSelectedSemester] = useState<Semester | null>(null);
    const [selectedUnits, setSelectedUnits] = useState<number[]>([]);

    const handleSemesterChange = (semesterId: number) => {
        const semester = semesters.find(s => s.id === semesterId) || null;
        setSelectedSemester(semester);
        setSelectedUnits(semester ? semester.units.map(u => u.id) : []);
    };

    const handleUnitToggle = (unitId: number) => {
        setSelectedUnits(prev =>
            prev.includes(unitId) ? prev.filter(id => id !== unitId) : [...prev, unitId]
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedSemester) {
            Inertia.post(`/semesters/${selectedSemester.id}/units`, { units: selectedUnits });
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-8xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Units for Semesters</h1>

                <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700">Select Semester</label>
                    <select
                        onChange={(e) => handleSemesterChange(parseInt(e.target.value))}
                        className="w-full border rounded p-2 mt-1"
                    >
                        <option value="">Select a semester</option>
                        {semesters.map((semester) => (
                            <option key={semester.id} value={semester.id}>
                                {semester.name}
                            </option>
                        ))}
                    </select>
                </div>

                {selectedSemester && (
                    <form onSubmit={handleSubmit}>
                        <h2 className="text-xl font-semibold mb-4">Units for {selectedSemester.name}</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {units.map((unit) => (
                                <div key={unit.id} className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={selectedUnits.includes(unit.id)}
                                        onChange={() => handleUnitToggle(unit.id)}
                                        className="mr-2"
                                    />
                                    <label>{unit.name}</label>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4">
                            <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                Save Changes
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default ManageUnits;
