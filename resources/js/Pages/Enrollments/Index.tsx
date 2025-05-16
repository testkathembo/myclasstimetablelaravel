"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"

// Define interfaces
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
  program?: { id: number; name: string }
  school?: { id: number; name: string }
}

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  name?: string
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

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedEnrollments {
  data: Enrollment[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

const Enrollments = () => {
  const {
    enrollments,
    semesters = [],
    groups = [],
    classes = [],
    units = [],
  } = usePage().props as {
    enrollments: PaginatedEnrollments | null
    semesters: Semester[] | null
    groups: Group[] | null
    classes: Class[] | null
    units: Unit[] | null
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [currentEnrollment, setCurrentEnrollment] = useState<{
    code: string
    semester_id: number
    class_id: number
    group_id: string
    unit_id: number
  } | null>(null)
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fetchAttempted, setFetchAttempted] = useState(false)

  const handleOpenModal = () => {
    setCurrentEnrollment({
      code: "",
      semester_id: 0,
      class_id: 0,
      group_id: "",
      unit_id: 0,
    })
    setIsModalOpen(true)
    setFetchAttempted(false)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setError(null)
  }

  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_id: 0,
    }))
    setFilteredClasses((classes || []).filter((cls) => cls.semester_id === semesterId))
    setFilteredGroups([])
    setFilteredUnits([])
    setFetchAttempted(false)
  }

  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      class_id: classId,
      group_id: "",
      unit_id: 0,
    }))
    setFilteredGroups((groups || []).filter((group) => group.class?.id === classId))
    setFilteredUnits([])
    setIsLoading(true)
    setError(null)
    setFetchAttempted(true)

    if (currentEnrollment?.semester_id) {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
        console.log("Fetching units for semester_id:", currentEnrollment.semester_id, "and class_id:", classId)

        const response = await axios.post(
          "/api/units/by-class-and-semester",
          {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          {
            headers: {
              "X-CSRF-TOKEN": csrfToken,
              "Content-Type": "application/json",
              Accept: "application/json",
            },
          },
        )

        console.log("Units response:", response.data)

        if (Array.isArray(response.data) && response.data.length > 0) {
          setFilteredUnits(response.data)
        } else {
          // If no units found, try to use units from the main units array that might match this class
          const selectedClass = (classes || []).find((c) => c.id === classId)
          if (selectedClass && selectedClass.name.includes("BBIT")) {
            const relevantUnits = (units || []).filter(
              (unit) =>
                unit.code?.startsWith("BIT") ||
                unit.code?.startsWith("CS") ||
                unit.name.includes("Information Technology") ||
                unit.name.includes("Database") ||
                unit.name.includes("Software") ||
                unit.name.includes("Programming") ||
                unit.name.includes("Computer Science"),
            )

            console.log("Using fallback units for BBIT class:", relevantUnits)
            setFilteredUnits(relevantUnits)
          } else {
            setFilteredUnits([])
            setError("No units found for this class and semester. Please contact the administrator.")
          }
        }
      } catch (error: any) {
        console.error("Error fetching units:", error.response?.data || error.message)
        setError("Failed to fetch units. Please try again or contact the administrator.")

        // Try to use fallback units from the main units array
        const selectedClass = (classes || []).find((c) => c.id === classId)
        if (selectedClass && selectedClass.name.includes("BBIT")) {
          const fallbackUnits = (units || []).filter(
            (unit) =>
              unit.code?.startsWith("BIT") ||
              unit.code?.startsWith("CS") ||
              unit.name.includes("Information Technology") ||
              unit.name.includes("Database") ||
              unit.name.includes("Software") ||
              unit.name.includes("Programming") ||
              unit.name.includes("Computer Science"),
          )

          if (fallbackUnits.length > 0) {
            console.log("Using fallback units after error:", fallbackUnits)
            setFilteredUnits(fallbackUnits)
          }
        }
      } finally {
        setIsLoading(false)
      }
    } else {
      setError("Please select a semester before selecting a class.")
      setIsLoading(false)
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (currentEnrollment) {
      // Validate form
      if (!currentEnrollment.code.trim()) {
        setError("Student code is required")
        return
      }

      if (!currentEnrollment.semester_id) {
        setError("Please select a semester")
        return
      }

      if (!currentEnrollment.class_id) {
        setError("Please select a class")
        return
      }

      if (!currentEnrollment.group_id) {
        setError("Please select a group")
        return
      }

      if (!currentEnrollment.unit_id) {
        setError("Please select a unit")
        return
      }

      console.log("Submitting enrollment:", currentEnrollment)

      router.post("/enrollments", currentEnrollment, {
        onSuccess: () => {
          toast.success("Student enrolled successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Enrollment errors:", errors)
          if (errors.group_id) {
            setError(errors.group_id)
          } else if (errors.code) {
            setError(errors.code)
          } else if (errors.error) {
            setError(errors.error)
          } else {
            setError("An error occurred during enrollment. Please try again.")
          }
        },
      })
    }
  }

  // If no units are found after API call, try to use fallback units
  useEffect(() => {
    if (fetchAttempted && !isLoading && filteredUnits.length === 0 && currentEnrollment?.class_id) {
      const selectedClass = (classes || []).find((c) => c.id === currentEnrollment.class_id)

      if (selectedClass && selectedClass.name.includes("BBIT")) {
        const fallbackUnits = (units || []).filter(
          (unit) =>
            unit.code?.startsWith("BIT") ||
            unit.code?.startsWith("CS") ||
            unit.name.includes("Information Technology") ||
            unit.name.includes("Database") ||
            unit.name.includes("Software") ||
            unit.name.includes("Programming") ||
            unit.name.includes("Computer Science"),
        )

        if (fallbackUnits.length > 0) {
          console.log("Using fallback units in useEffect:", fallbackUnits)
          setFilteredUnits(fallbackUnits)
          setError("Using available units that may be relevant to this class.")
        }
      }
    }
  }, [fetchAttempted, isLoading, filteredUnits.length, currentEnrollment?.class_id, classes, units])

  return (
    <AuthenticatedLayout>
      <Head title="Enrollments" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Enrollments</h1>
        <div className="flex justify-between items-center mb-4">
          <button onClick={handleOpenModal} className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
            + Enroll Student
          </button>
        </div>
        <table className="min-w-full border-collapse border border-gray-200">
          <thead className="bg-gray-100">
            <tr>
              <th className="px-4 py-2 border">ID</th>
              <th className="px-4 py-2 border">Student</th>
              <th className="px-4 py-2 border">Group</th>
              <th className="px-4 py-2 border">Class</th>
              <th className="px-4 py-2 border">Unit</th>
              <th className="px-4 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody>
            {enrollments?.data?.length ? (
              enrollments.data.map((enrollment) => (
                <tr key={enrollment.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 border">{enrollment.id}</td>
                  <td className="px-4 py-2 border">
                    {enrollment.student
                      ? enrollment.student.name ||
                        `${enrollment.student.first_name || ""} ${enrollment.student.last_name || ""}` ||
                        enrollment.student.code
                      : enrollment.student_code || "N/A"}
                  </td>
                  <td className="px-4 py-2 border">
                    {enrollment.group ? enrollment.group.name : enrollment.group_id || "N/A"}
                  </td>
                  <td className="px-4 py-2 border">
                    {enrollment.group && enrollment.group.class ? enrollment.group.class.name : "N/A"}
                  </td>
                  <td className="px-4 py-2 border">{enrollment.unit ? enrollment.unit.name : "N/A"}</td>
                  <td className="px-4 py-2 border">
                    <button
                      onClick={() => router.delete(`/enrollments/${enrollment.id}`)}
                      className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                    >
                      Remove
                    </button>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={6} className="text-center py-4">
                  No enrollments found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">Enroll Student</h2>
            {error && <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{error}</div>}
            <form onSubmit={handleSubmit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Student Code</label>
                <input
                  type="text"
                  value={currentEnrollment?.code || ""}
                  onChange={(e) =>
                    setCurrentEnrollment((prev) => ({
                      ...prev!,
                      code: e.target.value,
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                />
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Semester</label>
                <select
                  value={currentEnrollment?.semester_id || ""}
                  onChange={(e) => handleSemesterChange(Number.parseInt(e.target.value, 10))}
                  className="w-full border rounded p-2"
                  required
                >
                  <option value="" disabled>
                    Select a semester
                  </option>
                  {semesters?.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Class</label>
                <select
                  value={currentEnrollment?.class_id || ""}
                  onChange={(e) => handleClassChange(Number.parseInt(e.target.value, 10))}
                  className="w-full border rounded p-2"
                  required
                  disabled={!currentEnrollment?.semester_id}
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
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Group</label>
                <select
                  value={currentEnrollment?.group_id || ""}
                  onChange={(e) =>
                    setCurrentEnrollment((prev) => ({
                      ...prev!,
                      group_id: e.target.value,
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                  disabled={!currentEnrollment?.class_id}
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
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Unit</label>
                {isLoading ? (
                  <div className="text-center py-2">Loading units...</div>
                ) : (
                  <select
                    value={currentEnrollment?.unit_id || ""}
                    onChange={(e) =>
                      setCurrentEnrollment((prev) => ({
                        ...prev!,
                        unit_id: Number.parseInt(e.target.value, 10),
                      }))
                    }
                    className="w-full border rounded p-2"
                    required
                    disabled={!currentEnrollment?.class_id || isLoading}
                  >
                    <option value="" disabled>
                      Select a unit
                    </option>
                    {filteredUnits.length > 0 ? (
                      filteredUnits.map((unit) => (
                        <option key={unit.id} value={unit.id}>
                          {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                        </option>
                      ))
                    ) : (
                      <option value="" disabled>
                        No units found for this class and semester.
                      </option>
                    )}
                  </select>
                )}
              </div>
              <div className="flex justify-end space-x-2">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                  disabled={isLoading}
                >
                  Enroll
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  )
}

export default Enrollments
