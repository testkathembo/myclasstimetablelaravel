import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Enrollment {
  id: number;
  unit: { code: string; name: string };
  semester: { name: string };
  group?: { name: string };
}

interface Props {
  enrollments?: Enrollment[];
  currentSemester?: { name: string };
  downloadUrl?: string;
}

export default function StudentTimetable({ enrollments = [], currentSemester, downloadUrl }: Props) {
  return (
    <AuthenticatedLayout>
      <Head title="My Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-2">
          <h1 className="text-2xl font-semibold">My Timetable</h1>
          {currentSemester && (
            <span className="inline-block bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded font-medium">
              Active Semester: {currentSemester.name}
            </span>
          )}
          {downloadUrl && (
            <a
              href={downloadUrl}
              className="inline-block bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded shadow transition"
              download
            >
              Download Timetable
            </a>
          )}
        </div>
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 bg-white border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900 mb-4">Enrolled Units</h2>
            {enrollments.length === 0 ? (
              <p className="text-gray-500 mt-4">No timetable entries found.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full border-collapse border border-gray-200">
                  <thead className="bg-gray-100">
                    <tr>
                      <th className="px-4 py-2 border">Unit Code</th>
                      <th className="px-4 py-2 border">Unit Name</th>
                      <th className="px-4 py-2 border">Group</th>
                      <th className="px-4 py-2 border">Semester</th>
                    </tr>
                  </thead>
                  <tbody>
                    {enrollments.map((enrollment) => (
                      <tr key={enrollment.id} className="hover:bg-gray-50">
                        <td className="px-4 py-2 border">{enrollment.unit?.code || "N/A"}</td>
                        <td className="px-4 py-2 border">{enrollment.unit?.name || "N/A"}</td>
                        <td className="px-4 py-2 border">{enrollment.group?.name || "N/A"}</td>
                        <td className="px-4 py-2 border">{enrollment.semester?.name || "N/A"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}