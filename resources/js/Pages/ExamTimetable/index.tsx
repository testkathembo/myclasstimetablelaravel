"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
// Remove the problematic import
// import { Inertia } from "@inertiajs/inertia"
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
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  semester_name: string
}

interface Enrollment {
  id: number
  student_code: string // Changed from student_id to student_code
  unit_id: number
  semester_id: number
  lecturer_code: string | null // Changed from lecturer_id to lecturer_code
  created_at: string
  updated_at: string
  lecturer_name?: string | null
  unit_code?: string
  unit_name?: string
}

interface Examroom {
  id: number
  name: string
  capacity: number
  location: string
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

interface Unit {
  id: number
  code: string
  name: string
  semester_id: number
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
  enrollment_id: number
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  unit_id?: number
  unit_code?: string
  unit_name?: string
  timeslot_id?: number
  lecturer_id?: number | null
  lecturer_name?: string | null
}

// Helper function to ensure time is in H:i format
const formatTimeToHi = (timeStr: string) => {
  // If the time already has the correct format (H:i), return it
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeStr)) {
    return timeStr
  }

  // If the time has seconds (H:i:s), remove them
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(timeStr)) {
    return timeStr.substring(0, 5)
  }

  // If it's in 12-hour format with AM/PM, convert to 24-hour
  if (timeStr.includes("AM") || timeStr.includes("PM")) {
    const [time, modifier] = timeStr.split(" ")
    let [hours, minutes] = time.split(":").map(Number)

    if (modifier === "PM" && hours < 12) hours += 12
    if (modifier === "AM" && hours === 12) hours = 0

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`
  }

  // If we can't parse it, return as is (validation will catch it)
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
    can = { create: false, edit: false, delete: false, process: false, solve_conflicts: false, download: false },
    enrollments = [],
    examrooms = [],
    timeSlots = [],
    units = [],
    lecturers = [],
  } = usePage().props as unknown as {
    examTimetables: PaginatedExamTimetables
    perPage: number
    search: string
    semesters: Semester[]
    enrollments: Enrollment[]
    examrooms: Examroom[]
    timeSlots: TimeSlot[]
    units: Unit[]
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

  // Debug enrollments data structure
  useEffect(() => {
    console.log("Enrollments data structure:", enrollments.slice(0, 3))
    console.log("Lecturers:", lecturers)
  }, [enrollments, lecturers])

  useEffect(() => {
    console.log("Lecturers:", lecturers) // Debug lecturers array
  }, [lecturers])

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
        enrollment_id: 0,
        venue: "",
        location: "",
        no: 0,
        chief_invigilator: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
        unit_id: 0,
        unit_code: "",
        unit_name: "",
        timeslot_id: 0,
        lecturer_id: null,
        lecturer_name: "",
      })
      // Reset filtered units
      setFilteredUnits([])
    } else if (examtimetable) {
      // For edit/view, find the matching unit
      const unit = units.find((u) => u.code === examtimetable.unit_code)

      // Find matching timeslot
      const timeSlot = timeSlots.find(
        (ts) =>
          ts.day === examtimetable.day &&
          ts.date === examtimetable.date &&
          ts.start_time === examtimetable.start_time &&
          ts.end_time === examtimetable.end_time,
      )

      // Find lecturer for this unit
      const unitEnrollment = enrollments.find(
        (e) => e.unit_code === examtimetable.unit_code && Number(e.semester_id) === Number(examtimetable.semester_id),
      )

      setFormState({
        ...examtimetable,
        enrollment_id: unitEnrollment?.id || 0,
        unit_id: unit?.id || 0,
        timeslot_id: timeSlot?.id || 0,
        lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null, // Changed lecturer_id to lecturer_code
        lecturer_name: unitEnrollment?.lecturer_name || "",
      })

      // Filter units for the selected semester
      if (examtimetable.semester_id) {
        const semesterUnits = units.filter((unit) => unit.semester_id === examtimetable.semester_id)
        setFilteredUnits(semesterUnits)

        // Find lecturers for this unit
        if (unit) {
          findLecturersForUnit(unit.id, examtimetable.semester_id)
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
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this exam timetable?")) {
        try {
            await router.delete(`/examtimetable/${id}`, {
                onSuccess: () => alert("Exam timetable deleted successfully."),
                onError: (errors) => {
                    console.error("Failed to delete exam timetable:", errors);
                    alert("An error occurred while deleting the exam timetable.");
                },
            });
        } catch (error) {
            console.error("Unexpected error:", error);
            alert("An unexpected error occurred.");
        }
    }
};

  const checkForConflicts = (
    date: string,
    startTime: string,
    endTime: string,
    unitId: number | undefined,
    venueId: string,
  ) => {
    // Skip if we don't have all the necessary data
    if (!date || !startTime || !endTime || !unitId || !venueId) {
      setConflictWarning(null)
      return false
    }

    // Check for conflicts with existing exams
    const conflicts = examTimetables.data.filter((examtimetable) => {
      // Skip the current exam when editing
      if (selectedTimetable && examtimetable.id === selectedTimetable.id) return false

      // Check for time overlap on the same date
      const hasTimeOverlap = examtimetable.date === date && checkTimeOverlap(examtimetable, date, startTime, endTime)

      // Check if it's the same unit (unit conflict)
      const isSameUnit = examtimetable.unit_code === units.find((u) => u.id === unitId)?.code

      // Check if it's the same venue (venue conflict)
      const isSameVenue = examtimetable.venue === venueId

      // Return true if there's a time overlap AND (same unit OR same venue)
      return hasTimeOverlap && (isSameUnit || isSameVenue)
    })

    if (conflicts.length > 0) {
      // Create conflict warning message
      const unitConflicts = conflicts.filter((examtimetable) => examtimetable.unit_code === units.find((u) => u.id === unitId)?.code)
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

      // Check for conflicts if we have enough data
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

  const findLecturersForUnit = (unitId: number, semesterId: number) => {
    // Find all enrollments for this unit in the selected semester
    const unitEnrollments = enrollments.filter(
      (e) => e.unit_id === unitId && Number(e.semester_id) === Number(semesterId) && e.lecturer_code, // Changed lecturer_id to lecturer_code
    )

    // Extract unique lecturer codes
    const uniqueLecturerCodes = Array.from(new Set(unitEnrollments.map((e) => e.lecturer_code).filter(Boolean)))

    // Find lecturer details
    const unitLecturersList = lecturers.filter((l) => uniqueLecturerCodes.includes(l.id.toString())) // Match lecturer_code with lecturer.id

    console.log(
      `Found ${unitLecturersList.length} lecturers for unit ID ${unitId} in semester ${semesterId}:`,
      unitLecturersList,
    )

    setUnitLecturers(unitLecturersList)

    // Return the first lecturer if available
    return unitLecturersList.length > 0 ? unitLecturersList[0] : null
  }

  const handleSemesterChange = (semesterId: number | string) => {
    if (!formState) return

    setIsLoading(true)
    setErrorMessage(null)
    setUnitLecturers([])

    // Ensure semesterId is a number
    const numericSemesterId = Number(semesterId)

    if (isNaN(numericSemesterId)) {
      console.error("Invalid semester ID:", semesterId)
      setErrorMessage("Invalid semester ID")
      setIsLoading(false)
      return
    }

    // Update form state with the new semester_id
    setFormState((prev) => ({
      ...prev!,
      semester_id: numericSemesterId,
      unit_id: 0,
      unit_code: "",
      unit_name: "",
      no: 0,
      lecturer_id: null,
      lecturer_name: "",
      chief_invigilator: "",
    }))

    console.log(`Selected semester ID: ${numericSemesterId}`)
    console.log("Enrollments data:", enrollments)

    // Log the structure of a few enrollment objects to understand their properties
    if (enrollments.length > 0) {
      console.log("Sample enrollment object:", enrollments[0])
      console.log("Enrollment properties:", Object.keys(enrollments[0]))
    }

    // Find all enrollments for the selected semester
    const semesterEnrollments = enrollments.filter((enrollment) => {
      const enrollmentSemesterId = Number(enrollment.semester_id)
      const matches = enrollmentSemesterId === numericSemesterId
      console.log(
        `Enrollment ${enrollment.id}: semester_id ${enrollmentSemesterId} matches ${numericSemesterId}? ${matches}`,
      )
      return matches
    })

    console.log(
      `Found ${semesterEnrollments.length} enrollments for semester ${numericSemesterId}:`,
      semesterEnrollments,
    )

    // Extract unit_ids from these enrollments
    const unitIdsInSemester = semesterEnrollments.map((enrollment) => enrollment.unit_id)
    console.log("Unit IDs with enrollments in this semester:", unitIdsInSemester)

    // Then filter units that match these unit_ids
    const semesterUnits = units.filter((unit) => {
      const isUnitInSemester = unitIdsInSemester.includes(unit.id)
      console.log(`Unit ${unit.code} (ID: ${unit.id}): included in semester ${numericSemesterId}? ${isUnitInSemester}`)
      return isUnitInSemester
    })

    console.log(`Found ${semesterUnits.length} units with enrollments in semester ID: ${numericSemesterId}`)

    if (semesterUnits.length === 0) {
      console.warn(`No units found with enrollments for semester ID: ${numericSemesterId}`)
      setErrorMessage(
        `No units found for semester ${semesters.find((s) => s.id === numericSemesterId)?.name || numericSemesterId}`,
      )
    } else {
      setErrorMessage(null)
    }

    setFilteredUnits(semesterUnits)
    setIsLoading(false)
  }

  // Add this at the top level of your component, after other useEffect hooks
  useEffect(() => {
    // Log the structure of the first few units to understand their data format
    if (units && units.length > 0) {
      console.log("Sample unit data structure:", units.slice(0, 3))
      console.log(
        "Units by semester:",
        units.reduce(
          (acc, unit) => {
            acc[unit.semester_id] = acc[unit.semester_id] || []
            acc[unit.semester_id].push(unit)
            return acc
          },
          {} as Record<number, Unit[]>,
        ),
      )
    }
  }, [units])

  // Add this useEffect to log when filteredUnits changes
  useEffect(() => {
    console.log("filteredUnits updated:", filteredUnits)
  }, [filteredUnits])

  // Debugging: Log units received from the backend
  useEffect(() => {
    console.log("Units received from backend:", units)
  }, [units])

  const handleUnitChange = (unitId: number) => {
    if (!formState) return

    const selectedUnit = units.find((u) => u.id === Number(unitId))
    if (selectedUnit) {
      // Find enrollments for this unit in the selected semester
      const unitEnrollments = enrollments.filter(
        (e) => e.unit_id === selectedUnit.id && Number(e.semester_id) === Number(formState.semester_id),
      )

      // Count unique students enrolled in this unit
      const studentCount = unitEnrollments.length

      // Find the lecturer for this unit
      const lecturerEnrollment = unitEnrollments.find((e) => e.lecturer_name)
      const lecturerName = lecturerEnrollment?.lecturer_name || ""

      console.log(
        `Found ${studentCount} students enrolled in unit ${selectedUnit.code} for semester ${formState.semester_id}`,
      )
      console.log(`Lecturer for unit ${selectedUnit.code}: ${lecturerName}`)

      setFormState((prev) => ({
        ...prev!,
        unit_id: Number(unitId),
        unit_code: selectedUnit.code,
        unit_name: selectedUnit.name,
        no: studentCount, // Set the actual count of enrolled students
        chief_invigilator: lecturerName || prev!.chief_invigilator, // Set lecturer as chief invigilator if available
      }))

      // Check venue capacity if venue is already selected
      if (formState.venue) {
        checkVenueCapacity(formState.venue, studentCount)
      }

      // Check for conflicts if we have enough data
      if (formState.date && formState.start_time && formState.end_time && formState.venue) {
        checkForConflicts(formState.date, formState.start_time, formState.end_time, Number(unitId), formState.venue)
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
        chief_invigilator: selectedLecturer.name, // Update chief invigilator with lecturer name
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

      // Check for conflicts if we have enough data
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

    if (field === "chief_invigilator") {
      // Just update the chief invigilator field
      setFormState((prev) => ({
        ...prev!,
        chief_invigilator: value as string,
      }))
    }
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
    window.open("/download-examtimetables", "_blank")
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
    // Format start_time and end_time to H:i
    const formattedData = {
      ...data,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
    }

    console.log("Submitting form data:", formattedData) // Debug the data being sent

    if (data.id === 0) {
      // Create a new exam timetable
      router.post(`/examtimetable`, formattedData, {
        onError: (errors) => {
          console.error("Creation failed:", errors) // Debug errors
        },
        onSuccess: () => {
          console.log("Creation successful")
          handleCloseModal() // Close the modal after successful creation

          // Reload the page to refresh the data
          router.reload({
            only: ["examTimetables"],
            onSuccess: () => {
              console.log("Page data refreshed successfully")
            },
          })
        },
      })
    } else {
      // Update an existing exam timetable
      router.put(`/examtimetable/${data.id}`, formattedData, {
        onError: (errors) => {
          console.error("Update failed:", errors) // Debug errors
        },
        onSuccess: () => {
          console.log("Update successful")
          handleCloseModal() // Close the modal after successful update

          // Reload the page to refresh the data
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

  // Debug effect to monitor filtered units
  useEffect(() => {
    if (formState?.semester_id) {
      console.log("Current semester ID:", formState.semester_id)
      console.log("Filtered units count:", filteredUnits.length)
    }
  }, [formState?.semester_id, filteredUnits])

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

              {modalType === "edit" && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Edit Exam Timetable</h2>
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                    <select
                      value={formState.semester_id || ""}
                      onChange={(e) => handleSemesterChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
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
                        Loading units...
                      </div>
                    )}

                    {errorMessage && (
                      <Alert className="mb-3 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-600">{errorMessage}</AlertDescription>
                      </Alert>
                    )}

                    <label className="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <select
                      value={formState.unit_id || ""}
                      onChange={(e) => handleUnitChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                      disabled={!formState.semester_id || isLoading}
                    >
                      <option value="">Select Unit</option>
                      {filteredUnits && filteredUnits.length > 0 ? (
                        filteredUnits.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.code} - {unit.name}
                          </option>
                        ))
                      ) : (
                        <option value="" disabled>
                          No units available for this semester
                        </option>
                      )}
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                    <select
                      value={formState.venue}
                      onChange={(e) => handleVenueChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Chief Invigilator</label>
                    <input
                      type="text"
                      value={formState.chief_invigilator}
                      onChange={(e) => handleCreateChange("chief_invigilator", e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                    />

                    <div className="mt-4 flex justify-end space-x-2">
                      <Button
                        type="submit"
                        className="bg-blue-500 hover:bg-blue-600 text-white"
                        disabled={capacityWarning !== null || conflictWarning !== null}
                      >
                        Save
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

              {modalType === "create" && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Create Exam Timetable</h2>
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                    <select
                      value={formState.semester_id || ""}
                      onChange={(e) => handleSemesterChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
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
                        Loading units...
                      </div>
                    )}

                    {errorMessage && (
                      <Alert className="mb-3 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-600">{errorMessage}</AlertDescription>
                      </Alert>
                    )}

                    <label className="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <select
                      value={formState.unit_id || ""}
                      onChange={(e) => handleUnitChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                      disabled={!formState.semester_id || isLoading}
                    >
                      <option value="">Select Unit</option>
                      {filteredUnits && filteredUnits.length > 0 ? (
                        filteredUnits.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.code} - {unit.name}
                          </option>
                        ))
                      ) : (
                        <option value="" disabled>
                          No units available for this semester
                        </option>
                      )}
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                    <select
                      value={formState.venue}
                      onChange={(e) => handleVenueChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Chief Invigilator</label>
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
                        disabled={capacityWarning !== null || conflictWarning !== null}
                      >
                        Save
                      </Button>
                      <Button type="button" onClick={handleCloseModal} className="bg-gray-400 text-white">
                        Cancel
                      </Button>
                    </div>
                  </form>
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
