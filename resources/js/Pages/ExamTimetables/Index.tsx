"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { AlertCircle } from "lucide-react"

interface ExamTimetable {
  id: number
  day: string
  date: string
  unit_code: string
  unit_name: string
  class_code: string
  class_name: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  semester_name: string
  class_id: number
}

interface Class {
  id: number
  code: string
  name: string
  semester_id: number
  units?: Unit[]
}

interface Unit {
  id: number
  code: string
  name: string
  class_id: number
  semester_id: number
  student_count?: number
  lecturer_code?: string
  lecturer_name?: string
}

interface HierarchicalSemester {
  id: number
  name: string
  classes: Class[]
}

interface Semester {
  id: number
  name: string
}

interface TimeSlot {
  id: number
  day: string
  date: string
  start_time: string
  end_time: string
}

interface Examroom {
  id: number
  name: string
  capacity: number
  location: string
}

interface Lecturer {
  id: number
  name: string
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedExamTimetables {
  data: ExamTimetable[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

interface FormState {
  id: number
  day: string
  date: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  class_id: number
  unit_id: number
  unit_code?: string
  unit_name?: string
  class_code?: string
  class_name?: string
  timeslot_id?: number
  lecturer_id?: number | null
  lecturer_name?: string | null
}

// Helper function to get CSRF token
const getCSRFToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  if (!token) {
    console.error('CSRF token not found');
    return "";
  }
  return token;
}

// Helper function to ensure time is in H:i format
const formatTimeToHi = (timeStr: string) => {
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeStr)) {
    return timeStr
  }

  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(timeStr)) {
    return timeStr.substring(0, 5)
  }

  if (timeStr.includes("AM") || timeStr.includes("PM")) {
    const [time, modifier] = timeStr.split(" ")
    let [hours, minutes] = time.split(":").map(Number)

    if (modifier === "PM" && hours < 12) hours += 12
    if (modifier === "AM" && hours === 12) hours = 0

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`
  }

  return timeStr
}

// Helper function to check for time overlap
const checkTimeOverlap = (examtimetable: ExamTimetable, date: string, startTime: string, endTime: string) => {
  if (examtimetable.date !== date) return false

  return (
    (examtimetable.start_time <= startTime && examtimetable.end_time > startTime) ||
    (examtimetable.start_time < endTime && examtimetable.end_time >= endTime) ||
    (examtimetable.start_time >= startTime && examtimetable.end_time <= endTime)
  )
}

const ExamTimetable = () => {
  const {
    examTimetables = { data: [] },
    perPage = 10,
    search = "",
    semesters = [],
    hierarchicalData = [],
    classesBySemester = {},
    unitsByClass = {},
    can = { create: false, edit: false, delete: false, process: false, solve_conflicts: false, download: false },
    examrooms = [],
    timeSlots = [],
    lecturers = [],
  } = usePage().props as unknown as {
    examTimetables: PaginatedExamTimetables
    perPage: number
    search: string
    semesters: Semester[]
    hierarchicalData: HierarchicalSemester[]
    classesBySemester: Record<number, Class[]>
    unitsByClass: Record<number, Unit[]>
    examrooms: Examroom[]
    timeSlots: TimeSlot[]
    lecturers: Lecturer[]
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      process: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "">("")
  const [selectedTimetable, setSelectedTimetable] = useState<ExamTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)

  // Three-level cascading state
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])

  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [availableTimeSlots, setAvailableTimeSlots] = useState<TimeSlot[]>([])
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<Lecturer[]>([])

  // Initialize available timeslots
  useEffect(() => {
    if (timeSlots && timeSlots.length > 0) {
      setAvailableTimeSlots(timeSlots)
    }
  }, [timeSlots])

  // Debug hierarchical data
  useEffect(() => {
    console.log("Hierarchical data:", hierarchicalData)
    console.log("Classes by semester:", classesBySemester)
    console.log("Units by class:", unitsByClass)
  }, [hierarchicalData, classesBySemester, unitsByClass])

  const handleOpenModal = (type: "view" | "edit" | "delete" | "create", examtimetable: ExamTimetable | null) => {
    setModalType(type)
    setSelectedTimetable(examtimetable)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])

    if (type === "create") {
      setFormState({
        id: 0,
        day: "",
        date: "",
        venue: "",
        location: "",
        no: 0,
        chief_invigilator: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
        class_id: 0,
        unit_id: 0,
        unit_code: "",
        unit_name: "",
        class_code: "",
        class_name: "",
        timeslot_id: 0,
        lecturer_id: null,
        lecturer_name: "",
      })
      // Reset filtered data
      setFilteredClasses([])
      setFilteredUnits([])
    } else if (examtimetable) {
      // Find matching timeslot
      const timeSlot = timeSlots.find(
        (ts) =>
          ts.day === examtimetable.day &&
          ts.date === examtimetable.date &&
          ts.start_time === examtimetable.start_time &&
          ts.end_time === examtimetable.end_time,
      )

      setFormState({
        ...examtimetable,
        timeslot_id: timeSlot?.id || 0,
        lecturer_id: null,
        lecturer_name: "",
      })

      // Set filtered classes and units for the selected semester and class
      if (examtimetable.semester_id) {
        const semesterClasses = classesBySemester[examtimetable.semester_id] || []
        setFilteredClasses(semesterClasses)

        if (examtimetable.class_id) {
          const classUnits = unitsByClass[examtimetable.class_id] || []
          setFilteredUnits(classUnits)
        }
      }
    }

    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedTimetable(null)
    setFormState(null)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
    setFilteredClasses([])
    setFilteredUnits([])
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this exam timetable?")) {
      try {
        await router.delete(`/examtimetable/${id}`, {
          onSuccess: () => alert("Exam timetable deleted successfully."),
          onError: (errors) => {
            console.error("Failed to delete exam timetable:", errors)
            alert("An error occurred while deleting the exam timetable.")
          },
        })
      } catch (error) {
        console.error("Unexpected error:", error)
        alert("An unexpected error occurred.")
      }
    }
  }

  const checkForConflicts = (
    date: string,
    startTime: string,
    endTime: string,
    unitId: number | undefined,
    venueId: string,
  ) => {
    if (!date || !startTime || !endTime || !unitId || !venueId) {
      setConflictWarning(null)
      return false
    }

    const conflicts = examTimetables.data.filter((examtimetable) => {
      if (selectedTimetable && examtimetable.id === selectedTimetable.id) return false

      const hasTimeOverlap = examtimetable.date === date && checkTimeOverlap(examtimetable, date, startTime, endTime)
      const isSameUnit = examtimetable.unit_code === formState?.unit_code
      const isSameVenue = examtimetable.venue === venueId

      return hasTimeOverlap && (isSameUnit || isSameVenue)
    })

    if (conflicts.length > 0) {
      const unitConflicts = conflicts.filter((examtimetable) => examtimetable.unit_code === formState?.unit_code)
      const venueConflicts = conflicts.filter((examtimetable) => examtimetable.venue === venueId)

      let warningMsg = "Scheduling conflicts detected: "

      if (unitConflicts.length > 0) {
        warningMsg += `This unit already has an exam scheduled at this time. `
      }

      if (venueConflicts.length > 0) {
        warningMsg += `This venue is already booked at this time.`
      }

      setConflictWarning(warningMsg)
      return true
    }

    setConflictWarning(null)
    return false
  }

  const handleTimeSlotChange = (timeSlotId: number) => {
    if (!formState) return

    const selectedTimeSlot = timeSlots.find((ts) => ts.id === Number(timeSlotId))
    if (selectedTimeSlot) {
      setFormState((prev) => ({
        ...prev!,
        timeslot_id: Number(timeSlotId),
        day: selectedTimeSlot.day,
        date: selectedTimeSlot.date,
        start_time: selectedTimeSlot.start_time,
        end_time: selectedTimeSlot.end_time,
      }))

      if (formState.unit_id && formState.venue) {
        checkForConflicts(
          selectedTimeSlot.date,
          selectedTimeSlot.start_time,
          selectedTimeSlot.end_time,
          formState.unit_id,
          formState.venue,
        )
      }
    }
  }

  // LEVEL 1: Semester Selection (FIXED API CALL)
  const handleSemesterChange = async (semesterId: number | string) => {
    if (!formState) return

    setIsLoading(true)
    setErrorMessage(null)
    setUnitLecturers([])

    const numericSemesterId = Number(semesterId)

    if (isNaN(numericSemesterId)) {
      console.error("Invalid semester ID:", semesterId)
      setErrorMessage("Invalid semester ID")
      setIsLoading(false)
      return
    }

    // CASCADING LOGIC: Reset class and unit when semester changes
    setFormState((prev) => ({
      ...prev!,
      semester_id: numericSemesterId,
      class_id: 0, // Reset class selection
      class_code: "",
      class_name: "",
      unit_id: 0, // Reset unit selection
      unit_code: "",
      unit_name: "",
      no: 0, // Reset student count
      lecturer_id: null,
      lecturer_name: "",
      chief_invigilator: "", // Reset chief invigilator
    }))

    try {
      // FIXED: Use the correct API endpoint
      const response = await fetch(`/api/semester/${numericSemesterId}/classes`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        console.log(`Found ${data.classes.length} classes for semester ID: ${numericSemesterId}`, data.classes)

        if (data.classes.length === 0) {
          setErrorMessage(
            `No classes found for semester ${semesters.find((s) => s.id === numericSemesterId)?.name || numericSemesterId}. Please check if classes are assigned to this semester.`,
          )
        } else {
          setErrorMessage(null)
        }

        setFilteredClasses(data.classes)
      } else {
        setErrorMessage(data.message || "Failed to load classes")
        setFilteredClasses([])
      }
    } catch (error) {
      console.error("Error fetching classes:", error)
      setErrorMessage("Failed to load classes. Please try again.")
      setFilteredClasses([])
    }

    setFilteredUnits([]) // Clear units when semester changes
    setIsLoading(false)
  }

  // LEVEL 2: Class Selection (FIXED API CALL)
  const handleClassChange = async (classId: number | string) => {
    if (!formState) return

    setIsLoading(true)
    const numericClassId = Number(classId)

    if (isNaN(numericClassId)) {
      console.error("Invalid class ID:", classId)
      setIsLoading(false)
      return
    }

    // Find the selected class
    const selectedClass = filteredClasses.find((c) => c.id === numericClassId)

    // CASCADING LOGIC: Reset unit when class changes
    setFormState((prev) => ({
      ...prev!,
      class_id: numericClassId,
      class_code: selectedClass?.code || "",
      class_name: selectedClass?.name || "",
      unit_id: 0, // Reset unit selection
      unit_code: "",
      unit_name: "",
      no: 0, // Reset student count
      lecturer_id: null,
      lecturer_name: "",
      chief_invigilator: "", // Reset chief invigilator
    }))

    try {
      // FIXED: Use the correct API endpoint
      const response = await fetch("/api/units-by-class-semester", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCSRFToken(),
        },
        body: JSON.stringify({
          semester_id: formState.semester_id,
          class_id: numericClassId,
        }),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        console.log(`Found ${data.units.length} units for class ID: ${numericClassId}`, data.units)
        setFilteredUnits(data.units)
        setErrorMessage(null)
      } else {
        console.error("Failed to load units:", data.message)
        setErrorMessage(data.message || "Failed to load units")
        setFilteredUnits([])
      }
    } catch (error) {
      console.error("Error fetching units:", error)
      setErrorMessage("Failed to load units. Please try again.")
      setFilteredUnits([])
    }

    setIsLoading(false)
  }

  // LEVEL 3: Unit Selection
  const handleUnitChange = (unitId: number) => {
    if (!formState) return

    const selectedUnit = filteredUnits.find((u) => u.id === Number(unitId))
    if (selectedUnit) {
      console.log("Selected unit:", selectedUnit)

      const studentCount = selectedUnit.student_count || 0
      const lecturerName = selectedUnit.lecturer_name || ""

      setFormState((prev) => ({
        ...prev!,
        unit_id: Number(unitId),
        unit_code: selectedUnit.code,
        unit_name: selectedUnit.name,
        no: studentCount,
        chief_invigilator: lecturerName || prev!.chief_invigilator,
      }))

      // Check venue capacity if venue is already selected
      if (formState.venue) {
        checkVenueCapacity(formState.venue, studentCount)
      }

      // Check for conflicts if we have enough data
      if (formState.date && formState.start_time && formState.end_time && formState.venue) {
        checkForConflicts(formState.date, formState.start_time, formState.end_time, Number(unitId), formState.venue)
      }

      // Set lecturer information
      if (selectedUnit.lecturer_name && selectedUnit.lecturer_code) {
        const lecturer = {
          id: Number(selectedUnit.lecturer_code),
          name: selectedUnit.lecturer_name,
        }
        setUnitLecturers([lecturer])
      } else {
        setUnitLecturers([])
      }
    }
  }

  const handleLecturerChange = (lecturerId: number) => {
    if (!formState) return

    const selectedLecturer = lecturers.find((l) => l.id === Number(lecturerId))
    if (selectedLecturer) {
      setFormState((prev) => ({
        ...prev!,
        lecturer_id: Number(lecturerId),
        lecturer_name: selectedLecturer.name,
        chief_invigilator: selectedLecturer.name,
      }))
    }
  }

  const checkVenueCapacity = (venueName: string, studentCount: number) => {
    const selectedExamroom = examrooms.find((e) => e.name === venueName)

    if (selectedExamroom) {
      if (studentCount > selectedExamroom.capacity) {
        setCapacityWarning(
          `Warning: Not enough space! The venue ${venueName} has a capacity of ${selectedExamroom.capacity}, ` +
            `but there are ${studentCount} students enrolled (exceeding by ${studentCount - selectedExamroom.capacity} students).`,
        )
      } else {
        setCapacityWarning(null)
      }

      return selectedExamroom
    }

    return null
  }

  const handleVenueChange = (venueName: string) => {
    if (!formState) return

    const selectedExamroom = checkVenueCapacity(venueName, formState.no)

    if (selectedExamroom) {
      setFormState((prev) => ({
        ...prev!,
        venue: venueName,
        location: selectedExamroom.location,
      }))

      if (formState.date && formState.start_time && formState.end_time && formState.unit_id) {
        checkForConflicts(formState.date, formState.start_time, formState.end_time, formState.unit_id, venueName)
      }
    }
  }

  const handleCreateChange = (field: string, value: string | number) => {
    if (!formState) return

    setFormState((prev) => ({
      ...prev!,
      [field]: value,
    }))
  }

  const handleProcessTimetable = () => {
    router.post(
      "/process-examtimetables",
      {},
      {
        onSuccess: () => alert("Timetable processed successfully."),
        onError: () => alert("Failed to process timetable."),
      },
    )
  }

  const handleSolveConflicts = () => {
    router.get(
      "/solve-exam-conflicts",
      {},
      {
        onSuccess: () => alert("Conflicts resolved successfully."),
        onError: () => alert("Failed to resolve conflicts."),
      },
    )
  }

  const handleDownloadTimetable = () => {
    const link = document.createElement("a")
    link.href = "/download-examtimetables"
    link.setAttribute("download", "examtimetable.pdf")
    link.setAttribute("target", "_blank")
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchValue(e.target.value)
  }

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault()
    router.get("/examtimetable", { search: searchValue, perPage: rowsPerPage })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value)
    setRowsPerPage(newPerPage)
    router.get("/examtimetable", { search: searchValue, perPage: newPerPage })
  }

  const handleSubmitForm = (data: FormState) => {
    const formattedData = {
      ...data,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
    }

    console.log("Submitting form data:", formattedData)

    if (data.id === 0) {
      router.post(`/examtimetable`, formattedData, {
        onError: (errors) => {
          console.error("Creation failed:", errors)
        },
        onSuccess: () => {
          console.log("Creation successful")
          handleCloseModal()
          router.reload({
            only: ["examTimetables"],
            onSuccess: () => {
              console.log("Page data refreshed successfully")
            },
          })
        },
      })
    } else {
      router.put(`/examtimetable/${data.id}`, formattedData, {
        onError: (errors) => {
          console.error("Update failed:", errors)
        },
        onSuccess: () => {
          console.log("Update successful")
          handleCloseModal()
          router.reload({
            only: ["examTimetables"],
            onSuccess: () => {
              console.log("Page data refreshed successfully")
            },
          })
        },
      })
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Exam Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>

        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                + Add Exam
              </Button>
            )}

            {can.process && (
              <Button onClick={handleProcessTimetable} className="bg-blue-500 hover:bg-blue-600">
                Process Exam Timetable
              </Button>
            )}

            {can.solve_conflicts && (
              <Button onClick={handleSolveConflicts} className="bg-purple-500 hover:bg-purple-600">
                Solve Exam Conflicts
              </Button>
            )}

            {can.download && (
              <Button onClick={handleDownloadTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download Exam Timetable
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue}
              onChange={handleSearchChange}
              placeholder="Search exam timetable..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              Search
            </Button>
          </form>
          <div>
            <label className="mr-2">Rows per page:</label>
            <select value={rowsPerPage} onChange={handlePerPageChange} className="border rounded p-2">
              {[5, 10, 15, 20].map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        </div>

        {examTimetables?.data?.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full mt-6 border text-sm text-left">
                <thead className="bg-gray-100 border-b">
                  <tr>
                    <th className="px-3 py-2">ID</th>
                    <th className="px-3 py-2">Day</th>
                    <th className="px-3 py-2">Date</th>
                    <th className="px-3 py-2">Class</th>
                    <th className="px-3 py-2">Unit Code</th>
                    <th className="px-3 py-2">Unit Name</th>
                    <th className="px-3 py-2">Semester</th>
                    <th className="px-3 py-2">Venue</th>
                    <th className="px-3 py-2">Time</th>
                    <th className="px-3 py-2">Chief Invigilator</th>
                    <th className="px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {examTimetables.data.map((examtimetable) => (
                    <tr key={examtimetable.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2">{examtimetable.id}</td>
                      <td className="px-3 py-2">{examtimetable.day}</td>
                      <td className="px-3 py-2">{examtimetable.date}</td>
                      <td className="px-3 py-2">{examtimetable.class_code}</td>
                      <td className="px-3 py-2">{examtimetable.unit_code}</td>
                      <td className="px-3 py-2">{examtimetable.unit_name}</td>
                      <td className="px-3 py-2">{examtimetable.semester_name}</td>
                      <td className="px-3 py-2">{examtimetable.venue}</td>
                      <td className="px-3 py-2">
                        {examtimetable.start_time} - {examtimetable.end_time}
                      </td>
                      <td className="px-3 py-2">{examtimetable.chief_invigilator}</td>
                      <td className="px-3 py-2 flex space-x-2">
                        <Button
                          onClick={() => handleOpenModal("view", examtimetable)}
                          className="bg-blue-500 hover:bg-blue-600 text-white"
                        >
                          View
                        </Button>
                        {can.edit && (
                          <Button
                            onClick={() => handleOpenModal("edit", examtimetable)}
                            className="bg-yellow-500 hover:bg-yellow-600 text-white"
                          >
                            Edit
                          </Button>
                        )}
                        {can.delete && (
                          <Button
                            onClick={() => handleDelete(examtimetable.id)}
                            className="bg-red-500 hover:bg-red-600 text-white"
                          >
                            Delete
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {examTimetables.links && examTimetables.links.length > 3 && (
              <div className="flex justify-center mt-4">
                <nav className="flex items-center">
                  {examTimetables.links.map((link, index) => (
                    <button
                      key={index}
                      onClick={() => {
                        if (link.url) {
                          router.visit(link.url)
                        }
                      }}
                      className={`px-3 py-1 mx-1 border rounded ${
                        link.active ? "bg-blue-500 text-white" : "bg-white text-gray-700"
                      } ${!link.url ? "opacity-50 cursor-not-allowed" : "hover:bg-gray-100"}`}
                      disabled={!link.url}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </nav>
              </div>
            )}
          </>
        ) : (
          <p className="mt-6 text-gray-600">No exam timetables available yet.</p>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded shadow-md w-[500px] max-h-[90vh] overflow-y-auto">
              {modalType === "view" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Exam Timetable</h2>
                  <div className="space-y-2">
                    <p>
                      <strong>Day:</strong> {selectedTimetable.day}
                    </p>
                    <p>
                      <strong>Date:</strong> {selectedTimetable.date}
                    </p>
                    <p>
                      <strong>Class:</strong> {selectedTimetable.class_code} - {selectedTimetable.class_name}
                    </p>
                    <p>
                      <strong>Unit Code:</strong> {selectedTimetable.unit_code}
                    </p>
                    <p>
                      <strong>Unit Name:</strong> {selectedTimetable.unit_name}
                    </p>
                    <p>
                      <strong>Semester:</strong> {selectedTimetable.semester_name}
                    </p>
                    <p>
                      <strong>Time:</strong> {selectedTimetable.start_time} - {selectedTimetable.end_time}
                    </p>
                    <p>
                      <strong>Venue:</strong> {selectedTimetable.venue}
                    </p>
                    <p>
                      <strong>Location:</strong> {selectedTimetable.location}
                    </p>
                    <p>
                      <strong>Number of Students:</strong> {selectedTimetable.no}
                    </p>
                    <p>
                      <strong>Chief Invigilator:</strong> {selectedTimetable.chief_invigilator}
                    </p>
                  </div>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">
                    Close
                  </Button>
                </>
              )}

              {(modalType === "edit" || modalType === "create") && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">
                    {modalType === "create" ? "Create Exam Timetable" : "Edit Exam Timetable"}
                  </h2>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    <label className="block text-sm font-medium text-gray-700 mb-1">Time Slot</label>
                    <select
                      value={formState.timeslot_id || ""}
                      onChange={(e) => handleTimeSlotChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                    >
                      <option value="">Select Time Slot</option>
                      {availableTimeSlots?.map((slot) => (
                        <option key={slot.id} value={slot.id}>
                          {slot.day} ({slot.date}) - {slot.start_time} to {slot.end_time}
                        </option>
                      )) || null}
                    </select>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <input
                          type="text"
                          value={formState.day}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input
                          type="text"
                          value={formState.date}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input
                          type="text"
                          value={formState.start_time}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input
                          type="text"
                          value={formState.end_time}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                    </div>

                    {/* LEVEL 1: Semester Selection */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Semester <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={formState.semester_id || ""}
                      onChange={(e) => handleSemesterChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                      required
                    >
                      <option value="">Select Semester</option>
                      {semesters?.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      )) || null}
                    </select>

                    {isLoading && (
                      <div className="text-center py-2 mb-3">
                        <span className="inline-block animate-spin mr-2">⟳</span>
                        Loading...
                      </div>
                    )}

                    {errorMessage && (
                      <Alert className="mb-3 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-600">{errorMessage}</AlertDescription>
                      </Alert>
                    )}

                    {/* LEVEL 2: Class Selection */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Class <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={formState.class_id || ""}
                      onChange={(e) => handleClassChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                      disabled={!formState.semester_id || isLoading}
                      required
                    >
                      <option value="">
                        {!formState.semester_id
                          ? "Please select a semester first"
                          : filteredClasses.length === 0
                            ? "No classes available for this semester"
                            : "Select Class"}
                      </option>
                      {filteredClasses && filteredClasses.length > 0
                        ? filteredClasses.map((classItem) => (
                            <option key={classItem.id} value={classItem.id}>
                              {classItem.code} - {classItem.name}
                            </option>
                          ))
                        : null}
                    </select>

                    {/* LEVEL 3: Unit Selection */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Unit <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={formState.unit_id || ""}
                      onChange={(e) => handleUnitChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                      disabled={!formState.class_id || isLoading}
                      required
                    >
                      <option value="">
                        {!formState.class_id
                          ? "Please select a class first"
                          : filteredUnits.length === 0
                            ? "No units available for this class"
                            : "Select Unit"}
                      </option>
                      {filteredUnits && filteredUnits.length > 0
                        ? filteredUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                              {unit.code} - {unit.name}
                            </option>
                          ))
                        : null}
                    </select>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Unit Code</label>
                        <input
                          type="text"
                          value={formState.unit_code || ""}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Number of Students</label>
                        <input
                          type="number"
                          value={formState.no}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                        />
                      </div>
                    </div>

                    {unitLecturers.length > 0 && (
                      <>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Lecturer</label>
                        <select
                          value={formState.lecturer_id || ""}
                          onChange={(e) => handleLecturerChange(Number(e.target.value))}
                          className="w-full border rounded p-2 mb-3"
                        >
                          <option value="">Select Lecturer</option>
                          {unitLecturers.map((lecturer) => (
                            <option key={lecturer.id} value={lecturer.id}>
                              {lecturer.name}
                            </option>
                          ))}
                        </select>
                      </>
                    )}

                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Venue <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={formState.venue}
                      onChange={(e) => handleVenueChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                      required
                    >
                      <option value="">Select Venue</option>
                      {examrooms?.map((examroom) => (
                        <option key={examroom.id} value={examroom.name}>
                          {examroom.name} (Capacity: {examroom.capacity})
                        </option>
                      )) || null}
                    </select>

                    {capacityWarning && (
                      <Alert className="mb-3 bg-red-50 border-red-200">
                        <AlertCircle className="h-4 w-4 text-red-500" />
                        <AlertDescription className="text-red-500">{capacityWarning}</AlertDescription>
                      </Alert>
                    )}

                    {conflictWarning && (
                      <Alert className="mb-3 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-600">{conflictWarning}</AlertDescription>
                      </Alert>
                    )}

                    <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input
                      type="text"
                      value={formState.location}
                      className="w-full border rounded p-2 mb-3 bg-gray-50"
                      readOnly
                    />

                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Chief Invigilator <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={formState.chief_invigilator}
                      onChange={(e) => handleCreateChange("chief_invigilator", e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                      placeholder="Enter chief invigilator name"
                      required
                    />

                    <div className="mt-4 flex justify-end space-x-2">
                      <Button
                        type="submit"
                        className="bg-blue-500 hover:bg-blue-600 text-white"
                        disabled={isLoading || (capacityWarning !== null && capacityWarning !== "")}
                      >
                        {isLoading ? "Saving..." : "Save"}
                      </Button>
                      <Button type="button" onClick={handleCloseModal} className="bg-gray-400 text-white">
                        Cancel
                      </Button>
                    </div>
                  </form>
                </>
              )}

              {modalType === "delete" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Delete Exam Timetable</h2>
                  <p>Are you sure you want to delete this timetable?</p>
                  <div className="mt-4 flex justify-end space-x-2">
                    <Button
                      onClick={() => handleDelete(selectedTimetable.id)}
                      className="bg-red-500 hover:bg-red-600 text-white"
                    >
                      Delete
                    </Button>
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white">
                      Cancel
                    </Button>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default ExamTimetable