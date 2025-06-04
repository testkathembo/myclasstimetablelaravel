"use client"

import type React from "react"
import { useState } from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"

interface Semester {
  id: number
  name: string
}
interface Group {
  id: number
  name: string
  class: { id: number; name: string }
  capacity: number
}
interface Class {
  id: number
  name: string
  semester_id: number
}
interface Unit {
  id: number
  name: string
  code?: string
}
interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
}
interface Enrollment {
  id: number
  student_code: string | null
  group_id: string | null
  unit_id: number
  semester_id: number
  student: Student | null
  unit: Unit | null
  group: Group | null
}

interface Props {
  semesters: Semester[]
  groups: Group[]
  classes: Class[]
  units: Unit[]
  student: Student
  enrollments: Enrollment[]
}

export default function StudentEnroll({ semesters, groups, classes, units, student, enrollments }: Props) {
  const [currentEnrollment, setCurrentEnrollment] = useState({
    code: student.code,
    semester_id: 0,
    class_id: 0,
    group_id: "",
    unit_ids: [] as number[],
  })

  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // When semester changes, filter classes
  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    }))
    setFilteredClasses(classes.filter((cls) => cls.semester_id === semesterId))
    setFilteredGroups([])
    setFilteredUnits([])
  }

  // When class changes, filter groups and fetch units for this class/semester
  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      class_id: classId,
      group_id: "",
      unit_ids: [],
    }))
    setFilteredGroups(groups.filter((group) => group.class?.id === classId))
    setFilteredUnits([])
    setIsLoading(true)
    setError(null)

    if (currentEnrollment.semester_id && classId) {
      try {
        // FIXED: Use the correct URL path that matches your routes
        const response = await axios.get("/units/by-class-and-semester", {
          params: {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        })

        console.log("API Response:", response.data) // Debug response

        // Use only the units returned by the backend
        if (response.data.success && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units)
        } else if (response.data.units && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units)
        } else {
          setFilteredUnits([])
          setError("No units found for this class and semester.")
        }
      } catch (error: any) {
        console.error("Error fetching units:", error)
        setFilteredUnits([])
        setError("Failed to fetch units. Please try again.")
      } finally {
        setIsLoading(false)
      }
    } else {
      setIsLoading(false)
    }
  }

  // When group changes, just update state (units are already filtered by class)
  const handleGroupChange = (groupId: string) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      group_id: groupId,
      unit_ids: [],
    }))
  }

  // Handle form submit
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (
      !currentEnrollment.semester_id ||
      !currentEnrollment.class_id ||
      !currentEnrollment.group_id ||
      !currentEnrollment.unit_ids.length
    ) {
      setError("Please fill all fields and select at least one unit.")
      return
    }

    setError(null)

    router.post(
      "/enroll", // Changed from "/student/enroll" to "/enroll"
      {
        semester_id: currentEnrollment.semester_id,
        group_id: currentEnrollment.group_id,
        unit_ids: currentEnrollment.unit_ids,
        code: student.code,
      },
      {
        onSuccess: () => {
          toast.success("Enrolled successfully!")
          setCurrentEnrollment({
            code: student.code,
            semester_id: 0,
            class_id: 0,
            group_id: "",
            unit_ids: [],
          })
          setFilteredClasses([])
          setFilteredGroups([])
          setFilteredUnits([])
        },
        onError: (errors) => {
          if (typeof errors.error === "string") setError(errors.error)
          else if (errors.group_id) setError(errors.group_id)
          else if (errors.unit_ids) setError(errors.unit_ids)
          else if (errors.code) setError(errors.code)
          else setError("An error occurred during enrollment. Please try again.")
        },
      },
    )
  }

  return (
    <AuthenticatedLayout>
      <Head title="Self Enroll in Units" />
      <div className="p-6 bg-white rounded-lg shadow-md max-w-2xl mx-auto mt-8">
        <h1 className="text-2xl font-semibold mb-6">Self Enroll in Units</h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Student</label>
            <input
              type="text"
              value={`${student.first_name} ${student.last_name} (${student.code})`}
              className="w-full border rounded-md p-2 bg-gray-100"
              disabled
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
            <select
              value={currentEnrollment.semester_id || ""}
              onChange={(e) => handleSemesterChange(Number(e.target.value))}
              className="w-full border rounded-md p-2"
              required
            >
              <option value="" disabled>
                Select a semester
              </option>
              {semesters.map((semester) => (
                <option key={semester.id} value={semester.id}>
                  {semester.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Class *</label>
            <select
              value={currentEnrollment.class_id || ""}
              onChange={(e) => handleClassChange(Number(e.target.value))}
              className="w-full border rounded-md p-2"
              required
              disabled={!currentEnrollment.semester_id}
            >
              <option value="" disabled>
                Select a class
              </option>
              {filteredClasses.map((classItem) => (
                <option key={classItem.id} value={classItem.id}>
                  {classItem.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Group *</label>
            <select
              value={currentEnrollment.group_id || ""}
              onChange={(e) => handleGroupChange(e.target.value)}
              className="w-full border rounded-md p-2"
              required
              disabled={!currentEnrollment.class_id}
            >
              <option value="" disabled>
                Select a group
              </option>
              {filteredGroups.map((group) => (
                <option key={group.id} value={group.id.toString()}>
                  {group.name} (Capacity: {group.capacity})
                </option>
              ))}
            </select>
          </div>
          <div>
            <div>
  <label className="block text-sm font-medium text-gray-700 mb-1">Units *</label>
  {isLoading ? (
    <div className="text-center py-4 text-gray-500">Loading units...</div>
  ) : (
    <div className="border rounded-md p-3 max-h-48 overflow-y-auto bg-gray-50">
      {filteredUnits.length > 0 ? (
        <>
          <div className="flex items-center mb-2">
            <input
              type="checkbox"
              id="selectAllUnits"
              onChange={(e) => {
                if (e.target.checked) {
                  setCurrentEnrollment((prev) => ({
                    ...prev,
                    unit_ids: filteredUnits.map((unit) => unit.id),
                  }))
                } else {
                  setCurrentEnrollment((prev) => ({
                    ...prev,
                    unit_ids: [],
                  }))
                }
              }}
              checked={
                filteredUnits.length > 0 &&
                currentEnrollment.unit_ids.length === filteredUnits.length
              }
              disabled={!filteredUnits.length || isLoading}
              className="mr-2"
            />
            <label htmlFor="selectAllUnits" className="text-sm cursor-pointer">
              Select All Units
            </label>
          </div>
          <div className="space-y-2">
            {filteredUnits.map((unit) => (
              <div key={unit.id} className="flex items-center">
                <input
                  type="checkbox"
                  id={`unit-${unit.id}`}
                  value={unit.id}
                  onChange={(e) => {
                    const unitId = Number(e.target.value)
                    setCurrentEnrollment((prev) => ({
                      ...prev,
                      unit_ids: e.target.checked
                        ? [...prev.unit_ids, unitId]
                        : prev.unit_ids.filter((id) => id !== unitId),
                    }))
                  }}
                  checked={currentEnrollment.unit_ids.includes(unit.id)}
                  disabled={isLoading}
                  className="mr-2"
                />
                <label htmlFor={`unit-${unit.id}`} className="text-sm cursor-pointer">
                  {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                </label>
              </div>
            ))}
          </div>
        </>
      ) : (
        <div className="text-sm text-gray-500 text-center py-2">
          {currentEnrollment.class_id
            ? "No units found for this class and semester."
            : "Please select a class to see available units."}
        </div>
      )}
    </div>
  )}
</div>
          </div>
          {error && <div className="p-3 bg-red-100 border border-red-400 text-red-700 rounded-md">{error}</div>}
          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={() => {
                setCurrentEnrollment({
                  code: student.code,
                  semester_id: 0,
                  class_id: 0,
                  group_id: "",
                  unit_ids: [],
                })
                setFilteredClasses([])
                setFilteredGroups([])
                setFilteredUnits([])
                setError(null)
              }}
              className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Reset
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              disabled={isLoading || !currentEnrollment.unit_ids.length}
            >
              Enroll
            </button>
          </div>
        </form>
        {/* Show current enrollments */}
        {enrollments && enrollments.length > 0 && (
          <div className="mt-8 pt-6 border-t">
            <h2 className="text-lg font-semibold mb-4">Your Current Enrollments</h2>
            <div className="space-y-2">
              {enrollments.map((enrollment) => (
                <div key={enrollment.id} className="p-3 bg-gray-50 rounded-md">
                  <div className="text-sm">
                    <strong>{enrollment.unit?.name}</strong>
                    {enrollment.group && <span className="text-gray-600 ml-2">- Group: {enrollment.group.name}</span>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}
