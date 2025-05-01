import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ExamTimetable {
  id: number;
  date: string;
  day: string;
  start_time: string;
  end_time: string;
  venue: string;
  unit: {
    code: string;
    name: string;
  };
  semester: {
    name: string;
  };
}

interface Props {
  examTimetables: ExamTimetable[];
  currentSemester: {
    id: number;
    name: string;
  };
}

export default function ExamTimetable({ examTimetables, currentSemester }: Props) {
  return (
    <AuthenticatedLayout>
      <Head title="My Exam Timetable" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <h1 className="text-2xl font-semibold text-gray-900 mb-6">
            Exam Timetable - {currentSemester.name}
          </h1>

          <div className="mb-4">
            <Link
              href={route('student.exams.download')}
              className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              Download Timetable as PDF
            </Link>
          </div>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              {examTimetables.length > 0 ? (
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Day
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Time
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Venue
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Unit
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {examTimetables.map((exam) => (
                      <tr key={exam.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {exam.date}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {exam.day}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {exam.start_time} - {exam.end_time}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {exam.venue}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {exam.unit.code} - {exam.unit.name}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className="text-gray-500 mt-4">No exams scheduled for this semester.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
