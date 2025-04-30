import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Calendar, Clock, MapPin, Download } from 'lucide-react';
import { format } from 'date-fns';

interface ExamTimetable {
  id: number;
  date: string;
  day: string;
  start_time: string;
  end_time: string;
  venue: string;
  location: string;
  chief_invigilator: string;
  unit: {
    id: number;
    code: string;
    name: string;
  };
  semester: {
    id: number;
    name: string;
    year: number;
  };
}

interface Unit {
  id: number;
  code: string;
  name: string;
}

interface Semester {
  id: number;
  name: string;
  year: number;
}

interface Props {
  examTimetables: ExamTimetable[];
  currentSemester: Semester;
  enrolledUnits: Unit[];
}

export default function StudentTimetable({ examTimetables, currentSemester, enrolledUnits }: Props) {
  const [selectedUnit, setSelectedUnit] = useState<number | 'all'>('all');
  
  const filteredExams = selectedUnit === 'all' 
    ? examTimetables 
    : examTimetables.filter(exam => exam.unit.id === selectedUnit);

  // Group exams by date
  const examsByDate = filteredExams.reduce((acc, exam) => {
    const date = exam.date;
    if (!acc[date]) {
      acc[date] = [];
    }
    acc[date].push(exam);
    return acc;
  }, {} as Record<string, ExamTimetable[]>);

  // Sort dates
  const sortedDates = Object.keys(examsByDate).sort();

  return (
    <AuthenticatedLayout>
      <Head title="My Exams" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="flex justify-between items-center mb-6">
            <h1 className="text-2xl font-semibold text-gray-900">My Exam Timetable</h1>
            
            <div className="flex items-center space-x-4">
              <div>
                <label htmlFor="unit-filter" className="mr-2 text-sm font-medium text-gray-700">Filter by Unit:</label>
                <select
                  id="unit-filter"
                  className="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                  value={selectedUnit}
                  onChange={(e) => setSelectedUnit(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                >
                  <option value="all">All Units</option>
                  {enrolledUnits.map((unit) => (
                    <option key={unit.id} value={unit.id}>
                      {unit.code} - {unit.name}
                    </option>
                  ))}
                </select>
              </div>
              
              <a 
                href="/my-exams/download" 
                className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:border-blue-800 focus:ring focus:ring-blue-200 transition"
              >
                <Download className="mr-2 h-4 w-4" />
                Download PDF
              </a>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6 bg-white border-b border-gray-200">
              <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <Calendar className="mr-2 h-5 w-5 text-blue-500" />
                {currentSemester.name} {currentSemester.year} Exams
              </h2>
              
              {filteredExams.length > 0 ? (
                <div>
                  {sortedDates.map((date) => (
                    <div key={date} className="mb-8">
                      <h3 className="text-md font-medium text-gray-900 mb-3 pb-2 border-b">
                        {format(new Date(date), 'EEEE, MMMM d, yyyy')}
                      </h3>
                      
                      <div className="space-y-4">
                        {examsByDate[date].map((exam) => (
                          <div key={exam.id} className="bg-gray-50 p-4 rounded-lg">
                            <div className="flex flex-col md:flex-row md:justify-between">
                              <div>
                                <h4 className="text-md font-medium">{exam.unit.code} - {exam.unit.name}</h4>
                                <div className="flex items-center mt-2">
                                  <Clock className="h-4 w-4 text-gray-500 mr-2" />
                                  <span className="text-sm text-gray-600">
                                    {exam.start_time} - {exam.end_time}
                                  </span>
                                </div>
                              </div>
                              
                              <div className="mt-3 md:mt-0">
                                <div className="flex items-center">
                                  <MapPin className="h-4 w-4 text-gray-500 mr-2" />
                                  <span className="text-sm text-gray-600">
                                    {exam.venue} {exam.location ? `(${exam.location})` : ''}
                                  </span>
                                </div>
                                <div className="text-sm text-gray-500 mt-1">
                                  Chief Invigilator: {exam.chief_invigilator}
                                </div>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-500">No exams found for the selected criteria.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}