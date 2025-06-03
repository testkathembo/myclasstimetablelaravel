"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { useState, useEffect } from "react"

interface Unit {
  id: number
  code: string
  name: string
  school?: { name: string }
}

interface Student {
  id: number | null 
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
  studentCount?: number // Add the student count prop
  error?: string
}

const ClassStudents = ({ unit, students = [], unitSemester, selectedSemesterId, studentCount = 0, error }: Props) => {
  const [isLoading, setIsLoading] = useState(false)
  const [retryCount, setRetryCount] = useState(0)

  // If we have a count but no students, we can show a more specific message
  const hasCountMismatch = studentCount > 0 && students.length === 0

  // Function to retry loading students
  const handleRetry = () => {
    setIsLoading(true)
    setRetryCount((prev) => prev + 1)

    // Reload the page
    window.location.reload()
  }

  // Reset loading state after component mounts or updates
  useEffect(() => {
    setIsLoading(false)
  }, [students, error])

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

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
            <div>{error}</div>
            <button
              onClick={handleRetry}
              disabled={isLoading}
              className="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-800 rounded text-sm"
            >
              {isLoading ? "Retrying..." : "Retry"}
            </button>
          </div>
        )}

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
                <p className="text-sm text-gray-600">School</p>
                <p className="font-medium">{unit.school?.name || "N/A"}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Semester</p>
                <p className="font-medium">{unitSemester?.name || "N/A"}</p>
              </div>
            </div>
          </div>
        )}

        <div className="mb-4">
          <h2 className="text-xl font-medium mb-2">
            Enrolled Students ({students.length})
            {hasCountMismatch && <span className="text-sm text-yellow-600 ml-2">(Expected: {studentCount})</span>}
          </h2>

          {hasCountMismatch && (
            <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
              <div>
                <p>
                  There appears to be a discrepancy between the student count ({studentCount}) and the actual students
                  loaded (0).
                </p>
                <p className="text-sm mt-1">This may be due to a data synchronization issue.</p>
              </div>
              <button
                onClick={handleRetry}
                disabled={isLoading}
                className="px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded text-sm whitespace-nowrap ml-4"
              >
                {isLoading ? "Retrying..." : "Retry Loading"}
              </button>
            </div>
          )}

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
                        {enrollment.student?.email || "N/A"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
              <p className="text-yellow-700">
                {hasCountMismatch
                  ? "There should be students enrolled in this unit, but they couldn't be loaded. Please try again or contact support."
                  : "No students are currently enrolled in this unit."}
              </p>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ClassStudents
