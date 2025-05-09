"use client"

import type React from "react"

import { Head } from "@inertiajs/react"
import { useState } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Unit {
  id: number
  code: string
  name: string
  faculty?: { name: string }
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Props {
  units: Unit[]
  currentSemester: Semester
  semesters: Semester[]
  selectedSemesterId: number
  lecturerSemesters: number[]
  studentCounts: Record<string, number>
  error?: string
}

const Classes = ({
  units = [],
  currentSemester,
  semesters = [],
  selectedSemesterId,
  lecturerSemesters = [],
  studentCounts = {},
  error,
}: Props) => {
  const [selectedSemester, setSelectedSemester] = useState<number>(selectedSemesterId || 0)

  // Handle semester change
  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = Number.parseInt(e.target.value)
    setSelectedSemester(semesterId)

    // Add a loading indicator or message
    const loadingMessage = document.createElement("div")
    loadingMessage.className = "fixed top-0 left-0 w-full bg-blue-500 text-white text-center py-2"
    loadingMessage.textContent = "Loading classes..."
    document.body.appendChild(loadingMessage)

    // Redirect to the same page with the new filter
    window.location.href = `/lecturer/my-classes?semester_id=${semesterId}`
  }

  // Filter available semesters to only those the lecturer is assigned to
  const availableSemesters = semesters.filter((semester) => lecturerSemesters.includes(semester.id))

  return (
    <AuthenticatedLayout>
      <Head title="My Classes" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">My Classes</h1>

        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{error}</div>}

        <div className="mb-6 flex justify-between items-center">
          <div>
            <label htmlFor="semester" className="block text-sm font-medium text-gray-700 mb-1">
              Select Semester:
            </label>
            <select
              id="semester"
              value={selectedSemester}
              onChange={handleSemesterChange}
              className="border rounded p-2 min-w-[200px]"
            >
              {availableSemesters.length > 0 ? (
                availableSemesters.map((semester) => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name} {semester.is_active ? "(Current)" : ""}
                  </option>
                ))
              ) : (
                <option value="">No semesters available</option>
              )}
            </select>
          </div>

          <div>
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
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to Dashboard
            </a>
          </div>
        </div>

        {units.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Unit Code
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Unit Name
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Faculty
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Students
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {units.map((unit) => (
                  <tr key={unit.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{unit.code}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{unit.name}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{unit.faculty?.name || "N/A"}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{studentCounts[unit.id] || 0}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 space-x-2">
                      <a
                        href={`/lecturer/my-classes/${unit.id}/students?semester_id=${selectedSemester}`}
                        className="text-blue-600 hover:text-blue-800"
                      >
                        View Students
                      </a>
                      <span className="text-gray-300">|</span>
                      <a
                        href={`/lecturer/class-timetable?unit_id=${unit.id}&semester_id=${selectedSemester}`}
                        className="text-green-600 hover:text-green-800"
                      >
                        View Timetable
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <p className="text-yellow-700">
              You don't have any assigned units for this semester. Please contact your administrator if you believe this
              is an error.
            </p>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default Classes
