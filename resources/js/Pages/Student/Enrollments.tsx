import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Enrollment {
  id: number;
  unit: {
    code: string;
    name: string;
  };
  semester: {
    name: string;
  };
  lecturer?: {
    first_name: string;
    last_name: string;
  };
  student?: {
    first_name: string;
    last_name: string;
    code: string;
  };
  group: {
    name: string;
  };
}

interface Props {
  enrollments?: {
    data?: Enrollment[];
  };
  userRoles?: {
    isAdmin: boolean;
    isLecturer: boolean;
    isStudent: boolean;
  };
}

export default function Enrollments({ enrollments = { data: [] }, userRoles }: Props) {
  const { auth } = usePage().props as any;
  const isAdmin = userRoles?.isAdmin || false;
  const isLecturer = userRoles?.isLecturer || false;
  
  return (
    <AuthenticatedLayout>
      <Head title={isAdmin ? "All Enrollments" : "My Enrollments"} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <h1 className="text-2xl font-semibold text-gray-900 mb-6">
            {isAdmin ? "All Enrollments" : (isLecturer ? "My Teaching Units" : "My Enrollments")}
          </h1>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <h2 className="text-lg font-medium text-gray-900">
                {isAdmin ? "Enrolled Students" : (isLecturer ? "Units You Teach" : "Enrolled Units")}
              </h2>
              {enrollments.data && enrollments.data.length > 0 ? (
                <div className="mt-4 overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        {isAdmin && (
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Student
                          </th>
                        )}
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Unit Code
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Unit Name
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Group 
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Semester
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Lecturer
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {enrollments.data.map((enrollment) => (
                        <tr key={enrollment.id}>
                          {isAdmin && (
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                              {enrollment.student 
                                ? `${enrollment.student.first_name} ${enrollment.student.last_name} (${enrollment.student.code})`
                                : 'N/A'}
                            </td>
                          )}
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {enrollment.unit.code}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {enrollment.unit.name}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {enrollment.group?.name || 'N/A'}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {enrollment.semester.name}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {enrollment.lecturer
                              ? `${enrollment.lecturer.first_name} ${enrollment.lecturer.last_name}`
                              : 'N/A'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-gray-500 mt-4">No enrollments found.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}