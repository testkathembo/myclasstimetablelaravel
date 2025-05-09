"use client"

import type React from "react"

import { Head } from "@inertiajs/react"
import { useState } from "react"
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
  unit_name: string
  room_name: string
  day: string
  start_time: string
  end_time: string
  class_type?: string
}

interface Props {
  classTimetables: ClassTimetable[]
  currentSemester: Semester
  selectedSemesterId: number
  selectedUnitId?: number
  assignedUnits: Unit[]
  error?: string
}

const ClassTimetable = ({
  classTimetables = [],
  currentSemester,
  selectedSemesterId,
  selectedUnitId,
  assignedUnits = [],
  error,
}: Props) => {
  const [unitFilter, setUnitFilter] = useState<number | undefined>(selectedUnitId)

  // Handle unit filter change
  const handleUnitFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const unitId = e.target.value ? Number.parseInt(e.target.value) : undefined
    setUnitFilter(unitId)

    // Redirect to the same page with the new filter
    const url = unitId
      ? `/lecturer/class-timetable?semester_id=${selectedSemesterId}&unit_id=${unitId}`
      : `/lecturer/class-timetable?semester_id=${selectedSemesterId}`

    window.location.href = url
  }

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

        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{error}</div>}

        <div className="mb-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between">
            <div className="mb-4 md:mb-0">
              <label htmlFor="unit-filter" className="block text-sm font-medium text-gray-700 mb-1">
                Filter by Unit:
              </label>
              <select
                id="unit-filter"
                value={unitFilter || ""}
                onChange={handleUnitFilterChange}
                className="border rounded p-2 min-w-[250px]"
              >
                <option value="">All Units</option>
                {assignedUnits.map((unit) => (
                  <option key={unit.id} value={unit.id}>
                    {unit.code} - {unit.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <p className="text-blue-800 text-sm">
                <strong>Current Semester:</strong> {currentSemester?.name || "N/A"}
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
                            Room
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Type
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
                                {timetable.unit_name}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {timetable.room_name}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {timetable.class_type || "Lecture"}
                              </td>
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
