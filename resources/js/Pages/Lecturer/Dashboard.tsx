"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { useState, useEffect } from "react"

interface Unit {
  id: number
  code: string
  name: string
  school?: { name: string } // Make School optional
}

interface Semester {
  id: number
  name: string
  year?: number
}

interface SemesterUnits {
  semester: Semester
  units: Unit[]
}

interface Props {
  currentSemester: Semester
  lecturerSemesters: Semester[]
  unitsBySemester: Record<string, SemesterUnits> // Changed from number to string for object keys
  studentCounts: Record<string, Record<string, number>> // Changed from number to string for object keys
  error?: string
}

const Dashboard = ({ currentSemester, lecturerSemesters, unitsBySemester, studentCounts, error }: Props) => {
  // Safely handle potentially undefined data
  const semesterData = unitsBySemester || {}
  const studentCountsData = studentCounts || {}

  // Initialize state with safe defaults
  const [expandedSemesters, setExpandedSemesters] = useState<Record<number, boolean>>({})

  // Set up expanded semesters when component mounts or data changes
  useEffect(() => {
    if (currentSemester && Object.keys(semesterData).length > 0) {
      const initialState: Record<number, boolean> = {}
      Object.values(semesterData).forEach((data) => {
        if (data && data.semester) {
          initialState[data.semester.id] = data.semester.id === currentSemester.id
        }
      })
      setExpandedSemesters(initialState)
    }
  }, [currentSemester, semesterData])

  const toggleSemester = (semesterId: number) => {
    setExpandedSemesters((prev) => ({
      ...prev,
      [semesterId]: !prev[semesterId],
    }))
  }

  // Check if we have any data to display
  const hasSemesterData = Object.keys(semesterData).length > 0

  return (
    <AuthenticatedLayout>
      <Head title="Lecturer Dashboard" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Lecturer Dashboard</h1>

        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{error}</div>}

        {/* <p className="mb-6">
          Welcome to the Lecturer Dashboard.
          {currentSemester && (
            <span>
              {" "}
              You are currently in semester: <strong>{currentSemester.name}</strong>.
            </span>
          )}
        </p> */}       

        
        <div className="mt-8 p-4 bg-gray-50 rounded-lg border">
          <h3 className="text-lg font-medium mb-3">Quick Links</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="/lecturer/my-classes" className="p-3 bg-white rounded border hover:bg-gray-50 flex items-center">
              <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-5 w-5 text-blue-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                  />
                </svg>
              </div>
              <div>
                <div className="font-medium">My Classes</div>
                <div className="text-sm text-gray-500">View and manage your classes</div>
              </div>
            </a>

            <a
              href="/lecturer/class-timetable"
              className="p-3 bg-white rounded border hover:bg-gray-50 flex items-center"
            >
              <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-5 w-5 text-green-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
              </div>
              <div>
                <div className="font-medium">Class Timetable</div>
                <div className="text-sm text-gray-500">View your teaching schedule</div>
              </div>
            </a>

            <a
              href="/lecturer/exam-supervision"
              className="p-3 bg-white rounded border hover:bg-gray-50 flex items-center"
            >
              <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-5 w-5 text-red-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
              </div>
              <div>
                <div className="font-medium">Exam Supervision</div>
                <div className="text-sm text-gray-500">View your exam supervision duties</div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default Dashboard
