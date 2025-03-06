import { usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

interface Unit {
    id: number;
    name: string;
    code: string;
}

interface Semester {
    id: number;
    name: string;
    units: Unit[];
}

const ViewAssignedUnits = () => {
    const { semesters, auth } = usePage().props as { semesters: Semester[]; auth: { user: any } };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-8xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Assigned Units Per Semester</h1>

                {semesters.length > 0 ? (
                    semesters.map((semester) => (
                        <div key={semester.id} className="bg-white shadow-md rounded-lg p-4 mb-4">
                            <h2 className="text-lg font-semibold">{semester.name}</h2>
                            {semester.units.length > 0 ? (
                                <ul className="list-disc pl-5">
                                    {semester.units.map((unit) => (
                                        <li key={unit.id} className="text-gray-700">{unit.code} - {unit.name}</li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-gray-500">No units assigned.</p>
                            )}
                        </div>
                    ))
                ) : (
                    <p className="text-gray-500">No semesters found.</p>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default ViewAssignedUnits;
