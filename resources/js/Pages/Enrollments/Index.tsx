"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"
import Pagination from "@/Components/Pagination" // Import the Pagination component

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
  school?: { id: number; name: string } // Replace faculty with school
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

interface LecturerAssignment {
  unit_id: number
  unit_name: string
  lecturer_code: string
  lecturer_name: string
}

const Enrollments = () => {
  const {
    enrollments,
    semesters = [],
    groups = [],
    classes = [],
    units = [],
    lecturerAssignments = { data: [], links: [] }, // Default structure for pagination
    errors: pageErrors,
  } = usePage().props as {
    enrollments: PaginatedEnrollments | null
    semesters: Semester[] | null
    groups: Group[] | null
    classes: Class[] | null
    units: Unit[] | null
    lecturerAssignments: {
      data: {
        unit_id: number
        unit_name: string
        lecturer_code: string
        lecturer_name: string
      }[]
      links: {
        url: string | null
        label: string
        active: boolean
      }[]
    }
    errors: Record<string, string>
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [currentEnrollment, setCurrentEnrollment] = useState<{
    code: string
    semester_id: number
    class_id: number
    group_id: string
    unit_ids: number[] // Change from single unit_id to an array of unit_ids
  } | null>(null)
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false)
  const [assignData, setAssignData] = useState<{ unit_id: number; lecturer_code: string } | null>(null)
  const [assignSemesterId, setAssignSemesterId] = useState<number | null>(null)
  const [assignClassId, setAssignClassId] = useState<number | null>(null)
  const [assignUnits, setAssignUnits] = useState<Unit[]>([]) // Units for the selected class

  const [enrollmentsPage, setEnrollmentsPage] = useState(1) // State for enrollments table pagination
  const [lecturerAssignmentsPage, setLecturerAssignmentsPage] = useState(1) // State for lecturer assignments table pagination

  const handleEnrollmentsPageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true, preserveScroll: true })
      setEnrollmentsPage(new URL(url).searchParams.get("page") || 1)
    }
  }

  const handleLecturerAssignmentsPageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true, preserveScroll: true })
      setLecturerAssignmentsPage(new URL(url).searchParams.get("page") || 1)
    }
  }

  // Display page errors if any
  useEffect(() => {
    if (pageErrors && Object.keys(pageErrors).length > 0) {
      const errorMessage = Object.values(pageErrors).join(", ")
      setError(errorMessage)
    }
  }, [pageErrors])

  const handleOpenModal = () => {
    setCurrentEnrollment({
      code: "",
      semester_id: 0,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    })
    setIsModalOpen(true)
    setError(null)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setError(null)
  }

  const handleOpenAssignModal = () => {
    setAssignData({ unit_id: 0, lecturer_code: "" })
    setIsAssignModalOpen(true)
    setError(null)
  }

  const handleCloseAssignModal = () => {
    setIsAssignModalOpen(false)
    setAssignData(null)
    setError(null)
  }

  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    }))
    setFilteredClasses((classes || []).filter((cls) => cls.semester_id === semesterId))
    setFilteredGroups([])
    setFilteredUnits([])
  }

  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      class_id: classId,
      group_id: "",
      unit_ids: [],
    }))
    setFilteredGroups((groups || []).filter((group) => group.class?.id === classId))
    setFilteredUnits([])
    setIsLoading(true)
    setError(null)
  
    if (currentEnrollment?.semester_id) {
      try {
        // Get CSRF token directly from meta tag
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
        console.log("Fetching units for semester_id:", currentEnrollment.semester_id, "and class_id:", classId)
  
        // Make the API call to get all units for this class and semester
        const response = await axios.post(
          "/api/units/by-class-and-semester",
          {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          {
            headers: {
              "X-CSRF-TOKEN": token,
              "Content-Type": "application/json",
              Accept: "application/json",
            },
          },
        )
  
        console.log("Units response:", response.data)
  
        // Check if the response contains a warning
        if (response.data.warning && response.data.units) {
          setFilteredUnits(response.data.units)
          setError(response.data.warning)
        } else if (Array.isArray(response.data) && response.data.length > 0) {
          setFilteredUnits(response.data)
        } else if (response.data.units && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units)
        } else {
          // If no units found from API, try to find units based on class name pattern
          const classNamePattern = (classes || []).find((c) => c.id === classId)?.name.toLowerCase() || ""
          const fallbackUnits = (units || []).filter((unit) => {
            // Try to match units by name or code patterns
            const unitName = (unit.name || "").toLowerCase()
            const unitCode = (unit.code || "").toLowerCase()
  
            // Extract class level if it's in format like "BBIT 1.1"
            const classMatch = classNamePattern.match(/(\w+)\s+(\d+\.\d+)/)
            if (classMatch) {
              const program = classMatch[1] // e.g., "bbit"
              const level = classMatch[2] // e.g., "1.1"
              const majorLevel = level.split(".")[0] // e.g., "1"
  
              return (
                unitCode.includes(program) ||
                unitCode.includes(majorLevel) ||
                unitName.includes(program) ||
                unitName.includes(`level ${majorLevel}`) ||
                unitName.includes(`year ${majorLevel}`)
              )
            }
  
            return false
          })
  
          if (fallbackUnits.length > 0) {
            console.log("Using fallback units based on name/code matching:", fallbackUnits)
            setFilteredUnits(fallbackUnits)
            setError("Using best-match units for this class. Please verify your selection.")
          } else {
            setFilteredUnits([])
            setError("No units found for this class and semester. Please contact the administrator.")
          }
        }
      } catch (error: any) {
        console.error("Error fetching units:", error.response?.data || error.message)
  
        // Extract and display a more user-friendly error message
        const errorMessage = "Failed to fetch units. Please try again or contact the administrator."
  
        if (error.response?.data?.error) {
          console.error("Detailed error:", error.response.data.error)
        }
  
        setError(errorMessage)
  
        // Try to use all available units as a last resort
        const allUnits = units || []
        if (allUnits.length > 0) {
          console.log("Using all available units as fallback")
          setFilteredUnits(allUnits)
          setError("Unable to fetch specific units. Showing all available units as a fallback.")
        }
      } finally {
        setIsLoading(false)
      }
    } else {
      setError("Please select a semester before selecting a class.")
      setIsLoading(false)
    }
  }

  const handleAssignSemesterChange = (semesterId: number) => {
    setAssignSemesterId(semesterId)
    setAssignClassId(null) // Reset class selection
    setAssignUnits([]) // Reset units
  }

  const handleAssignClassChange = async (classId: number) => {
    setAssignClassId(classId)
    setAssignUnits([]) // Reset units
    setError(null)

    if (assignSemesterId) {
      try {
        const response = await axios.post(
          "/api/units/by-class-and-semester",
          {
            semester_id: assignSemesterId,
            class_id: classId,
          },
          {
            headers: {
              "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
              "Content-Type": "application/json",
              Accept: "application/json",
            },
          },
        )

        if (response.data.units) {
          setAssignUnits(response.data.units)
        } else {
          setAssignUnits(response.data)
        }
      } catch (error: any) {
        console.error("Error fetching units for class:", error.response?.data || error.message)
        setError("Failed to fetch units for the selected class. Please try again.")
      }
    } else {
      setError("Please select a semester before selecting a class.")
    }
  }

  const handleAssignSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (assignData) {
      if (!assignData.unit_id) {
        setError("Please select a unit")
        return
      }

      if (!assignData.lecturer_code.trim()) {
        setError("Lecturer code is required")
        return
      }

      console.log("Assigning unit to lecturer:", assignData)

      router.post("/assign-unit", assignData, {
        onSuccess: () => {
          toast.success("Unit assigned to lecturer successfully!")
          handleCloseAssignModal()
        },
        onError: (errors) => {
          console.error("Error assigning unit:", errors)

          // Extract meaningful error message
          const errorMessage =
            errors?.response?.data?.message || // API error message
            errors?.message || // JavaScript error message
            "An error occurred during assignment. Please try again."

          setError(errorMessage)
          toast.error(errorMessage) // Display error as a toast notification
        },
      })
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (currentEnrollment) {
      // Validate form
      if (!currentEnrollment.code.trim()) {
        setError("Student code is required");
        return;
      }

      if (!currentEnrollment.semester_id) {
        setError("Please select a semester");
        return;
      }

      if (!currentEnrollment.class_id) {
        setError("Please select a class");
        return;
      }

      if (!currentEnrollment.group_id) {
        setError("Please select a group");
        return;
      }

      if (!currentEnrollment.unit_ids.length) {
        setError("Please select at least one unit");
        return;
      }

      // Ensure unit_ids is sent as an array
      const formattedEnrollment = {
        ...currentEnrollment,
        unit_ids: currentEnrollment.unit_ids.map((id) => Number(id)), // Ensure IDs are numbers
      };

      console.log("Submitting enrollment:", formattedEnrollment);

      router.post("/enrollments", formattedEnrollment, {
        onSuccess: () => {
          toast.success("Student enrolled successfully!");
          handleCloseModal();
        },
        onError: (errors) => {
          console.error("Enrollment errors:", errors);
          if (errors.group_id) {
            setError(errors.group_id);
          } else if (errors.code) {
            setError(errors.code);
          } else if (errors.error) {
            setError(errors.error);
          } else {
            setError("An error occurred during enrollment. Please try again.");
          }
        },
      });
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Enrollments" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Enrollments</h1>
        <div className="flex justify-between items-center mb-4">
          <button onClick={handleOpenModal} className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
            + Enroll Student
          </button>
          <button onClick={handleOpenAssignModal} className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            Assign Unit to Lecturer
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
                    {enrollment.student_code || "N/A"}
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
        <Pagination
          links={enrollments?.links}
          onPageChange={handleEnrollmentsPageChange}
          currentPage={enrollmentsPage}
        />
      </div>

      {/* Lecturer Assignments Section */}
      <div className="p-6 bg-white rounded-lg shadow-md mt-6">
        <h2 className="text-xl font-semibold mb-4">Lecturer Assignments</h2>
        <table className="min-w-full border-collapse border border-gray-200">
          <thead className="bg-gray-100">
            <tr>
              <th className="px-4 py-2 border">Unit</th>
              <th className="px-4 py-2 border">Lecturer Code</th>
              <th className="px-4 py-2 border">Lecturer Name</th>
            </tr>
          </thead>
          <tbody>
            {lecturerAssignments.data.length ? (
              lecturerAssignments.data.map((assignment) => (
                <tr key={assignment.unit_id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 border">{assignment.unit_name}</td>
                  <td className="px-4 py-2 border">{assignment.lecturer_code}</td>
                  <td className="px-4 py-2 border">{assignment.lecturer_name}</td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={3} className="text-center py-4">
                  No lecturer assignments found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
        <Pagination
          links={lecturerAssignments.links}
          onPageChange={handleLecturerAssignmentsPageChange}
          currentPage={lecturerAssignmentsPage}
        />
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
                <label className="block text-sm font-medium text-gray-700">Units</label>
                {isLoading ? (
                  <div className="text-center py-2">Loading units...</div>
                ) : (
                  <select
                    multiple // Allow multiple selection
                    value={currentEnrollment?.unit_ids || []}
                    onChange={(e) =>
                      setCurrentEnrollment((prev) => ({
                        ...prev!,
                        unit_ids: Array.from(e.target.selectedOptions, (option) =>
                          Number.parseInt(option.value, 10),
                        ),
                      }))
                    }
                    className="w-full border rounded p-2"
                    required
                    disabled={!currentEnrollment?.class_id || isLoading}
                  >
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

      {isAssignModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">Assign Unit to Lecturer</h2>
            {error && <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{error}</div>}
            <form onSubmit={handleAssignSubmit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Semester</label>
                <select
                  value={assignSemesterId || ""}
                  onChange={(e) => handleAssignSemesterChange(Number.parseInt(e.target.value, 10))}
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
                  value={assignClassId || ""}
                  onChange={(e) => handleAssignClassChange(Number.parseInt(e.target.value, 10))}
                  className="w-full border rounded p-2"
                  required
                  disabled={!assignSemesterId}
                >
                  <option value="" disabled>
                    Select a class
                  </option>
                  {classes
                    ?.filter((cls) => cls.semester_id === assignSemesterId)
                    .map((classItem) => (
                      <option key={classItem.id} value={classItem.id}>
                        {classItem.name}
                      </option>
                    ))}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Unit</label>
                <select
                  value={assignData?.unit_id || ""}
                  onChange={(e) =>
                    setAssignData((prev) => ({
                      ...prev!,
                      unit_id: Number.parseInt(e.target.value, 10),
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                  disabled={!assignClassId || assignUnits.length === 0}
                >
                  <option value="" disabled>
                    Select a unit
                  </option>
                  {assignUnits.map((unit) => (
                    <option key={unit.id} value={unit.id}>
                      {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Lecturer Code</label>
                <input
                  type="text"
                  value={assignData?.lecturer_code || ""}
                  onChange={(e) =>
                    setAssignData((prev) => ({
                      ...prev!,
                      lecturer_code: e.target.value,
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                />
              </div>
              <div className="flex justify-end space-x-2">
                <button
                  type="button"
                  onClick={handleCloseAssignModal}
                  className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                >
                  Assign
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
