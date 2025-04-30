import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BookOpen, User, Building } from 'lucide-react';

interface Unit {
  id: number;
  code: string;
  name: string;
  description: string;
  faculty: {
    id: number;
    name: string;
  };
  lecturer: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
}

interface Semester {
  id: number;
  name: string;
  year: number;
  is_active: boolean;
}

interface Props {
  enrolledUnits: Unit[];
  semesters: Semester[];
  currentSemester: Semester;
  selectedSemesterId: number;
}

export default function Enrollments({ enrolledUnits, semesters, currentSemester, selectedSemesterId }: Props) {
  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    window.location.href = `/my-enrollments?semester_id=${e.target.value}`;
  };

  return (
    <AuthenticatedLayout>
      <Head title="My Enrollments" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="flex justify-between items-center mb-6">
            <h1 className="text-2xl font-semibold text-gray-900">My Enrollments</h1>
            
            <div className="flex items-center">
              <label htmlFor="semester" className="mr-2 text-sm font-medium text-gray-700">Semester:</label>
              <select
                id="semester"
                name="semester_id"
                className="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                value={selectedSemesterId}
                onChange={handleSemesterChange}
              >
                {semesters.map((semester) => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name} {semester.year} {semester.is_active ? '(Current)' : ''}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {enrolledUnits.length > 0 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {enrolledUnits.map((unit) => (
                <div key={unit.id} className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                  <div className="p-6 bg-white border-b border-gray-200">
                    <div className="flex items-center mb-4">
                      <BookOpen className="h-5 w-5 text-blue-500 mr-2" />
                      <h2 className="text-lg font-medium text-gray-900">{unit.code}</h2>
                    </div>
                    
                    <h3 className="text-md font-medium mb-2">{unit.name}</h3>
                    <p className="text-sm text-gray-600 mb-4">{unit.description || 'No description available.'}</p>
                    
                    <div className="border-t pt-4 mt-4">
                      <div className="flex items-center mb-2">
                        <Building className="h-4 w-4 text-gray-500 mr-2" />
                        <span className="text-sm text-gray-600">{unit.faculty.name}</span>
                      </div>
                      
                      <div className="flex items-center">
                        <User className="h-4 w-4 text-gray-500 mr-2" />
                        <span className="text-sm text-gray-600">
                          {unit.lecturer 
                            ? `${unit.lecturer.first_name} ${unit.lecturer.last_name}` 
                            : 'No lecturer assigned'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 bg-white border-b border-gray-200">
                <p className="text-gray-500">No enrollments found for the selected semester.</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}