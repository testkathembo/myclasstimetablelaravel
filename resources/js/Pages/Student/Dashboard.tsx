import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Calendar, BookOpen, Clock, Download } from 'lucide-react';
import { format } from 'date-fns';

interface Unit {
  id: number;
  code: string;
  name: string;
  faculty: {
    name: string;
  };
}

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
}

interface Semester {
  id: number;
  name: string;
  year: number;
  is_active: boolean;
}

interface Props {
  enrolledUnits: Unit[];
  upcomingExams: ExamTimetable[];
  currentSemester: Semester;
}

export default function Dashboard({ enrolledUnits, upcomingExams, currentSemester }: Props) {
  return (
    <AuthenticatedLayout>
      <Head title="Student Dashboard" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <h1 className="text-2xl font-semibold text-gray-900 mb-6">Student Dashboard</h1>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Current Semester */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 bg-white border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900 flex items-center">
                  <Calendar className="mr-2 h-5 w-5 text-blue-500" />
                  Current Semester
                </h2>
                <div className="mt-4">
                  <p className="text-xl font-bold">{currentSemester?.name || 'N/A'} {currentSemester?.year || ''}</p>
                </div>
              </div>
            </div>
            
            {/* Enrolled Units Summary */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 bg-white border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900 flex items-center">
                  <BookOpen className="mr-2 h-5 w-5 text-blue-500" />
                  My Enrollments
                </h2>
                <div className="mt-4">
                  <p className="text-xl font-bold">{enrolledUnits?.length || 0} Units</p>
                  <a href="/my-enrollments" className="text-blue-600 hover:underline mt-2 inline-block">
                    View all enrollments
                  </a>
                </div>
              </div>
            </div>
            
            {/* Upcoming Exams */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg col-span-1 md:col-span-2">
              <div className="p-6 bg-white border-b border-gray-200">
                <div className="flex justify-between items-center">
                  <h2 className="text-lg font-medium text-gray-900 flex items-center">
                    <Clock className="mr-2 h-5 w-5 text-blue-500" />
                    Upcoming Exams
                  </h2>
                </div>
                
                <div className="mt-4">
                  {upcomingExams?.length > 0 ? (
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                          <tr>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Date
                            </th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Unit
                            </th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Time
                            </th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Venue
                            </th>
                          </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                          {upcomingExams.map((exam) => (
                            exam?.unit && ( // Ensure `exam` and `exam.unit` are defined
                              <tr key={exam.id} className="hover:bg-gray-50">
                                <td className="px-6 py-4 whitespace-nowrap">
                                  <div className="text-sm font-medium text-gray-900">
                                    {format(new Date(exam.date), 'MMM d, yyyy')}
                                  </div>
                                  <div className="text-sm text-gray-500">{exam.day}</div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                  <div className="text-sm font-medium text-gray-900">{exam.unit.code || 'N/A'}</div>
                                  <div className="text-sm text-gray-500">{exam.unit.name || 'N/A'}</div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                  <div className="text-sm text-gray-900">
                                    {exam.start_time} - {exam.end_time}
                                  </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                  {exam.venue || 'N/A'}
                                </td>
                              </tr>
                            )
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <p className="text-gray-500">No upcoming exams found.</p>
                  )}
                  
                  <a href="/my-exams" className="text-blue-600 hover:underline mt-4 inline-block">
                    View all exams
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}