"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { useState } from "react"

interface Semester {
  id: number
  name: string
}

interface Supervision {
  id: number
  unit_code: string
  unit_name: string
  venue: string
  location: string
  day: string
  date: string
  start_time: string
  end_time: string
  no: number
}

interface Unit {
  id: number
  code: string
  name: string
}

interface Props {
  supervisions: Supervision[]
  lecturerSemesters: Semester[]
  units: Unit[]
  error?: string
  message?: string
}

const ExamSupervision = ({ supervisions = [], lecturerSemesters = [], units = [], error, message }: Props) => {
  const [isLoading, setIsLoading] = useState(false)

  // Function to retry loading
  const handleRetry = () => {
    setIsLoading(true)
    window.location.reload()
  }

  // Format date for display
  const formatDate = (dateString: string) => {
    try {
      const date = new Date(dateString)
      return date.toLocaleDateString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      })
    } catch (e) {
      return dateString
    }
  }

  // Group supervisions by date for better display
  const supervisionsByDate: Record<string, Supervision[]> = {}
  supervisions.forEach((supervision) => {
    if (!supervisionsByDate[supervision.date]) {
      supervisionsByDate[supervision.date] = []
    }
    supervisionsByDate[supervision.date].push(supervision)
  })

  // Sort dates chronologically
  const sortedDates = Object.keys(supervisionsByDate).sort((a, b) => new Date(a).getTime() - new Date(b).getTime())

  return (
    <AuthenticatedLayout>
      <Head title="Exam Supervision" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-semibold">Exam Supervision Duties</h1>
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

        <div className="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
          <p className="text-blue-800">
            <strong>Total Supervision Duties:</strong> {supervisions.length}
          </p>
        </div>

        <div className="mb-6">
          <h2 className="text-lg font-semibold mb-2">Units</h2>
          <ul className="list-disc pl-5">
            {units.map((unit) => (
              <li key={unit.id}>
                <strong>{unit.code}</strong>: {unit.name}
              </li>
            ))}
          </ul>
        </div>

        {supervisions.length > 0 ? (
          <div className="space-y-6">
            {sortedDates.map((date) => (
              <div key={date} className="border rounded-lg overflow-hidden">
                <div className="bg-gray-100 px-4 py-3 font-medium border-b">
                  <h3 className="text-lg">{formatDate(date)}</h3>
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
                        {supervisionsByDate[date]
                          .sort((a, b) => a.start_time.localeCompare(b.start_time))
                          .map((supervision) => (
                            <tr key={supervision.id} className="hover:bg-gray-50">
                              <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {supervision.start_time} - {supervision.end_time}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {supervision.unit_code} - {supervision.unit_name}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {supervision.venue} {supervision.location ? `(${supervision.location})` : ""}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{supervision.no}</td>
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
            <p className="text-yellow-700">{message || "No exam supervision duties found for the current semester."}</p>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default ExamSupervision
