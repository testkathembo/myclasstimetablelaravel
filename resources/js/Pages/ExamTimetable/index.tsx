"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "../../components/ui/button"

interface ExamTimetable {
  id: number
  day: string
  date: string
  unit_id: number
  unit_name: string
  group: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
}

interface Enrollment {
  id: number
  unit_name: string
  semester_id: number
  unit_id: number
  student_count: number
  lecturer_id: number | null
  lecturer_name: string | null
}

interface Classroom {
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
  group: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
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
const checkTimeOverlap = (exam: ExamTimetable, date: string, startTime: string, endTime: string) => {
  if (exam.date !== date) return false

  return (
    (exam.start_time <= startTime && exam.end_time > startTime) ||
    (exam.start_time < endTime && exam.end_time >= endTime) ||
    (exam.start_time >= startTime && exam.end_time <= endTime)
  )
}

const ExamTimetable = () => {
  const { examTimetables, perPage, search, semesters, enrollments, timeSlots, classrooms } = usePage().props as {
    examTimetables: PaginatedExamTimetables
    perPage: number
    search: string
    semesters: Semester[]
    enrollments: Enrollment[]
    timeSlots: TimeSlot[]
    classrooms: Classroom[]
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "">("")
  const [selectedSemester, setSelectedSemester] = useState<number | null>(null)
  const [selectedTimeSlotId, setSelectedTimeSlotId] = useState<number | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [itemsPerPage, setItemsPerPage] = useState(perPage)
  const [searchQuery, setSearchQuery] = useState(search)
  const [scheduledStudents, setScheduledStudents] = useState<{ [key: string]: number }>({})
  const [selectedClassroom, setSelectedClassroom] = useState<number | null>(null)
  const [remainingCapacity, setRemainingCapacity] = useState<number | null>(null)

  // Instead of filtering classrooms, we'll enhance them with capacity information
  const [classroomsWithCapacity, setClassroomsWithCapacity] = useState<
    Array<
      Classroom & {
        remainingCapacity?: number
        isSuitable?: boolean
      }
    >
  >([])

  const filteredEnrollments = selectedSemester
    ? enrollments
        .filter((enrollment) => enrollment.semester_id === selectedSemester)
        .filter((enrollment, index, self) => index === self.findIndex((e) => e.unit_name === enrollment.unit_name))
    : []

  const handleOpenModal = (type: "create" | "edit" | "delete", timetable: ExamTimetable | null = null) => {
    setModalType(type)
    if (type === "create") {
      setFormState({
        id: 0,
        day: "",
        date: "",
        enrollment_id: 0,
        group: "",
        venue: "",
        location: "",
        no: 0,
        chief_invigilator: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
      })
      // Reset the classrooms with capacity
      setClassroomsWithCapacity(
        classrooms.map((c) => ({
          ...c,
          remainingCapacity: c.capacity,
          isSuitable: true,
        })),
      )
    } else if (timetable) {
      const selectedEnrollment = enrollments.find((e) => e.unit_id === timetable.unit_id)

      // Format the time values to ensure they're in H:i format
      const formattedStartTime = formatTimeToHi(timetable.start_time)
      const formattedEndTime = formatTimeToHi(timetable.end_time)

      setFormState({
        id: timetable.id,
        day: timetable.day,
        date: timetable.date,
        enrollment_id: selectedEnrollment?.id || 0,
        group: timetable.group,
        venue: timetable.venue,
        location: timetable.location,
        no: timetable.no,
        chief_invigilator: timetable.chief_invigilator,
        start_time: formattedStartTime,
        end_time: formattedEndTime,
        semester_id: timetable.semester_id,
      })
      setSelectedSemester(timetable.semester_id)

      // If we're editing, we need to calculate the classroom capacities
      if (timetable.date && timetable.start_time && timetable.end_time) {
        calculateVenueOccupancy(timetable.date, formattedStartTime, formattedEndTime, timetable.id)
      }
    }
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setFormState(null)
    setSelectedSemester(null)
    setSelectedTimeSlotId(null)
    setClassroomsWithCapacity([])
    setSelectedClassroom(null)
    setRemainingCapacity(null)
    setScheduledStudents({})
  }

  // Calculate venue occupancy based on date and time
  const calculateVenueOccupancy = (date: string, startTime: string, endTime: string, currentExamId = 0) => {
    const venueOccupancy: { [key: string]: number } = {}

    // Make sure examTimetables exists and has data
    if (examTimetables && examTimetables.data) {
      examTimetables.data.forEach((exam) => {
        // Skip the current exam if we're editing
        if (exam.id === currentExamId) return

        // Check if this exam overlaps with the selected time slot
        if (checkTimeOverlap(exam, date, startTime, endTime)) {
          // If this venue isn't in our occupancy map yet, initialize it
          if (!venueOccupancy[exam.venue]) {
            venueOccupancy[exam.venue] = 0
          }
          // Add the student count to the venue's occupancy
          venueOccupancy[exam.venue] += exam.no
        }
      })
    }

    setScheduledStudents(venueOccupancy)

    // Update classrooms with capacity information
    const updatedClassrooms = classrooms
      .map((c) => {
        const existingStudents = venueOccupancy[c.name] || 0
        const remainingCapacity = c.capacity - existingStudents
        const isSuitable = formState ? remainingCapacity >= formState.no : true

        return {
          ...c,
          remainingCapacity,
          isSuitable,
        }
      })
      .sort((a, b) => {
        // Sort by suitability first (suitable classrooms first)
        if (a.isSuitable !== b.isSuitable) {
          return a.isSuitable ? -1 : 1
        }
        // Then sort by capacity (smallest suitable capacity first)
        return a.capacity - b.capacity
      })

    setClassroomsWithCapacity(updatedClassrooms)

    // If a classroom was already selected, update the remaining capacity
    if (selectedClassroom && formState) {
      const classroom = classrooms.find((c) => c.id === selectedClassroom)
      if (classroom) {
        const existingStudents = venueOccupancy[classroom.name] || 0
        const remaining = classroom.capacity - existingStudents - formState.no
        setRemainingCapacity(remaining >= 0 ? remaining : 0)
      }
    }

    return venueOccupancy
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (!formState) {
      alert("Form state is invalid.")
      return
    }

    // Validate enrollment selection
    const selectedEnrollment = enrollments.find((e) => e.id === formState.enrollment_id)
    if (!selectedEnrollment) {
      alert("Invalid enrollment selected.")
      return
    }

    // Validate classroom selection
    if (!selectedClassroom) {
      alert("Please select a venue.")
      return
    }

    const classroom = classrooms.find((c) => c.id === selectedClassroom)
    if (!classroom) {
      alert("Selected classroom is invalid.")
      return
    }

    // Check for time slot selection
    if (!formState.start_time || !formState.end_time || !formState.date) {
      alert("Please select a time slot.")
      return
    }

    // Calculate existing students in the selected venue at the selected time
    const currentExamId = modalType === "edit" ? formState.id : 0
    let existingStudents = 0

    if (examTimetables && examTimetables.data) {
      examTimetables.data.forEach((exam) => {
        if (exam.id !== currentExamId && exam.venue === classroom.name && exam.date === formState.date) {
          // Check for time overlap
          const start24 = formState.start_time
          const end24 = formState.end_time

          const examStart = exam.start_time
          const examEnd = exam.end_time

          if (
            (examStart <= start24 && examEnd > start24) ||
            (examStart < end24 && examEnd >= end24) ||
            (examStart >= start24 && examEnd <= end24)
          ) {
            existingStudents += exam.no
          }
        }
      })
    }

    // Check if there's enough capacity
    const remainingCapacity = classroom.capacity - existingStudents
    if (formState.no > remainingCapacity) {
      alert(`ERROR: Cannot schedule this exam. The classroom ${classroom.name} has a capacity of ${classroom.capacity}, but there would be ${existingStudents + formState.no} students scheduled at this time (exceeding capacity by ${formState.no - remainingCapacity} students).

Please select a different venue with sufficient capacity.`)
      return // Prevent form submission
    }

    // Ensure time values are in the correct format before submission
    const submissionData = {
      ...formState,
      unit_id: selectedEnrollment.unit_id,
      start_time: formatTimeToHi(formState.start_time),
      end_time: formatTimeToHi(formState.end_time),
    }

    console.log("Submitting with formatted times:", submissionData.start_time, submissionData.end_time)

    const url = modalType === "create" ? "/exam-timetables" : `/exam-timetables/${formState.id}`
    const method = modalType === "create" ? "post" : "put"

    router.visit(url, {
      method,
      data: submissionData,
      onSuccess: () => {
        alert("Saved successfully")
        setIsModalOpen(false)
      },
      onError: (errors) => {
        // Display the validation errors
        const errorMessages = Object.values(errors).flat().join("\n")
        alert(`Validation failed:\n${errorMessages}`)
      },
    })
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get("/exam-timetables", { search: searchQuery, per_page: itemsPerPage }, { preserveState: true })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value, 10)
    setItemsPerPage(newPerPage)
    router.get("/exam-timetables", { per_page: newPerPage, search: searchQuery }, { preserveState: true })
  }

  const handleTimeSlotSelect = (slotId: number) => {
    const slot = timeSlots.find((t) => t.id === slotId)
    if (slot) {
      // Format the time values to ensure they're in H:i format
      const formattedStartTime = formatTimeToHi(slot.start_time)
      const formattedEndTime = formatTimeToHi(slot.end_time)

      setFormState((prev) => ({
        ...prev!,
        start_time: formattedStartTime,
        end_time: formattedEndTime,
        day: slot.day,
        date: slot.date,
      }))
      setSelectedTimeSlotId(slotId)

      // Calculate venue occupancy for this time slot
      const currentExamId = modalType === "edit" && formState ? formState.id : 0
      calculateVenueOccupancy(slot.date, formattedStartTime, formattedEndTime, currentExamId)
    }
  }

  const handleClassroomSelect = (classroomId: number) => {
    setSelectedClassroom(classroomId)
    const classroom = classrooms.find((c) => c.id === classroomId)
    if (classroom && formState) {
      setFormState((prev) => ({
        ...prev!,
        venue: classroom.name,
        location: classroom.location,
      }))

      // Calculate remaining capacity considering other exams
      const existingStudents = scheduledStudents[classroom.name] || 0
      const remaining = classroom.capacity - existingStudents - formState.no
      setRemainingCapacity(remaining >= 0 ? remaining : 0)

      // Show warning if capacity is insufficient
      if (remaining < 0) {
        alert(
          `Warning: This venue doesn't have enough capacity. It can hold ${classroom.capacity} students, but you're trying to schedule ${formState.no} students when there are already ${existingStudents} students scheduled at this time.`,
        )
      }
    }
  }

  const handleEnrollmentSelect = (enrollmentId: number) => {
    const selectedEnrollment = enrollments.find((e) => e.id === enrollmentId)
    setFormState((prev) => ({
      ...prev!,
      enrollment_id: enrollmentId,
      no: selectedEnrollment ? selectedEnrollment.student_count : 0,
      chief_invigilator: selectedEnrollment?.lecturer_name || "",
    }))

    if (selectedEnrollment && selectedTimeSlotId) {
      const studentCount = selectedEnrollment.student_count
      const slot = timeSlots.find((t) => t.id === selectedTimeSlotId)

      if (slot) {
        // Update classroom suitability based on the new student count
        const updatedClassrooms = classroomsWithCapacity
          .map((c) => {
            const existingStudents = scheduledStudents[c.name] || 0
            const remainingCapacity = c.capacity - existingStudents
            return {
              ...c,
              remainingCapacity,
              isSuitable: remainingCapacity >= studentCount,
            }
          })
          .sort((a, b) => {
            // Sort by suitability first (suitable classrooms first)
            if (a.isSuitable !== b.isSuitable) {
              return a.isSuitable ? -1 : 1
            }
            // Then sort by capacity (smallest suitable capacity first)
            return a.capacity - b.capacity
          })

        setClassroomsWithCapacity(updatedClassrooms)
      }
    }
  }

  const handleDelete = (id: number) => {
    if (confirm("Are you sure you want to delete this exam timetable?")) {
      router.delete(`/exam-timetables/${id}`, {
        onSuccess: () => alert("Exam timetable deleted successfully."),
        onError: () => alert("Failed to delete the exam timetable."),
      })
    }
  }

  const formatDate = (dateString: string) => {
    if (!dateString) return ""
    const date = new Date(dateString)
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  return (
    <AuthenticatedLayout>
      <Head title="Exam Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>
        <div className="flex justify-between items-center mb-4">
          <Button onClick={() => handleOpenModal("create")} className="bg-green-500 hover:bg-green-600">
            + Add Exam
          </Button>
          <form onSubmit={handleSearch} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search exam timetable..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              Search
            </Button>
          </form>
          <div>
            <label className="mr-2">Rows per page:</label>
            <select value={itemsPerPage} onChange={handlePerPageChange} className="border rounded p-2">
              {[5, 10, 15, 20].map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        </div>

        {examTimetables.data.length > 0 ? (
          <>
            <table className="w-full mt-6 border text-sm text-left">
              <thead className="bg-gray-100 border-b">
                <tr>
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">Unit</th>
                  <th className="px-3 py-2">Semester</th>
                  <th className="px-3 py-2">Day</th>
                  <th className="px-3 py-2">Date</th>
                  <th className="px-3 py-2">Time</th>
                  <th className="px-3 py-2">Venue</th>
                  <th className="px-3 py-2">Chief Invigilator</th>
                  <th className="px-3 py-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                {examTimetables.data.map((exam, index) => (
                  <tr key={exam.id} className="border-b">
                    <td className="px-3 py-2">{index + 1}</td>
                    <td className="px-3 py-2">{exam.unit_name}</td>
                    <td className="px-3 py-2">{semesters.find((s) => s.id === exam.semester_id)?.name}</td>
                    <td className="px-3 py-2">{exam.day}</td>
                    <td className="px-3 py-2">{exam.date}</td>
                    <td className="px-3 py-2">
                      {formatTimeToHi(exam.start_time)} - {formatTimeToHi(exam.end_time)}
                    </td>
                    <td className="px-3 py-2">{exam.venue}</td>
                    <td className="px-3 py-2">{exam.chief_invigilator}</td>
                    <td className="px-3 py-2 flex space-x-2">
                      {/* Edit Action */}
                      <Button
                        onClick={() => handleOpenModal("edit", exam)}
                        className="bg-yellow-500 hover:bg-yellow-600 text-white"
                      >
                        Edit
                      </Button>

                      {/* Delete Action */}
                      <Button onClick={() => handleDelete(exam.id)} className="bg-red-500 hover:bg-red-600 text-white">
                        Delete
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="flex justify-center mt-4">
              {examTimetables.links.map((link, index) => (
                <button
                  key={index}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                  disabled={!link.url}
                  className={`px-4 py-2 mx-1 rounded ${
                    link.active ? "bg-blue-600 text-white" : "bg-gray-200 text-gray-700 hover:bg-gray-300"
                  }`}
                >
                  {link.label === "&laquo; Previous" ? "Previous" : link.label === "Next &raquo;" ? "Next" : link.label}
                </button>
              ))}
            </div>
          </>
        ) : (
          <p className="mt-6 text-gray-600">No exam timetables available yet.</p>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div className="bg-white p-6 rounded shadow-md w-96">
              <form onSubmit={handleSubmit}>
                <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                <div className="relative">
                  <select
                    className="w-full border rounded p-2 mb-3 text-gray-800 bg-white appearance-none"
                    value={formState?.semester_id || ""}
                    onChange={(e) => {
                      const id = Number.parseInt(e.target.value)
                      setSelectedSemester(id)
                      setFormState((prev) => ({ ...prev!, semester_id: id, enrollment_id: 0 }))
                    }}
                    required
                    style={{ color: "black" }}
                  >
                    <option value="" style={{ color: "black", backgroundColor: "white" }}>
                      Select semester
                    </option>
                    {semesters.map((s) => (
                      <option
                        key={s.id}
                        value={s.id}
                        style={{ color: "black", backgroundColor: "white", padding: "8px" }}
                      >
                        {s.name}
                      </option>
                    ))}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                      <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                    </svg>
                  </div>
                </div>

                <label className="block text-sm font-medium text-gray-700 mb-1">Enrollment</label>
                <div className="relative">
                  <select
                    className="w-full border rounded p-2 mb-3 text-gray-800 bg-white appearance-none"
                    value={formState?.enrollment_id || ""}
                    onChange={(e) => handleEnrollmentSelect(Number.parseInt(e.target.value))}
                    required
                    disabled={!selectedSemester}
                    style={{ color: "black" }}
                  >
                    <option value="" style={{ color: "black", backgroundColor: "white" }}>
                      Select enrollment
                    </option>
                    {filteredEnrollments.length > 0 ? (
                      filteredEnrollments.map((enrollment) => (
                        <option
                          key={enrollment.id}
                          value={enrollment.id}
                          style={{ color: "black", backgroundColor: "white", padding: "8px" }}
                        >
                          {enrollment.unit_name}
                        </option>
                      ))
                    ) : (
                      <option disabled style={{ color: "gray", backgroundColor: "white" }}>
                        No units available for this semester
                      </option>
                    )}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                      <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                    </svg>
                  </div>
                </div>
                {selectedSemester && (
                  <div className="text-xs text-blue-600 -mt-2 mb-2">
                    {filteredEnrollments.length} unit(s) found for this semester
                  </div>
                )}

                <label className="block text-sm font-medium text-gray-700 mb-1">Unit Name</label>
                <input
                  type="text"
                  value={enrollments.find((e) => e.id === formState?.enrollment_id)?.unit_name || ""}
                  className="w-full border rounded p-2 mb-3 bg-gray-100"
                  readOnly
                />

                <label className="block text-sm font-medium text-gray-700 mb-1">Time Slot</label>
                <div className="relative">
                  <select
                    className="w-full border rounded p-2 mb-3 text-gray-800 bg-white appearance-none"
                    value={selectedTimeSlotId || ""}
                    onChange={(e) => handleTimeSlotSelect(Number.parseInt(e.target.value))}
                    style={{ color: "black" }}
                  >
                    <option value="" style={{ color: "black", backgroundColor: "white" }}>
                      Select time slot
                    </option>
                    {timeSlots.map((slot) => (
                      <option
                        key={slot.id}
                        value={slot.id}
                        style={{ color: "black", backgroundColor: "white", padding: "8px" }}
                      >
                        {formatDate(slot.date)} ({slot.day}) - {formatTimeToHi(slot.start_time)} to{" "}
                        {formatTimeToHi(slot.end_time)}
                      </option>
                    ))}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                      <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                    </svg>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                    <input
                      type="text"
                      placeholder="Day"
                      value={formState?.day || ""}
                      onChange={(e) => setFormState((prev) => ({ ...prev!, day: e.target.value }))}
                      className="w-full border rounded p-2 mb-3 bg-gray-100"
                      required
                      readOnly
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input
                      type="date"
                      value={formState?.date || ""}
                      onChange={(e) => setFormState((prev) => ({ ...prev!, date: e.target.value }))}
                      className="w-full border rounded p-2 mb-3 bg-gray-100"
                      required
                      readOnly
                    />
                  </div>
                </div>

                <label className="block text-sm font-medium text-gray-700 mb-1">No of Students/Unit</label>
                <input
                  type="number"
                  placeholder="No"
                  value={formState?.no || ""}
                  onChange={(e) => setFormState((prev) => ({ ...prev!, no: Number.parseInt(e.target.value) }))}
                  className="w-full border rounded p-2 mb-3 bg-gray-100"
                  readOnly
                />

                <label className="block text-sm font-medium text-gray-700 mb-1">Venue & Capacity</label>
                <div className="relative">
                  <select
                    className="w-full border rounded p-2 mb-3 text-gray-800 bg-white appearance-none"
                    value={selectedClassroom || ""}
                    onChange={(e) => handleClassroomSelect(Number.parseInt(e.target.value))}
                    style={{ color: "black" }}
                  >
                    <option value="">Select venue</option>
                    {classrooms.map((classroom) => {
                      const existingStudents = scheduledStudents[classroom.name] || 0
                      const remainingCapacity = classroom.capacity - existingStudents
                      const isSuitable = formState && formState.no > 0 ? remainingCapacity >= formState.no : true

                      return (
                        <option
                          key={classroom.id}
                          value={classroom.id}
                          style={{
                            color: isSuitable ? "black" : "red",
                            backgroundColor: "white",
                            padding: "8px",
                          }}
                        >
                          {classroom.name} - Capacity: {classroom.capacity} (Available: {remainingCapacity})
                          {!isSuitable && formState?.no > 0 ? " - INSUFFICIENT CAPACITY" : ""}
                        </option>
                      )
                    })}
                  </select>
                </div>

                {remainingCapacity !== null && (
                  <div
                    className={`text-xs mb-2 ${remainingCapacity > 0 ? "text-green-600" : "text-red-600 font-bold"}`}
                  >
                    {scheduledStudents[formState?.venue || ""] > 0 && (
                      <>
                        <span>Currently scheduled: {scheduledStudents[formState?.venue || ""]} students</span>
                        <br />
                      </>
                    )}
                    {remainingCapacity >= 0
                      ? `Remaining space: ${remainingCapacity} student${remainingCapacity !== 1 ? "s" : ""}`
                      : `OVER CAPACITY by ${Math.abs(remainingCapacity)} student${Math.abs(remainingCapacity) !== 1 ? "s" : ""}!`}
                  </div>
                )}
                {formState?.no > 0 &&
                  !classrooms.some((c) => {
                    const existingStudents = scheduledStudents[c.name] || 0
                    return c.capacity - existingStudents >= formState.no
                  }) && (
                    <div className="text-xs text-red-600 -mt-2 mb-2">
                      Warning: No classrooms have sufficient capacity for {formState.no} students at this time slot. You
                      can still select a venue, but it will exceed capacity.
                    </div>
                  )}
                <label className="block text-sm font-medium text-gray-700 mb-1">Lecturer</label>
                <input
                  type="text"
                  placeholder="Chief Invigilator"
                  value={formState?.chief_invigilator || ""}
                  onChange={(e) => setFormState((prev) => ({ ...prev!, chief_invigilator: e.target.value }))}
                  className="w-full border rounded p-2 mb-3"
                />
                <button
                  type="submit"
                  className={`px-4 py-2 rounded ${
                    selectedClassroom &&
                    formState &&
                    formState.no > 0 &&
                    (scheduledStudents[classrooms.find((c) => c.id === selectedClassroom)?.name || ""] || 0) +
                      formState.no >
                      (classrooms.find((c) => c.id === selectedClassroom)?.capacity || 0)
                      ? "bg-gray-400 cursor-not-allowed"
                      : "bg-blue-600 hover:bg-blue-700"
                  } text-white`}
                  disabled={
                    selectedClassroom &&
                    formState &&
                    formState.no > 0 &&
                    (scheduledStudents[classrooms.find((c) => c.id === selectedClassroom)?.name || ""] || 0) +
                      formState.no >
                      (classrooms.find((c) => c.id === selectedClassroom)?.capacity || 0)
                  }
                >
                  {selectedClassroom &&
                  formState &&
                  formState.no > 0 &&
                  (scheduledStudents[classrooms.find((c) => c.id === selectedClassroom)?.name || ""] || 0) +
                    formState.no >
                    (classrooms.find((c) => c.id === selectedClassroom)?.capacity || 0)
                    ? "Capacity Exceeded"
                    : "Submit"}
                </button>
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="ml-2 bg-gray-400 text-white px-4 py-2 rounded"
                >
                  Cancel
                </button>
              </form>
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default ExamTimetable
