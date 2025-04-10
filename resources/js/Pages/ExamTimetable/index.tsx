"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface ExamTimetable {
  id: number
  day: string
  date: string
  unit_id: number
  unit_name: string
  group: string
  venue: string
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
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
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
  const [suitableClassrooms, setSuitableClassrooms] = useState<Classroom[]>([])
  const [selectedClassroom, setSelectedClassroom] = useState<number | null>(null)

  // Filter enrollments by semester and remove duplicates by unit_name
  const filteredEnrollments = selectedSemester
    ? enrollments
        .filter((enrollment) => enrollment.semester_id === selectedSemester)
        .filter(
          (enrollment, index, self) => index === self.findIndex((e) => e.unit_name === enrollment.unit_name), // Remove duplicates by unit_name
        )
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
        no: 0,
        chief_invigilator: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
      })
    } else if (timetable) {
      setFormState({
        id: timetable.id,
        day: timetable.day,
        date: timetable.date,
        enrollment_id: timetable.unit_id,
        group: timetable.group,
        venue: timetable.venue,
        no: timetable.no,
        chief_invigilator: timetable.chief_invigilator,
        start_time: timetable.start_time,
        end_time: timetable.end_time,
        semester_id: timetable.semester_id,
      })
      setSelectedSemester(timetable.semester_id)
    }
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setFormState(null)
    setSelectedSemester(null)
    setSelectedTimeSlotId(null)
    setSuitableClassrooms([])
    setSelectedClassroom(null)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    const url = modalType === "create" ? "/exam-timetables" : `/exam-timetables/${formState?.id}`
    const method = modalType === "create" ? router.post : router.put
    method(url, formState, {
      onSuccess: () => {
        alert(`Exam timetable ${modalType === "create" ? "created" : "updated"} successfully!`)
        handleCloseModal()
      },
      onError: (errors) => {
        console.error("Error saving exam timetable:", errors)
      },
    })
  }

  const handleTimeSlotSelect = (slotId: number) => {
    const slot = timeSlots.find((t) => t.id === slotId)
    if (slot) {
      setFormState((prev) => ({
        ...prev!,
        start_time: slot.start_time,
        end_time: slot.end_time,
        day: slot.day,
        date: slot.date,
      }))
      setSelectedTimeSlotId(slotId)
    }
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get("/exam-timetables", { search: searchQuery, per_page: itemsPerPage }, { preserveState: true })
  }

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true })
    }
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value, 10)
    setItemsPerPage(newPerPage)
    router.get("/exam-timetables", { per_page: newPerPage, search: searchQuery }, { preserveState: true })
  }

  // Format date for display
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
          <button
            onClick={() => handleOpenModal("create")}
            className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
          >
            + Add Exam
          </button>
          <form onSubmit={handleSearch} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search exam timetable..."
              className="border rounded p-2 w-64"
            />
            <button type="submit" className="bg-blue-500 text-white px-4 py-2 rounded">
              Search
            </button>
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

        {/* TABLE omitted for brevity */}

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
                    onChange={(e) => {
                      const enrollmentId = Number.parseInt(e.target.value)
                      const selectedEnrollment = enrollments.find((e) => e.id === enrollmentId)

                      // Update form state with enrollment ID and student count
                      setFormState((prev) => ({
                        ...prev!,
                        enrollment_id: enrollmentId,
                        no: selectedEnrollment ? selectedEnrollment.student_count : 0,
                      }))

                      // Find suitable classrooms based on student count
                      if (selectedEnrollment) {
                        const studentCount = selectedEnrollment.student_count
                        const suitable = classrooms
                          .filter((c) => c.capacity >= studentCount)
                          .sort((a, b) => a.capacity - b.capacity) // Sort by capacity (smallest first)
                        setSuitableClassrooms(suitable)
                      } else {
                        setSuitableClassrooms([])
                      }

                      // Reset selected classroom
                      setSelectedClassroom(null)
                    }}
                    required
                    disabled={!selectedSemester} // Disable if no semester is selected
                    style={{ color: "black" }} // Inline style to force text color
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
                        {formatDate(slot.date)} ({slot.day}) - {slot.start_time} to {slot.end_time}
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
                <input
                  type="text"
                  placeholder="Venue"
                  value={formState?.venue || ""}
                  onChange={(e) => setFormState((prev) => ({ ...prev!, venue: e.target.value }))}
                  className="w-full border rounded p-2 mb-3"
                />
                <input
                  type="number"
                  placeholder="No"
                  value={formState?.no || ""}
                  onChange={(e) => setFormState((prev) => ({ ...prev!, no: Number.parseInt(e.target.value) }))}
                  className="w-full border rounded p-2 mb-3 bg-gray-100"
                  readOnly
                />
                <label className="block text-sm font-medium text-gray-700 mb-1">Classroom</label>
                <div className="relative">
                  <select
                    className="w-full border rounded p-2 mb-3 text-gray-800 bg-white appearance-none"
                    value={selectedClassroom || ""}
                    onChange={(e) => {
                      const classroomId = Number.parseInt(e.target.value)
                      setSelectedClassroom(classroomId)
                      const classroom = classrooms.find((c) => c.id === classroomId)
                      if (classroom) {
                        setFormState((prev) => ({
                          ...prev!,
                          venue: `${classroom.name} (${classroom.location})`,
                        }))
                      }
                    }}
                    disabled={suitableClassrooms.length === 0}
                    style={{ color: "black" }}
                  >
                    <option value="" style={{ color: "black", backgroundColor: "white" }}>
                      Select classroom
                    </option>
                    {suitableClassrooms.map((classroom) => (
                      <option
                        key={classroom.id}
                        value={classroom.id}
                        style={{ color: "black", backgroundColor: "white", padding: "8px" }}
                      >
                        {classroom.name} - Capacity: {classroom.capacity} ({classroom.location})
                      </option>
                    ))}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                      <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                    </svg>
                  </div>
                </div>
                {suitableClassrooms.length > 0 && (
                  <div className="text-xs text-green-600 -mt-2 mb-2">
                    {suitableClassrooms.length} suitable classroom(s) found
                  </div>
                )}
                {suitableClassrooms.length === 0 && formState?.no > 0 && (
                  <div className="text-xs text-red-600 -mt-2 mb-2">No classrooms with enough capacity found</div>
                )}
                <input
                  type="text"
                  placeholder="Chief Invigilator"
                  value={formState?.chief_invigilator || ""}
                  onChange={(e) => setFormState((prev) => ({ ...prev!, chief_invigilator: e.target.value }))}
                  className="w-full border rounded p-2 mb-3"
                />
                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">
                  Submit
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
