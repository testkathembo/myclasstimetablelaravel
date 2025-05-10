"use client"

import type React from "react"

import { Head } from "@inertiajs/react"
import { useState, useEffect } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Unit {
  id: number
  code: string
  name: string
}

interface Semester {
  id: number
  name: string
}

interface ClassTimetable {
  id: number
  unit_id: number
  semester_id: number
  unit?: {
    id: number
    code: string
    name: string
  }
  semester?: {
    id: number
    name: string
  }
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no?: number
  lecturer?: string
}

interface Props {
  classTimetables: ClassTimetable[]
  currentSemester: Semester | null
  selectedSemesterId: number | null
  selectedUnitId?: number | null
  assignedUnits: Unit[]
  lecturerSemesters: Semester[]
  showAllByDefault?: boolean
  error?: string
}

const ClassTimetable = ({
  classTimetables = [],
  currentSemester,
  selectedSemesterId,
  selectedUnitId,
  assignedUnits = [],
  lecturerSemesters = [],
  showAllByDefault = true,
  error,
}: Props) => {
  const [unitFilter, setUnitFilter] = useState<number | undefined>(selectedUnitId || undefined)
  const [semesterFilter, setSemesterFilter] = useState<number | undefined>(selectedSemesterId || undefined)
  const [isLoading, setIsLoading] = useState(false)
  const [retryCount, setRetryCount] = useState(0)

  // Handle semester filter changes
  const handleSemesterFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = e.target.value ? Number.parseInt(e.target.value) : undefined
    setSemesterFilter(semesterId)

    // Show loading indicator
    setIsLoading(true)

    // Redirect to the same page with the new filter
    let url = semesterId ? `/lecturer/class-timetable?semester_id=${semesterId}` : `/lecturer/class-timetable`

    // Add unit_id to the URL if it's selected
    if (unitFilter) {
      url += `&unit_id=${unitFilter}`
    }

    window.location.href = url
  }

  // Handle unit filter changes
  const handleUnitFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const unitId = e.target.value ? Number.parseInt(e.target.value) : undefined
    setUnitFilter(unitId)

    // Show loading indicator
    setIsLoading(true)

    // Redirect to the same page with the new filter
    let url = unitId ? `/lecturer/class-timetable?unit_id=${unitId}` : `/lecturer/class-timetable`

    // Add semester_id to the URL if it's selected
    if (semesterFilter) {
      url += `&semester_id=${semesterFilter}`
    }

    window.location.href = url
  }

  // Function to retry loading timetable
  const handleRetry = () => {
    setIsLoading(true)
    setRetryCount((prev) => prev + 1)

    // Reload the page
    window.location.reload()
  }

  // Function to clear all filters
  const handleClearFilters = () => {
    setIsLoading(true)
    window.location.href = `/lecturer/class-timetable`
  }

  // Reset loading state after component mounts or updates
  useEffect(() => {
    setIsLoading(false)
  }, [classTimetables, error])

  // Group timetables by day for better display
  const timetablesByDay: Record<string, ClassTimetable[]> = {}
  classTimetables.forEach((timetable) => {
    if (!timetablesByDay[timetable.day]) {
      timetablesByDay[timetable.day] = []
    }
    timetablesByDay[timetable.day].push(timetable)
  })

  // Sort days in a logical order
  const sortedDays = Object.keys(timetablesByDay).sort((a, b) => {
    const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]
    return days.indexOf(a) - days.indexOf(b)
  })

  return (
    <AuthenticatedLayout>
      <Head title="Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-semibold">Class Timetable</h1>
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

        <div className="mb-6">
          <div className="flex flex-col md:flex-row md:items-end md:justify-between">
            <div className="mb-4 md:mb-0 flex flex-col md:flex-row md:space-x-4">
              <div>
                <label htmlFor="unit-filter" className="block text-sm font-medium text-gray-700 mb-1">
                  Filter by Unit:
                </label>
                <select
                  id="unit-filter"
                  value={unitFilter || ""}
                  onChange={handleUnitFilterChange}
                  className="border rounded p-2 min-w-[250px]"
                  disabled={isLoading}
                >
                  <option value="">All Units</option>
                  {assignedUnits.map((unit) => (
                    <option key={unit.id} value={unit.id}>
                      {unit.code} - {unit.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="semester-filter" className="block text-sm font-medium text-gray-700 mb-1">
                  Filter by Semester:
                </label>
                <select
                  id="semester-filter"
                  value={semesterFilter || ""}
                  onChange={handleSemesterFilterChange}
                  className="border rounded p-2 min-w-[250px]"
                  disabled={isLoading}
                >
                  <option value="">All Semesters</option>
                  {lecturerSemesters?.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
                    </option>
                  ))}
                </select>
              </div>
              {(unitFilter || semesterFilter) && (
                <div className="mt-4 md:mt-0 md:self-end">
                  <button
                    onClick={handleClearFilters}
                    className="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm"
                    disabled={isLoading}
                  >
                    Clear Filters
                  </button>
                </div>
              )}
            </div>
            <div className="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <p className="text-blue-800 text-sm">
                <strong>Selected Semester:</strong>{" "}
                {currentSemester?.name || (semesterFilter ? "Loading..." : "All Semesters")}
              </p>
            </div>
          </div>
        </div>

        {classTimetables.length > 0 ? (
          <div className="space-y-6">
            {sortedDays.map((day) => (
              <div key={day} className="border rounded-lg overflow-hidden">
                <div className="bg-gray-100 px-4 py-3 font-medium border-b">
                  <h3 className="text-lg">{day}</h3>
                </div>
                <div className="p-0">
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Time
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Unit
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Semester
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Venue
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Students
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {timetablesByDay[day]
                          .sort((a, b) => a.start_time.localeCompare(b.start_time))
                          .map((timetable) => (
                            <tr key={timetable.id} className="hover:bg-gray-50">
                              <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {timetable.start_time} - {timetable.end_time}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {timetable.unit?.name || "Unknown Unit"}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {timetable.semester?.name || "Unknown Semester"}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {timetable.venue} {timetable.location ? `(${timetable.location})` : ""}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{timetable.no || 0}</td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <p className="text-yellow-700">
              No class timetable entries found for the selected criteria. Please check your schedule with the academic
              office.
            </p>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default ClassTimetable
