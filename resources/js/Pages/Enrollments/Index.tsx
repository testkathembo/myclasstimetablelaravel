import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"
import Pagination from "@/Components/Pagination"

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

interface LecturerAssignment {
  unit_id: number
  unit_name: string
  lecturer_code: string
  lecturer_name: string
}

const Enrollments = () => {
  // Debug the props received from the server
  const pageProps = usePage().props
  console.log("Page props:", pageProps)

  const {
    enrollments,
    semesters = [],
    groups = [],
    classes = [],
    units = [],
    lecturerAssignments = { data: [], links: [] },
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

  // Debug the extracted data
  console.log("Extracted data:", {
    semestersCount: semesters?.length || 0,
    groupsCount: groups?.length || 0,
    classesCount: classes?.length || 0,
    unitsCount: units?.length || 0,
  })

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [currentEnrollment, setCurrentEnrollment] = useState<{
    code: string
    semester_id: number
    class_id: number
    group_id: string
    unit_ids: number[]
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
  const [assignUnits, setAssignUnits] = useState<Unit[]>([])

  const [enrollmentsPage, setEnrollmentsPage] = useState(1)
  const [lecturerAssignmentsPage, setLecturerAssignmentsPage] = useState(1)
  const [selectedEnrollments, setSelectedEnrollments] = useState<number[]>([])
  const [isSelectAllChecked, setIsSelectAllChecked] = useState(false)

  // Check if semesters are available
  useEffect(() => {
    if (!semesters || semesters.length === 0) {
      console.warn("No semesters available in props")
    } else {
      console.log("Semesters available:", semesters)
    }
  }, [semesters])

  const handleEnrollmentsPageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true, preserveScroll: true })
      setEnrollmentsPage(Number(new URL(url).searchParams.get("page")) || 1)
    }
  }

  const handleLecturerAssignmentsPageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true, preserveScroll: true })
      setLecturerAssignmentsPage(Number(new URL(url).searchParams.get("page")) || 1)
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
    setFilteredClasses([])
    setFilteredGroups([])
    setFilteredUnits([])
    setIsModalOpen(true)
    setError(null)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setError(null)
  }

  const handleSelectEnrollment = (enrollmentId: number) => {
    setSelectedEnrollments(prev => {
      if (prev.includes(enrollmentId)) {
        const newSelection = prev.filter(id => id !== enrollmentId)
        setIsSelectAllChecked(false)
        return newSelection
      } else {
        const newSelection = [...prev, enrollmentId]
        const allEnrollmentIds = enrollments?.data?.map(e => e.id) || []
        setIsSelectAllChecked(newSelection.length === allEnrollmentIds.length)
        return newSelection
      }
    })
  }

  const handleSelectAll = () => {
    if (isSelectAllChecked) {
      setSelectedEnrollments([])
      setIsSelectAllChecked(false)
    } else {
      const allEnrollmentIds = enrollments?.data?.map(e => e.id) || []
      setSelectedEnrollments(allEnrollmentIds)
      setIsSelectAllChecked(true)
    }
  }

  const handleBulkDelete = () => {
    if (selectedEnrollments.length === 0) {
      toast.error("Please select enrollments to delete")
      return
    }

    if (confirm(`Are you sure you want to delete ${selectedEnrollments.length} enrollment(s)? This action cannot be undone.`)) {
      router.post("/enrollments/bulk-delete", { enrollment_ids: selectedEnrollments }, {
        onSuccess: () => {
          toast.success(`${selectedEnrollments.length} enrollment(s) deleted successfully!`)
          setSelectedEnrollments([])
          setIsSelectAllChecked(false)
        },
        onError: (errors) => {
          console.error("Bulk delete errors:", errors)
          toast.error("Failed to delete some enrollments. Please try again.")
        },
      })
    }
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
    console.log("Semester changed to:", semesterId)

    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    }))

    const filtered = (classes || []).filter((cls) => cls.semester_id === semesterId)
    console.log(`Found ${filtered.length} classes for semester ${semesterId}`)
    setFilteredClasses(filtered)

    setFilteredGroups([])
    setFilteredUnits([])
  }

  const handleClassChange = async (classId: number) => {
    console.log("Class changed to:", classId)

    setCurrentEnrollment((prev) => ({
      ...prev!,
      class_id: classId,
      group_id: "",
      unit_ids: [],
    }))

    const filtered = (groups || []).filter((group) => group.class?.id === classId)
    console.log(`Found ${filtered.length} groups for class ${classId}`)
    setFilteredGroups(filtered)

    setFilteredUnits([])
    setIsLoading(true)
    setError(null)

    if (currentEnrollment?.semester_id) {
      try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
        console.log("Fetching units for semester_id:", currentEnrollment.semester_id, "and class_id:", classId)

        const response = await axios.get("/units/by-class-and-semester", {
          params: {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          headers: {
            "X-CSRF-TOKEN": token,
            "Content-Type": "application/json",
            Accept: "application/json",
          },
        })

        console.log("Units response:", response.data)

        if (response.data.warning && response.data.units) {
          setFilteredUnits(response.data.units)
          setError(response.data.warning)
        } else if (Array.isArray(response.data) && response.data.length > 0) {
          setFilteredUnits(response.data)
        } else if (response.data.units && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units)
        } else {
          const classNamePattern = (classes || []).find((c) => c.id === classId)?.name.toLowerCase() || ""
          const fallbackUnits = (units || []).filter((unit) => {
            const unitName = (unit.name || "").toLowerCase()
            const unitCode = (unit.code || "").toLowerCase()

            const classMatch = classNamePattern.match(/(\w+)\s+(\d+\.\d+)/)
            if (classMatch) {
              const program = classMatch[1]
              const level = classMatch[2]
              const majorLevel = level.split(".")[0]

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

        const errorMessage = "Failed to fetch units. Please try again or contact the administrator."

        if (error.response?.data?.error) {
          console.error("Detailed error:", error.response.data.error)
        }

        setError(errorMessage)

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
    setAssignClassId(null)
    setAssignUnits([])
  }

  const handleAssignClassChange = async (classId: number) => {
    setAssignClassId(classId)
    setAssignUnits([])
    setError(null)

    if (assignSemesterId) {
      try {
        const response = await axios.get("/units/by-class-and-semester", {
          params: {
            semester_id: assignSemesterId,
            class_id: classId,
          },
          headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
            "Content-Type": "application/json",
            Accept: "application/json",
          },
        })

        if (response.data.units) {
          setAssignUnits(response.data.units)
        } else if (response.data.success && response.data.units) {
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

          const errorMessage =
            errors?.response?.data?.message ||
            errors?.message ||
            "An error occurred during assignment. Please try again."

          setError(errorMessage)
          toast.error(errorMessage)
        },
      })
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (currentEnrollment) {
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

      if (!currentEnrollment.unit_ids.length) {
        setError("Please select at least one unit")
        return
      }

      const formattedEnrollment = {
        ...currentEnrollment,
        unit_ids: currentEnrollment.unit_ids.map((id) => Number(id)),
      }

      console.log("Submitting enrollment:", formattedEnrollment)

      router.post("/enrollments", formattedEnrollment, {
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

  return (
    <AuthenticatedLayout>
      <Head title="Enrollments" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold text-slate-800 mb-2">Student Enrollments</h1>
                  <p className="text-slate-600 text-lg">Manage student course registrations and lecturer assignments</p>
                </div>
                <div className="flex flex-col sm:flex-row gap-3 mt-6 sm:mt-0">
                  <button
                    onClick={handleOpenModal}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Enroll Student
                  </button>
                  <button
                    onClick={handleOpenAssignModal}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl shadow-lg hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                    </svg>
                    Assign Lecturer
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Warning Section */}
          {(!semesters || semesters.length === 0) && (
            <div className="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 p-6 rounded-xl shadow-md">
              <div className="flex items-center">
                <svg className="w-6 h-6 text-amber-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div>
                  <h3 className="text-amber-800 font-semibold">No Semesters Available</h3>
                  <p className="text-amber-700 mt-1">Please check your database or contact the administrator.</p>
                </div>
              </div>
            </div>
          )}

          {/* Enrollments Table */}
          <div className="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden mb-8">
            <div className="px-8 py-6 bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-2xl font-bold text-slate-800">Current Enrollments</h2>
                  <p className="text-slate-600 mt-1">View and manage all student course enrollments</p>
                </div>
                {selectedEnrollments.length > 0 && (
                  <div className="flex items-center gap-3 mt-4 sm:mt-0">
                    <span className="text-sm font-medium text-slate-600">
                      {selectedEnrollments.length} selected
                    </span>
                    <button
                      onClick={handleBulkDelete}
                      className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 shadow-md"
                    >
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Delete Selected
                    </button>
                  </div>
                )}
              </div>
            </div>
            
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left">
                      <div className="flex items-center">
                        <input
                          type="checkbox"
                          checked={isSelectAllChecked}
                          onChange={handleSelectAll}
                          className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                        />
                        <label className="ml-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                          Select All
                        </label>
                      </div>
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Student</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Group</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Class</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Unit</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {enrollments?.data?.length ? (
                    enrollments.data.map((enrollment, index) => (
                      <tr key={enrollment.id} className={`hover:bg-slate-50 transition-colors duration-150 ${index % 2 === 0 ? 'bg-white' : 'bg-slate-25'} ${selectedEnrollments.includes(enrollment.id) ? 'bg-blue-50 border-l-4 border-blue-500' : ''}`}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="checkbox"
                            checked={selectedEnrollments.includes(enrollment.id)}
                            onChange={() => handleSelectEnrollment(enrollment.id)}
                            className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                              <span className="text-blue-600 font-semibold text-sm">{enrollment.id}</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-slate-900">{enrollment.student_code || "N/A"}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                            {enrollment.group ? enrollment.group.name : enrollment.group_id || "N/A"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                            {enrollment.group && enrollment.group.class ? enrollment.group.class.name : "N/A"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-slate-900">{enrollment.unit ? enrollment.unit.name : "N/A"}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <button
                            onClick={() => router.delete(`/enrollments/${enrollment.id}`)}
                            className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 shadow-md"
                          >
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Remove
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                          </svg>
                          <h3 className="text-lg font-medium text-slate-900 mb-1">No enrollments found</h3>
                          <p className="text-slate-500">Start by enrolling your first student</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            
            {enrollments?.links && (
              <div className="px-6 py-4 bg-slate-50 border-t border-slate-200">
                <Pagination
                  links={enrollments.links}
                  onPageChange={handleEnrollmentsPageChange}
                  currentPage={enrollmentsPage}
                />
              </div>
            )}
          </div>

          {/* Lecturer Assignments Table */}
          <div className="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div className="px-8 py-6 bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
              <h2 className="text-2xl font-bold text-slate-800">Lecturer Assignments</h2>
              <p className="text-slate-600 mt-1">View current unit-lecturer assignments</p>
            </div>
            
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Unit</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Lecturer Code</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Lecturer Name</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {lecturerAssignments.data.length ? (
                    lecturerAssignments.data.map((assignment, index) => (
                      <tr key={assignment.unit_id} className={`hover:bg-slate-50 transition-colors duration-150 ${index % 2 === 0 ? 'bg-white' : 'bg-slate-25'}`}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-slate-900">{assignment.unit_name}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                            {assignment.lecturer_code}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-slate-900">{assignment.lecturer_name}</div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={3} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                          </svg>
                          <h3 className="text-lg font-medium text-slate-900 mb-1">No lecturer assignments found</h3>
                          <p className="text-slate-500">Start by assigning units to lecturers</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            
            {lecturerAssignments.links && (
              <div className="px-6 py-4 bg-slate-50 border-t border-slate-200">
                <Pagination
                  links={lecturerAssignments.links}
                  onPageChange={handleLecturerAssignmentsPageChange}
                  currentPage={lecturerAssignmentsPage}
                />
              </div>
            )}
          </div>
        </div>
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
                  onChange={(e) => handleSemesterChange(Number(e.target.value))}
                  className="w-full border rounded p-2"
                  required
                >
                  <option value="">Select a semester</option>
                  {semesters && semesters.length > 0 ? (
                    semesters.map((semester) => (
                      <option key={semester.id} value={semester.id}>
                        {semester.name}
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      No semesters available
                    </option>
                  )}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Class</label>
                <select
                  value={currentEnrollment?.class_id || ""}
                  onChange={(e) => handleClassChange(Number(e.target.value))}
                  className="w-full border rounded p-2"
                  required
                  disabled={!currentEnrollment?.semester_id}
                >
                  <option value="">Select a class</option>
                  {filteredClasses.length > 0 ? (
                    filteredClasses.map((classItem) => (
                      <option key={classItem.id} value={classItem.id}>
                        {classItem.name}
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      {currentEnrollment?.semester_id ? "No classes for this semester" : "Select a semester first"}
                    </option>
                  )}
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
                  <option value="">Select a group</option>
                  {filteredGroups.length > 0 ? (
                    filteredGroups.map((group) => (
                      <option key={group.id} value={group.id.toString()}>
                        {group.name} (Capacity: {group.capacity})
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      {currentEnrollment?.class_id ? "No groups for this class" : "Select a class first"}
                    </option>
                  )}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Units</label>
                {isLoading ? (
                  <div className="text-center py-2">Loading units...</div>
                ) : (
                  <div>
                    <div className="flex items-center mb-2">
                      <input
                        type="checkbox"
                        id="selectAllUnits"
                        onChange={(e) => {
                          if (e.target.checked) {
                            setCurrentEnrollment((prev) => ({
                              ...prev!,
                              unit_ids: filteredUnits.map((unit) => unit.id),
                            }))
                          } else {
                            setCurrentEnrollment((prev) => ({
                              ...prev!,
                              unit_ids: [],
                            }))
                          }
                        }}
                        checked={
                          filteredUnits.length > 0 && currentEnrollment?.unit_ids.length === filteredUnits.length
                        }
                        disabled={!filteredUnits.length || isLoading}
                      />
                      <label htmlFor="selectAllUnits" className="ml-2 text-sm">
                        Select All Units
                      </label>
                    </div>
                    <div className="border rounded p-2 max-h-40 overflow-y-auto">
                      {filteredUnits.length > 0 ? (
                        filteredUnits.map((unit) => (
                          <div key={unit.id} className="flex items-center mb-1">
                            <input
                              type="checkbox"
                              id={`unit-${unit.id}`}
                              value={unit.id}
                              onChange={(e) => {
                                const unitId = Number(e.target.value)
                                if (e.target.checked) {
                                  setCurrentEnrollment((prev) => ({
                                    ...prev!,
                                    unit_ids: [...(prev?.unit_ids || []), unitId],
                                  }))
                                } else {
                                  setCurrentEnrollment((prev) => ({
                                    ...prev!,
                                    unit_ids: (prev?.unit_ids || []).filter((id) => id !== unitId),
                                  }))
                                }
                              }}
                              checked={currentEnrollment?.unit_ids.includes(unit.id)}
                              disabled={isLoading}
                            />
                            <label htmlFor={`unit-${unit.id}`} className="ml-2 text-sm">
                              {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                            </label>
                          </div>
                        ))
                      ) : (
                        <div className="text-sm text-gray-500 text-center py-2">
                          {currentEnrollment?.class_id
                            ? "No units found for this class and semester."
                            : "Please select a class to see available units."}
                        </div>
                      )}
                    </div>
                  </div>
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
                  onChange={(e) => handleAssignSemesterChange(Number(e.target.value))}
                  className="w-full border rounded p-2"
                  required
                >
                  <option value="">Select a semester</option>
                  {semesters && semesters.length > 0 ? (
                    semesters.map((semester) => (
                      <option key={semester.id} value={semester.id}>
                        {semester.name}
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      No semesters available
                    </option>
                  )}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Class</label>
                <select
                  value={assignClassId || ""}
                  onChange={(e) => handleAssignClassChange(Number(e.target.value))}
                  className="w-full border rounded p-2"
                  required
                  disabled={!assignSemesterId}
                >
                  <option value="">Select a class</option>
                  {classes && classes.length > 0 ? (
                    classes
                      .filter((cls) => cls.semester_id === assignSemesterId)
                      .map((classItem) => (
                        <option key={classItem.id} value={classItem.id}>
                          {classItem.name}
                        </option>
                      ))
                  ) : (
                    <option value="" disabled>
                      {assignSemesterId ? "No classes for this semester" : "Select a semester first"}
                    </option>
                  )}
                </select>
              </div>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Unit</label>
                <select
                  value={assignData?.unit_id || ""}
                  onChange={(e) =>
                    setAssignData((prev) => ({
                      ...prev!,
                      unit_id: Number(e.target.value),
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                  disabled={!assignClassId || assignUnits.length === 0}
                >
                  <option value="">Select a unit</option>
                  {assignUnits.length > 0 ? (
                    assignUnits.map((unit) => (
                      <option key={unit.id} value={unit.id}>
                        {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      {assignClassId ? "No units for this class" : "Select a class first"}
                    </option>
                  )}
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
                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
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
