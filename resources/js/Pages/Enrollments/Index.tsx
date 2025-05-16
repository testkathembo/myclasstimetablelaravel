"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import SemesterUnits from "@/Pages/SemesterUnits/Index"
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
  school_id?: number
}

interface Program {
  id: number
  name: string
}

interface School {
  id: number
  name: string
}

interface Unit {
  id: number
  name: string
  code?: string
  program_id?: number
  school_id?: number
  program?: Program
  school?: School
}

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  name: string
}

interface Enrollment {
  id: number
  student_code: string | null
  lecturer_code: string | null
  group_id: string | null
  unit_id: number
  semester_id: number
  program_id: number | null
  created_at: string | null
  updated_at: string | null
  student: Student | null
  unit: Unit | null
  group: Group | null
  program: Program | null
  school: School | null
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
    units: allUnits = [],
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
    program_id: number | null
  } | null>(null)
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [apiAttempted, setApiAttempted] = useState(false)

  const handleOpenModal = () => {
    setCurrentEnrollment({
      code: "",
      semester_id: 0,
      class_id: 0,
      group_id: "",
      unit_id: 0,
      program_id: null,
    })
    setIsModalOpen(true)
    setApiAttempted(false)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setApiAttempted(false)
  }

  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_id: 0,
      program_id: null,
    }))
    setFilteredClasses(classes.filter((cls) => cls.semester_id === semesterId))
    setFilteredGroups([])
    setFilteredUnits([])
    setApiAttempted(false)
  }

  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      class_id: classId,
      group_id: "",
      unit_id: 0,
      program_id: null,
    }))
    setFilteredGroups(groups.filter((group) => group.class.id === classId))
    setFilteredUnits([])
    setIsLoading(true)
    setError(null)
    setApiAttempted(true)

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
        setFilteredUnits(response.data || [])
      } catch (error: any) {
        console.error("Error fetching units:", error.response?.data || error.message)
        setError("An error occurred while fetching units. Please try again.")
      } finally {
        setIsLoading(false)
      }
    } else {
      console.error("Semester ID is missing in currentEnrollment.")
      setError("Please select a semester before selecting a class.")
      setIsLoading(false)
    }
  }

  const handleUnitChange = async (unitId: number) => {
    const selectedUnit = filteredUnits.find((unit) => unit.id === unitId)

    if (selectedUnit) {
      if (selectedUnit.program_id !== undefined) {
        setCurrentEnrollment((prev) => ({
          ...prev!,
          unit_id: unitId,
          program_id: selectedUnit.program_id || null,
        }))
        console.log("Using unit data from filtered units:", {
          unit_id: unitId,
          program_id: selectedUnit.program_id,
        })
      } else {
        try {
          setIsLoading(true)
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""

          const response = await axios.get(`/api/units/${unitId}/details`, {
            headers: {
              "X-CSRF-TOKEN": csrfToken,
              "Content-Type": "application/json",
              Accept: "application/json",
            },
          })

          console.log("Unit details response:", response.data)

          setCurrentEnrollment((prev) => ({
            ...prev!,
            unit_id: unitId,
            program_id: response.data.program_id,
          }))
        } catch (error: any) {
          console.error("Error fetching unit details:", error.response?.data || error.message)
          setCurrentEnrollment((prev) => ({
            ...prev!,
            unit_id: unitId,
          }))
        } finally {
          setIsLoading(false)
        }
      }
    } else {
      setCurrentEnrollment((prev) => ({
        ...prev!,
        unit_id: unitId,
      }))
    }
  }

  useEffect(() => {
    if (apiAttempted && !isLoading && filteredUnits.length === 0 && currentEnrollment?.class_id) {
      const selectedClass = classes?.find((c) => c.id === currentEnrollment.class_id)

      if (selectedClass && selectedClass.name.includes("BBIT 1.1")) {
        const relevantUnits =
          allUnits?.filter(
            (unit) =>
              (unit.code?.startsWith("BIT") &&
                ["101", "201", "301", "303", "401", "403", "405"].some((code) => unit.code?.includes(code))) ||
              unit.name.includes("Information Technology") ||
              unit.name.includes("Database Systems") ||
              unit.name.includes("Software Engineering") ||
              unit.name.includes("Cybersecurity") ||
              unit.name.includes("Artificial Intelligence") ||
              unit.name.includes("Data Science") ||
              unit.name.includes("Internet of Things"),
          ) || []

        console.log("Using fallback units for BBIT 1.1 in useEffect:", relevantUnits)
        setFilteredUnits(relevantUnits)
      }
    }
  }, [apiAttempted, isLoading, filteredUnits.length, currentEnrollment?.class_id, classes, allUnits])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (currentEnrollment) {
        router.post("/enrollments", currentEnrollment, {
            onSuccess: () => {
                alert("Student enrolled successfully!");
                handleCloseModal();
            },
            onError: (errors) => {
                if (errors.group_id) {
                    alert(errors.group_id); // Display the error message for group capacity
                } else {
                    console.error("Error enrolling student:", errors);
                }
            },
        });
    }
};

  return (
    <>
      <Head title="Enrollments" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Enrollments</h1>
        <SemesterUnits units={allUnits || []} />
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
              <th className="px-4 py-2 border">Program</th>
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
                  <td className="px-4 py-2 border">{enrollment.program ? enrollment.program.name : "N/A"}</td>
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
                <td colSpan={7} className="text-center py-4">
                  No enrollments found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">Enroll Student</h2>
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
                      {group.name}
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
                    onChange={(e) => handleUnitChange(Number.parseInt(e.target.value, 10))}
                    className="w-full border rounded p-2"
                    required
                    disabled={!currentEnrollment?.class_id || isLoading}
                  >
                    <option value="" disabled>
                      Select a unit
                    </option>
                    {filteredUnits.map((unit) => (
                      <option key={unit.id} value={unit.id}>
                        {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                      </option>
                    ))}
                  </select>
                )}
                {error && <p className="text-red-500 text-sm mt-1">{error}</p>}
                {!isLoading && filteredUnits.length === 0 && currentEnrollment?.class_id && (
                  <p className="text-amber-500 text-sm mt-1">No units found for this class and semester.</p>
                )}
              </div>

              {/* Display the selected program (read-only) */}
              {currentEnrollment?.unit_id > 0 && (
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Program</label>
                  <div className="w-full border rounded p-2 bg-gray-100">
                    {(() => {
                      const selectedUnit = filteredUnits.find((u) => u.id === currentEnrollment.unit_id)
                      const programName =
                        selectedUnit?.program?.name ||
                        (selectedUnit?.program_id
                          ? `Program ID: ${selectedUnit.program_id}`
                          : "Will be auto-assigned")
                      return programName
                    })()}
                  </div>
                </div>
              )}

              <button
                type="submit"
                className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                disabled={isLoading}
              >
                Enroll
              </button>
              <button
                type="button"
                onClick={handleCloseModal}
                className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
              >
                Cancel
              </button>
            </form>
          </div>
        </div>
      )}
    </>
  )
}

export default Enrollments
