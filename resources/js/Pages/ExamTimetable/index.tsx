"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "../../components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"

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
  const { examTimetables, perPage, search, semesters, enrollments, timeSlots, classrooms, can } = usePage().props as unknown as {
    examTimetables: PaginatedExamTimetables
    perPage: number
    search: string
    semesters: Semester[]
    enrollments: Enrollment[]
    timeSlots: TimeSlot[]
    classrooms: Classroom[]
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
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "view" | "">("")
  const [selectedSemester, setSelectedSemester] = useState<number | null>(null)
  const [selectedTimeSlotId, setSelectedTimeSlotId] = useState<number | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [itemsPerPage, setItemsPerPage] = useState(perPage)
  const [searchQuery, setSearchQuery] = useState(search)
  const [scheduledStudents, setScheduledStudents] = useState<{ [key: string]: number }>({})
  const [selectedClassroom, setSelectedClassroom] = useState<number | null>(null)
  const [remainingCapacity, setRemainingCapacity] = useState<number | null>(null)
  const [alertMessage, setAlertMessage] = useState<{ type: "success" | "error"; message: string } | null>(null)
  const [selectedTimetable, setSelectedTimetable] = useState<ExamTimetable | null>(null)

  // Instead of filtering classrooms, we'll enhance them with capacity information
  const [classroomsWithCapacity, setClassroomsWithCapacity] = useState<
    (Classroom & {
      remainingCapacity?: number
      isSuitable?: boolean
    })[]
  >([])

  const filteredEnrollments = selectedSemester
    ? enrollments
        .filter((enrollment) => enrollment.semester_id === selectedSemester)
        .filter((enrollment, index, self) => index === self.findIndex((e) => e.unit_name === enrollment.unit_name))
    : []

  const handleOpenModal = (type: "create" | "edit" | "delete" | "view", timetable: ExamTimetable | null = null) => {
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
    if (type === "view" || type === "delete") {
      setSelectedTimetable(timetable)
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
    setSelectedTimetable(null)
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
      setAlertMessage({ type: "error", message: "Form state is invalid." })
      return
    }

    // Validate enrollment selection
    const selectedEnrollment = enrollments.find((e) => e.id === formState.enrollment_id)
    if (!selectedEnrollment) {
      setAlertMessage({ type: "error", message: "Invalid enrollment selected." })
      return
    }

    // Validate classroom selection
    if (!selectedClassroom) {
      setAlertMessage({ type: "error", message: "Please select a venue." })
      return
    }

    const classroom = classrooms.find((c) => c.id === selectedClassroom)
    if (!classroom) {
      setAlertMessage({ type: "error", message: "Selected classroom is invalid." })
      return
    }

    // Check for time slot selection
    if (!formState.start_time || !formState.end_time || !formState.date) {
      setAlertMessage({ type: "error", message: "Please select a time slot." })
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
      setAlertMessage({ 
        type: "error", 
        message: `ERROR: Cannot schedule this exam. The classroom ${classroom.name} has a capacity of ${classroom.capacity}, but there would be ${existingStudents + formState.no} students scheduled at this time (exceeding capacity by ${formState.no - remainingCapacity} students). Please select a different venue with sufficient capacity.`
      })
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

    // Update URL to match your new route structure
    const url = modalType === "create" ? "/exam-timetables" : `/exam-timetables/${formState.id}`
    const method = modalType === "create" ? "post" : "put"

    router.visit(url, {
      method,
      data: submissionData,
      onSuccess: () => {
        setAlertMessage({ type: "success", message: "Exam timetable saved successfully" })
        setIsModalOpen(false)
      },
      onError: (errors) => {
        // Display the validation errors
        const errorMessages = Object.values(errors).flat().join("\n")
        setAlertMessage({ type: "error", message: `Validation failed: ${errorMessages}` })
      },
    })
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get("/examtimetable", { search: searchQuery, per_page: itemsPerPage }, { preserveState: true })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value, 10)
    setItemsPerPage(newPerPage)
    router.get("/examtimetable", { per_page: newPerPage, search: searchQuery }, { preserveState: true })
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
        setAlertMessage({
          type: "error",
          message: `Warning: This venue doesn't have enough capacity. It can hold ${classroom.capacity} students, but you're trying to schedule ${formState.no} students when there are already ${existingStudents} students scheduled at this time.`,
        })
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

  const handleDelete = () => {
    if (!selectedTimetable) return

    router.delete(`/exam-timetables/${selectedTimetable.id}`, {
      onSuccess: () => {
        setAlertMessage({ type: "success", message: "Exam timetable deleted successfully." })
        handleCloseModal()
      },
      onError: () => {
        setAlertMessage({ type: "error", message: "Failed to delete the exam timetable." })
      },
    })
  }

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!formState) return

    router.put(`/exam-timetables/${formState.id}`, formState, {
      onSuccess: () => {
        setAlertMessage({ type: "success", message: "Exam timetable updated successfully." })
        handleCloseModal()
      },
      onError: () => {
        setAlertMessage({ type: "error", message: "Failed to update the exam timetable." })
      },
    })
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

  const handleProcessTimetable = () => {
    router.post("/process-timetable", {}, {
      onSuccess: () => setAlertMessage({ type: "success", message: "Timetable processed successfully." }),
      onError: () => setAlertMessage({ type: "error", message: "Failed to process timetable." }),
    })
  }

  const handleSolveConflicts = () => {
    router.get("/solve-conflicts", {}, {
      onSuccess: () => setAlertMessage({ type: "success", message: "Conflicts resolved successfully." }),
      onError: () => setAlertMessage({ type: "error", message: "Failed to resolve conflicts." }),
    })
  }

  const handleDownloadTimetable = () => {
    window.open("/download-timetable", "_blank")
  }

  return (
    <AuthenticatedLayout>
      <Head title="Exam Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>
        
        {alertMessage && (
          <Alert className={`mb-4 ${alertMessage.type === "error" ? "bg-red-50 text-red-800 border-red-200" : "bg-green-50 text-green-800 border-green-200"}`}>
            <AlertDescription>{alertMessage.message}</AlertDescription>
          </Alert>
        )}
        
        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create")} className="bg-green-500 hover:bg-green-600">
                + Add Exam
              </Button>
            )}
            
            {can.process && (
              <Button onClick={handleProcessTimetable} className="bg-blue-500 hover:bg-blue-600">
                Process Timetable
              </Button>
            )}
            
            {can.solve_conflicts && (
              <Button onClick={handleSolveConflicts} className="bg-purple-500 hover:bg-purple-600">
                Solve Conflicts
              </Button>
            )}
            
            {can.download && (
              <Button onClick={handleDownloadTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download
              </Button>
            )}
          </div>
          
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
                  <th className="px-3 py-2">Id</th>
                  <th className="px-3 py-2">Day</th>
                  <th className="px-3 py-2">Date</th>
                  <th className="px-3 py-2">Unit</th>
                  <th className="px-3 py-2">Semester</th>               
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
                    <td className="px-3 py-2">{exam.day}</td>
                    <td className="px-3 py-2">{exam.date}</td>
                    <td className="px-3 py-2">{exam.unit_name}</td>
                    <td className="px-3 py-2">{semesters.find((s) => s.id === exam.semester_id)?.name}</td>                   
                    <td className="px-3 py-2">
                      {formatTimeToHi(exam.start_time)} - {formatTimeToHi(exam.end_time)}
                    </td>
                    <td className="px-3 py-2">{exam.venue}</td>
                    <td className="px-3 py-2">{exam.chief_invigilator}</td>
                    <td className="px-3 py-2 flex space-x-2">
                      {/* View Action */}
                      <Button
                        onClick={() => handleOpenModal("view", exam)}
                        className="bg-blue-500 hover:bg-blue-600 text-white"
                      >
                        View
                      </Button>

                      {/* Edit Action */}
                  
                        <Button
                          onClick={() => handleOpenModal("edit", exam)}
                          className="bg-yellow-500 hover:bg-yellow-600 text-white"
                        >
                          Edit
                        </Button>
                    

                      {/* Delete Action */}
                      
                        <Button
                          onClick={() => handleOpenModal("delete", exam)}
                          className="bg-red-500 hover:bg-red-600 text-white"
                        >
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
              {modalType === "view" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Exam Timetable</h2>
                  <p><strong>Day:</strong> {selectedTimetable.day}</p>
                  <p><strong>Date:</strong> {selectedTimetable.date}</p>
                  <p><strong>Unit:</strong> {selectedTimetable.unit_name}</p>
                  <p><strong>Semester:</strong> {semesters.find((s) => s.id === selectedTimetable.semester_id)?.name}</p>
                  <p><strong>Time:</strong> {selectedTimetable.start_time} - {selectedTimetable.end_time}</p>
                  <p><strong>Venue:</strong> {selectedTimetable.venue}</p>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">Close</Button>
                </>
              )}

              {modalType === "edit" && formState && (
                <form onSubmit={handleEditSubmit}>
                  <h2 className="text-xl font-semibold mb-4">Edit Exam Timetable</h2>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                  <input
                    type="text"
                    value={formState.day}
                    onChange={(e) => setFormState({ ...formState, day: e.target.value })}
                    className="w-full border rounded p-2 mb-3"
                  />
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                  <input
                    type="date"
                    value={formState.date}
                    onChange={(e) => setFormState({ ...formState, date: e.target.value })}
                    className="w-full border rounded p-2 mb-3"
                  />
                  <label className="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                  <input
                    type="text"
                    value={formState.venue}
                    onChange={(e) => setFormState({ ...formState, venue: e.target.value })}
                    className="w-full border rounded p-2 mb-3"
                  />
                  <Button type="submit" className="bg-blue-500 hover:bg-blue-600 text-white">Save</Button>
                  <Button onClick={handleCloseModal} className="ml-2 bg-gray-400 text-white">Cancel</Button>
                </form>
              )}

              {modalType === "delete" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Delete Exam Timetable</h2>
                  <p>Are you sure you want to delete this timetable?</p>
                  <div className="mt-4 flex justify-end space-x-2">
                    <Button onClick={handleDelete} className="bg-red-500 hover:bg-red-600 text-white">Delete</Button>
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white">Cancel</Button>
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