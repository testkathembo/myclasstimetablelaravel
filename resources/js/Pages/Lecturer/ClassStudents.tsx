"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Unit {
  id: number
  code: string
  name: string
  faculty?: { name: string }
}

interface Student {
  id: number | null
  name: string
  email: string
  code: string
}

interface Enrollment {
  id: number
  student_id: number | null
  unit_id: number
  semester_id: number
  student: Student
}

interface Semester {
  id: number
  name: string
}

interface Props {
  unit: Unit | null
  students: Enrollment[]
  unitSemester: Semester | null
  selectedSemesterId: number
  error?: string
}

const ClassStudents = ({ unit, students = [], unitSemester, selectedSemesterId, error }: Props) => {
  return (
    <AuthenticatedLayout>
      <Head title={unit ? `Students - ${unit.code}` : "Students"} />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-semibold">
            {unit ? `Students Enrolled in ${unit.code} - ${unit.name}` : "Students"}
          </h1>
          <div className="flex space-x-2">
            <a
              href={`/lecturer/my-classes?semester_id=${selectedSemesterId}`}
              className="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-4 w-4 mr-2"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to Classes
            </a>
            <a
              href="/lecturer/dashboard"
              className="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-4 w-4 mr-2"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
              Dashboard
            </a>
          </div>
        </div>

        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{error}</div>}

        {unit && (
          <div className="mb-6 bg-gray-50 p-4 rounded-lg border">
            <h2 className="text-lg font-medium mb-2">Unit Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-600">Code</p>
                <p className="font-medium">{unit.code}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Name</p>
                <p className="font-medium">{unit.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Faculty</p>
                <p className="font-medium">{unit.faculty?.name || "N/A"}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Semester</p>
                <p className="font-medium">{unitSemester?.name || "N/A"}</p>
              </div>
            </div>
          </div>
        )}

        <div className="mb-4">
          <h2 className="text-xl font-medium mb-2">Enrolled Students ({students.length})</h2>
          {students.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th
                      scope="col"
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      Student ID
                    </th>
                    <th
                      scope="col"
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      Name
                    </th>
                    <th
                      scope="col"
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      Email
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {students.map((enrollment) => (
                    <tr key={enrollment.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {enrollment.student?.code || "N/A"}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {enrollment.student?.name || "N/A"}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {enrollment.student?.email || "N/A"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
              <p className="text-yellow-700">No students are currently enrolled in this unit.</p>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ClassStudents
