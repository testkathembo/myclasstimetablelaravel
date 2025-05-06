"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { AlertCircle } from "lucide-react"

interface ClassTimetable {
  id: number
  day: string
  unit_code: string
  unit_name: string
  venue: string
  location: string
  no: number
  lecturer: string
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

interface ClassTimeSlot {
  id: number
  day: string
  start_time: string
  end_time: string
}

interface Unit {
  id: number
  code: string
  name: string
  semester_id: number
  student_count?: number
  lecturer_code?: string
  lecturer_name?: string
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

interface PaginatedClassTimetables {
  data: ClassTimetable[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

interface FormState {
  id: number
  day: string  
  enrollment_id: number
  venue: string
  location: string
  no: number
  lecturer: string
  start_time: string
  end_time: string
  semester_id: number
  unit_id?: number
  unit_code?: string
  unit_name?: string
  classtimeslot_id?: number
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
const checkTimeOverlap = (classtimetable: ClassTimetable, day: string, startTime: string, endTime: string) => {
  if (classtimetable.day !== day) return false

  return (
    (classtimetable.start_time <= startTime && classtimetable.end_time > startTime) ||
    (classtimetable.start_time < endTime && classtimetable.end_time >= endTime) ||
    (classtimetable.start_time >= startTime && classtimetable.end_time <= endTime)
  )
}

const ClassTimetable = () => {
  const {
    classTimetable = { data: [] },
    perPage = 10,
    search = "",
    semesters = [],
    can = { create: false, edit: false, delete: false, process: false, solve_conflicts: false, download: false },
    enrollments = [],
    classrooms = [],
    classtimeSlots = [],
    units = [],
    lecturers = [],
  } = usePage().props as unknown as {
    classTimetable: PaginatedClassTimetables
    perPage: number
    search: string
    semesters: Semester[]
    enrollments: Enrollment[]
    classrooms: Classroom[]
    classtimeSlots: classTimeSlot[]
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
  const [selectedClassTimetable, setSelectedClassTimetable] = useState<ClassTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [availableClassTimeSlots, setAvailableClassTimeSlots] = useState<classTimeSlot[]>([])
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<Lecturer[]>([])

  // Initialize available timeslots
  useEffect(() => {
    if (classtimeSlots && classtimeSlots.length > 0) {
      setAvailableClassTimeSlots(classtimeSlots)
    }
  }, [classtimeSlots])

  // Debug enrollments data structure
  useEffect(() => {
    console.log("Enrollments data:", enrollments.slice(0, 3))
  }, [enrollments])

  useEffect(() => {
    console.log("Lecturers:", lecturers) // Debug lecturers array
  }, [lecturers])

  // Debugging: Log the current semester ID
  useEffect(() => {
    if (formState?.semester_id) {
      console.log(`Current semester ID: ${formState.semester_id}`);
    }
  }, [formState?.semester_id]);

  // Debugging: Log filtered units when they are updated
  useEffect(() => {
    console.log(`Filtered units count: ${filteredUnits.length}`);
    if (filteredUnits.length > 0) {
      console.log("Filtered units:", filteredUnits);
    }
  }, [filteredUnits]);

  const handleOpenModal = (type: "view" | "edit" | "delete" | "create", classtimetable: ClassTimetable | null) => {
    setModalType(type)
    setSelectedClassTimetable(classtimetable)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])

    if (type === "create") {
      setFormState({
        id: 0,
        day: "",      
        enrollment_id: 0,
        venue: "",
        location: "",
        no: 0,
        lecturer: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
        unit_id: 0,
        unit_code: "",
        unit_name: "",
        classtimeslot_id: 0,
        lecturer_id: null,
        lecturer_name: "",
      })
      // Reset filtered units
      setFilteredUnits([])
    } else if (classtimetable) {
      // For edit/view, find the matching unit
      const unit = units.find((u) => u.code === classtimetable.unit_code)

      // Find matching classtimeslot
      const classtimeSlot = classtimeSlots.find(
        (ts) =>
          ts.day === classtimetable.day &&          
          ts.start_time === classtimetable.start_time &&
          ts.end_time === classtimetable.end_time,
      )

      // Find lecturer for this unit
      const unitEnrollment = enrollments.find(
        (e) => e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
      )

      setFormState({
        ...classtimetable,
        enrollment_id: unitEnrollment?.id || 0,
        unit_id: unit?.id || 0,
        classtimeslot_id: classtimeSlot?.id || 0,
        lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null, // Changed lecturer_id to lecturer_code
        lecturer_name: unitEnrollment?.lecturer_name || "",
      })

      // Filter units for the selected semester
      if (classtimetable.semester_id) {
        const semesterUnits = units.filter((unit) => unit.semester_id === classtimetable.semester_id)
        setFilteredUnits(semesterUnits)

        // Find lecturers for this unit
        if (unit) {
          findLecturersForUnit(unit.id, classtimetable.semester_id)
        }
      }
    }

    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedClassTimetable(null)
    setFormState(null)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this class timetable?")) {
      try {
        await router.delete(`/classtimetable/${id}`, {
          onSuccess: () => alert("Class timetable deleted successfully."),
          onError: (errors) => {
            console.error("Failed to delete class timetable:", errors)
            alert("An error occurred while deleting the class timetable.")
          },
        })
      } catch (error) {
        console.error("Unexpected error:", error)
        alert("An unexpected error occurred.")
      }
    }
  }

  const checkForConflicts = (
    day: string,
    startTime: string,
    endTime: string,
    unitId: number | undefined,
    venueId: string,
  ) => {
    // Skip if we don't have all the necessary data
    if (!day || !startTime || !endTime || !unitId || !venueId) {
      setConflictWarning(null)
      return false
    }

    // Check for conflicts with existing exams
    const conflicts = classTimetable.data.filter((classtimetable) => {
      // Skip the current Class  when editing
      if (selectedClassTimetable && classtimetable.id === selectedClassTimetable.id) return false

      // Check for time overlap on the same day
      const hasTimeOverlap = classtimetable.day === day && checkTimeOverlap(classtimetable, day, startTime, endTime)

      // Check if it's the same unit (unit conflict)
      const isSameUnit = classtimetable.unit_code === units.find((u) => u.id === unitId)?.code

      // Check if it's the same venue (venue conflict)
      const isSameVenue = classtimetable.venue === venueId

      // Return true if there's a time overlap AND (same unit OR same venue)
      return hasTimeOverlap && (isSameUnit || isSameVenue)
    })

    if (conflicts.length > 0) {
      // Create conflict warning message
      const unitConflicts = conflicts.filter(
        (classtimetable) => classtimetable.unit_code === units.find((u) => u.id === unitId)?.code,
      )
      const venueConflicts = conflicts.filter((classtimetable) => classtimetable.venue === venueId)

      let warningMsg = "Scheduling conflicts detected: "

      if (unitConflicts.length > 0) {
        warningMsg += `This unit already has an class scheduled at this time. `
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

  const handleClassTimeSlotChange = (classtimeSlotId: number) => {
    if (!formState) return

    const selectedClassTimeSlot = classtimeSlots.find((ts) => ts.id === Number(classtimeSlotId))
    if (selectedClassTimeSlot) {
      setFormState((prev) => ({
        ...prev!,
        timeslot_id: Number(classtimeSlotId),
        day: selectedClassTimeSlot.day,
        start_time: selectedClassTimeSlot.start_time,
        end_time: selectedClassTimeSlot.end_time,
      }))

      // Check for conflicts if we have enough data
      if (formState.unit_id && formState.venue) {
        checkForConflicts(
          selectedClassTimeSlot.day,
          selectedClassTimeSlot.start_time,
          selectedClassTimeSlot.end_time,
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
      lecturer: "",
    }))

    console.log(`Selected semester ID: ${numericSemesterId}`)
    console.log("All units:", units)

    // Filter units by semester_id
    const semesterUnits = units.filter((unit) => {
      const unitSemesterId = Number(unit.semester_id)
      const matches = unitSemesterId === numericSemesterId
      console.log(`Unit ${unit.code} (ID: ${unit.id}): semester_id=${unitSemesterId}, matches=${matches}`)
      return matches
    })

    console.log(`Found ${semesterUnits.length} units for semester ID: ${numericSemesterId}`, semesterUnits)

    if (semesterUnits.length === 0) {
      console.warn(`No units found for semester ID: ${numericSemesterId}`)
      setErrorMessage(
        `No units found for semester ${semesters.find((s) => s.id === numericSemesterId)?.name || numericSemesterId}. Please check if units are assigned to this semester.`,
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

  // Debugging: Log selected unit details
  const handleUnitChange = (unitId: number) => {
    if (!formState) return;

    const selectedUnit = units.find((u) => u.id === unitId);
    if (selectedUnit) {
        console.log("Selected unit:", selectedUnit);

        const studentCount = selectedUnit.student_count || 0;
        const lecturerName = selectedUnit.lecturer_name || "Unknown";

        console.log(`Student count for unit ${selectedUnit.code}: ${studentCount}`);
        console.log(`Lecturer for unit ${selectedUnit.code}: ${lecturerName}`);

        setFormState((prev) => ({
            ...prev!,
            unit_id: unitId,
            unit_code: selectedUnit.code,
            unit_name: selectedUnit.name,
            no: studentCount,
            lecturer: lecturerName,
        }));
    }
  };

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
    const selectedClassroom = classrooms.find((e) => e.name === venueName)

    if (selectedClassroom) {
      if (studentCount > selectedClassroom.capacity) {
        setCapacityWarning(
          `Warning: Not enough space! The venue ${venueName} has a capacity of ${selectedClassroom.capacity}, ` +
            `but there are ${studentCount} students enrolled (exceeding by ${studentCount - selectedClassroom.capacity} students).`,
        )
      } else {
        setCapacityWarning(null)
      }

      return selectedClassroom
    }

    return null
  }

  const handleVenueChange = (venueName: string) => {
    if (!formState) return

    const selectedClassroom = checkVenueCapacity(venueName, formState.no)

    if (selectedClassroom) {
      setFormState((prev) => ({
        ...prev!,
        venue: venueName,
        location: selectedClassroom.location,
      }))

      // Check for conflicts if we have enough data
      if (formState.day && formState.start_time && formState.end_time && formState.unit_id) {
        checkForConflicts(formState.day, formState.start_time, formState.end_time, formState.unit_id, venueName)
      }
    }
  }

  const handleCreateChange = (field: string, value: string | number) => {
    if (!formState) return

    setFormState((prev) => ({
      ...prev!,
      [field]: value,
    }))

    if (field === "lecturer") {
      // Just update the chief invigilator field
      setFormState((prev) => ({
        ...prev!,
        lecturer: value as string,
      }))
    }
  }

  // Debugging: Log form data before submission
 // In your handleSubmitForm function in class-timetable.tsx, modify it to:

const handleSubmitForm = (data: FormState) => {
  // Format the date field - this is required by your database schema
  const currentDate = new Date().toISOString().split('T')[0]; // Use today's date as fallback
  
  const formattedData = {
      ...data,
      date: currentDate, // Add the date field which is required by your database
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
  };

  console.log("Submitting form data:", formattedData);

  if (data.id === 0) {
      router.post(`/classtimetable`, formattedData, {
          onSuccess: () => {
              console.log("Creation successful");
              handleCloseModal();
              router.reload({
                  only: ["classTimetable"], // Make sure this matches the prop name from your controller
                  onSuccess: () => console.log("Page data refreshed successfully"),
              });
          },
          onError: (errors) => {
              console.error("Creation failed:", errors);
          },
      });
  } else {
      router.put(`/classtimetable/${data.id}`, formattedData, {
          onSuccess: () => {
              console.log("Update successful");
              handleCloseModal();
              router.reload({
                  only: ["classTimetable"], // Make sure this matches the prop name from your controller
                  onSuccess: () => console.log("Page data refreshed successfully"),
              });
          },
          onError: (errors) => {
              console.error("Update failed:", errors);
          },
      });
  }
};

  const handleProcessClassTimetable = () => {
    router.post(
      "/process-classtimetables",
      {},
      {
        onSuccess: () => alert("Class Timetable processed successfully."),
        onError: () => alert("Failed to process class timetable."),
      },
    )
  }

  const handleSolveConflicts = () => {
    router.get(
      "/solve-class-conflicts",
      {},
      {
        onSuccess: () => alert("Conflicts resolved successfully."),
        onError: () => alert("Failed to resolve conflicts."),
      },
    )
  }

  const handleDownloadClassTimetable = () => {
    // Create a temporary anchor element
    const link = document.createElement("a")
    link.href = "/download-classtimetables"

    // Set these attributes to force download behavior
    link.setAttribute("download", "classtimetable.pdf")
    link.setAttribute("target", "_blank")

    // Append to body, click, and remove
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchValue(e.target.value)
  }

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault()
    router.get("/classtimetable", { search: searchValue, perPage: rowsPerPage })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value)
    setRowsPerPage(newPerPage)
    router.get("/classtimetable", { search: searchValue, perPage: newPerPage })
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
      <Head title="Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Class Timetable</h1>

        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                + Add Class
              </Button>
            )}

            {can.process && (
              <Button onClick={handleProcessClassTimetable} className="bg-blue-500 hover:bg-blue-600">
                Process Class Timetable
              </Button>
            )}

            {can.solve_conflicts && (
              <Button onClick={handleSolveConflicts} className="bg-purple-500 hover:bg-purple-600">
                Solve Class Conflicts
              </Button>
            )}

            {can.download && (
              <Button onClick={handleDownloadClassTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download Class Timetable
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue}
              onChange={handleSearchChange}
              placeholder="Search class timetable..."
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

        {classTimetable?.data?.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full mt-6 border text-sm text-left">
                <thead className="bg-gray-100 border-b">
                  <tr>
                    <th className="px-3 py-2">ID</th>
                    <th className="px-3 py-2">Day</th>                   
                    <th className="px-3 py-2">Unit Code</th>
                    <th className="px-3 py-2">Unit Name</th>
                    <th className="px-3 py-2">Semester</th>
                    <th className="px-3 py-2">Classroom</th>
                    <th className="px-3 py-2">Time</th>
                    <th className="px-3 py-2">Lecturer</th>
                    <th className="px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {classTimetable.data.map((classtimetable) => (
                    <tr key={classtimetable.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2">{classtimetable.id}</td>
                      <td className="px-3 py-2">{classtimetable.day}</td>                    
                      <td className="px-3 py-2">{classtimetable.unit_code}</td>
                      <td className="px-3 py-2">{classtimetable.unit_name}</td>
                      <td className="px-3 py-2">{classtimetable.semester_name}</td>
                      <td className="px-3 py-2">{classtimetable.venue}</td>
                      <td className="px-3 py-2">
                        {classtimetable.start_time} - {classtimetable.end_time}
                      </td>
                      <td className="px-3 py-2">{classtimetable.lecturer}</td>
                      <td className="px-3 py-2 flex space-x-2">
                        <Button
                          onClick={() => handleOpenModal("view", classtimetable)}
                          className="bg-blue-500 hover:bg-blue-600 text-white"
                        >
                          View
                        </Button>
                        {can.edit && (
                          <Button
                            onClick={() => handleOpenModal("edit", classtimetable)}
                            className="bg-yellow-500 hover:bg-yellow-600 text-white"
                          >
                            Edit
                          </Button>
                        )}
                        {can.delete && (
                          <Button
                            onClick={() => handleDelete(classtimetable.id)}
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
            {classTimetable.links && classTimetable.links.length > 3 && (
              <div className="flex justify-center mt-4">
                <nav className="flex items-center">
                  {classTimetable.links.map((link, index) => (
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
          <p className="mt-6 text-gray-600">No class timetables available yet.</p>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded shadow-md w-[500px] max-h-[90vh] overflow-y-auto">
              {modalType === "view" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Class Timetable</h2>
                  <div className="space-y-2">
                    <p>
                      <strong>Day:</strong> {selectedClassTimetable.day}
                    </p>
                    <p>
                      <strong>Unit Code:</strong> {selectedClassTimetable.unit_code}
                    </p>
                    <p>
                      <strong>Unit Name:</strong> {selectedClassTimetable.unit_name}
                    </p>
                    <p>
                      <strong>Semester:</strong> {selectedClassTimetable.semester_name}
                    </p>
                    <p>
                      <strong>Time:</strong> {selectedClassTimetable.start_time} - {selectedClassTimetable.end_time}
                    </p>
                    <p>
                      <strong>Venue:</strong> {selectedClassTimetable.venue}
                    </p>
                    <p>
                      <strong>Location:</strong> {selectedClassTimetable.location}
                    </p>
                    <p>
                      <strong>Number of Students:</strong> {selectedClassTimetable.no}
                    </p>
                    <p>
                      <strong>Chief Invigilator:</strong> {selectedClassTimetable.lecturer}
                    </p>
                  </div>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">
                    Close
                  </Button>
                </>
              )}

              {modalType === "edit" && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Edit Class Timetable</h2>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <select
                          value={formState.day}
                          onChange={(e) => handleCreateChange("day", e.target.value)}
                          className="w-full border rounded p-2 mb-3"
                          required
                        >
                          <option value="">Select Day</option>
                          {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"].map((day) => (
                            <option key={day} value={day}>
                              {day}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Class Time Slot</label>
                        <select
                          value={formState.classtimeslot_id || ""}
                          onChange={(e) => handleClassTimeSlotChange(Number(e.target.value))}
                          className="w-full border rounded p-2 mb-3"
                        >
                          <option value="">Select Time Slot</option>
                          {availableClassTimeSlots.map((slot) => (
                            <option key={slot.id} value={slot.id}>
                              {slot.day} - {slot.start_time} to {slot.end_time}
                            </option>
                          ))}
                        </select>
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
                      {classrooms?.map((classroom) => (
                        <option key={classroom.id} value={classroom.name}>
                          {classroom.name} (Capacity: {classroom.capacity})
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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Lecturer</label>
                    <input
                      type="text"
                      value={formState.lecturer}
                      onChange={(e) => handleCreateChange("lecturer", e.target.value)}
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

              {modalType === "delete" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Delete Class Timetable</h2>
                  <p>Are you sure you want to delete this timetable?</p>
                  <div className="mt-4 flex justify-end space-x-2">
                    <Button
                      onClick={() => handleDelete(selectedClassTimetable.id)}
                      className="bg-red-500 hover:bg-red-600 text-white"
                    >
                      Delete
                    </Button>
                    <Button
                      type="button"
                      onClick={handleCloseModal}
                      className="bg-gray-400 text-white"
                    >
                      Cancel
                    </Button>
                  </div>
                </>
              )}

              {modalType === "create" && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Create Class Timetable</h2>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <select
                          value={formState.day}
                          onChange={(e) => handleCreateChange("day", e.target.value)}
                          className="w-full border rounded p-2 mb-3"
                        >
                          <option value="">Select Day</option>
                          {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"].map((day) => (
                            <option key={day} value={day}>
                              {day}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Class Time Slot</label>
                        <select
                          value={formState.classtimeslot_id || ""}
                          onChange={(e) => handleClassTimeSlotChange(Number(e.target.value))}
                          className="w-full border rounded p-2 mb-3"
                        >
                          <option value="">Select Time Slot</option>
                          {availableClassTimeSlots.map((slot) => (
                            <option key={slot.id} value={slot.id}>
                              {slot.day} - {slot.start_time} to {slot.end_time}
                            </option>
                          ))}
                        </select>
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
                      <option value="">Select Classroom</option>
                      {classrooms?.map((classroom) => (
                        <option key={classroom.id} value={classroom.name}>
                          {classroom.name} (Capacity: {classroom.capacity})
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
                      value={formState.lecturer}
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

export default ClassTimetable
