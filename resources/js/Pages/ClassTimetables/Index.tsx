"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { AlertCircle } from "lucide-react"
import { toast } from "react-hot-toast"
import Pagination from "@/Components/Pagination"
import axios from "axios"

interface ClassTimetable {
  id: number
  semester_id: number
  unit_id: number
  class_id?: number | null
  group_id?: number | null
  day: string
  start_time: string
  end_time: string
  teaching_mode?: string | null
  venue?: string | null
  location?: string | null
  no?: number | null
  lecturer?: string | null
  program_id?: number | null
  school_id?: number | null
  created_at?: string | null
  updated_at?: string | null

  // Optionally, for display (from joins)
  unit_code?: string
  unit_name?: string
  semester_name?: string
  class_name?: string
  group_name?: string
  status?: string // for legacy/fallback
}

interface Enrollment {
  id: number
  student_code: string
  unit_id: number
  semester_id: number
  lecturer_code: string | null
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

interface Class {
  id: number
  name: string
}

interface Group {
  id: number
  name: string
  class_id: number
}

interface Program {
  id: number
  code: string
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

// ✅ Updated FormState interface to allow null values
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
  class_id?: number | null
  group_id?: number | null
  school_id?: number | null
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
const checkTimeOverlap = (classtimetable: ClassTimetable, day: string, startTime: string, endTime: string) => {
  if (classtimetable.day !== day) return false
  return (
    (classtimetable.start_time <= startTime && classtimetable.end_time > startTime) ||
    (classtimetable.start_time < endTime && classtimetable.end_time >= endTime) ||
    (classtimetable.start_time >= startTime && classtimetable.end_time <= endTime)
  )
}

// Helper function to check for lecturer time conflicts
const checkLecturerConflict = (
  classTimetables: PaginatedClassTimetables,
  day: string,
  startTime: string,
  endTime: string,
  lecturer: string,
) => {
  return classTimetables.data.some((classtimetable) => {
    if (classtimetable.day !== day || classtimetable.lecturer !== lecturer) return false
    return (
      (classtimetable.start_time <= startTime && classtimetable.end_time > startTime) ||
      (classtimetable.start_time < endTime && classtimetable.end_time >= endTime) ||
      (classtimetable.start_time >= startTime && classtimetable.end_time <= endTime)
    )
  })
}

// Helper function to check for semester time conflicts
const checkSemesterConflict = (
  classTimetables: PaginatedClassTimetables,
  day: string,
  startTime: string,
  endTime: string,
  semesterId: number,
) => {
  return classTimetables.data.some((classtimetable) => {
    if (classtimetable.day !== day || classtimetable.semester_id !== semesterId) return false
    return (
      (classtimetable.start_time <= startTime && classtimetable.end_time > startTime) ||
      (classtimetable.start_time < endTime && classtimetable.end_time >= endTime) ||
      (classtimetable.start_time >= startTime && classtimetable.end_time <= endTime)
    )
  })
}

const ClassTimetable = () => {
  const [isSubmitting, setIsSubmitting] = useState(false)

  const pageProps = usePage().props as unknown as {
    classTimetables: PaginatedClassTimetables
    perPage: number
    search: string
    semesters: Semester[]
    enrollments: Enrollment[]
    classrooms: Classroom[]
    classtimeSlots: ClassTimeSlot[]
    units: Unit[]
    lecturers: Lecturer[]
    classes: Class[]
    groups: Group[]
    programs: Program[]
    schools: { id: number, code: string, name: string }[] // Add schools here
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      process: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  const {
    classTimetables = { data: [] },
    perPage = 10,
    search = "",
    semesters = [],
    can = { create: false, edit: false, delete: false, process: false, solve_conflicts: false, download: false },
    enrollments = [],
    classrooms = [],
    classtimeSlots = [],
    units = [],
    lecturers = [],
    schools = [],
  } = pageProps

  // Defensive: always use array for programs, classes, groups
  const programs = Array.isArray(pageProps.programs) ? pageProps.programs : []
  const classes = Array.isArray(pageProps.classes) ? pageProps.classes : []
  const groups = Array.isArray(pageProps.groups) ? pageProps.groups : []

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "">("")
  const [selectedClassTimetable, setSelectedClassTimetable] = useState<ClassTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [availableClassTimeSlots, setAvailableClassTimeSlots] = useState<ClassTimeSlot[]>([])
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<Lecturer[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [isAutoGenerateModalOpen, setIsAutoGenerateModalOpen] = useState(false)
  const [autoGenerateFormState, setAutoGenerateFormState] = useState({
    semester_id: "",
    program_id: "",
    class_id: "",
    group_id: "",
  })

  // State for dynamic dropdowns in auto-generate modal
  const [autoPrograms, setAutoProgramsRaw] = useState<Program[]>([])
  const [autoClasses, setAutoClassesRaw] = useState<Class[]>([])
  const [autoGroups, setAutoGroupsRaw] = useState<Group[]>([])
  const [autoLoading, setAutoLoading] = useState(false)

  // Initialize available timeslots
  useEffect(() => {
    if (classtimeSlots && classtimeSlots.length > 0) {
      setAvailableClassTimeSlots(classtimeSlots)
    }
  }, [classtimeSlots])

  // Fetch programs when semester changes
  useEffect(() => {
    if (autoGenerateFormState.semester_id) {
      setAutoLoading(true)
      console.log("Fetching programs for semester:", autoGenerateFormState.semester_id)

      axios
        .get("/api/auto-generate-timetable/programs", {
          params: { semester_id: autoGenerateFormState.semester_id },
        })
        .then((res) => {
          console.log("Programs response:", res.data)
          setAutoProgramsRaw(Array.isArray(res.data) ? res.data : [])
        })
        .catch((error) => {
          console.error("Error fetching programs:", error)
          setAutoProgramsRaw([])
        })
        .finally(() => setAutoLoading(false))

      // Reset dependent fields
      setAutoGenerateFormState((prev) => ({
        ...prev,
        program_id: "",
        class_id: "",
        group_id: "",
      }))
      setAutoClassesRaw([])
      setAutoGroupsRaw([])
    } else {
      setAutoProgramsRaw([])
      setAutoClassesRaw([])
      setAutoGroupsRaw([])
    }
  }, [autoGenerateFormState.semester_id])

  // Fetch classes when program or semester changes
  useEffect(() => {
    if (autoGenerateFormState.semester_id && autoGenerateFormState.program_id) {
      setAutoLoading(true)
      console.log("Fetching classes for:", {
        semester_id: autoGenerateFormState.semester_id,
        program_id: autoGenerateFormState.program_id,
      })

      axios
        .get("/api/auto-generate-timetable/classes", {
          params: {
            semester_id: autoGenerateFormState.semester_id,
            program_id: autoGenerateFormState.program_id,
          },
        })
        .then((res) => {
          console.log("Classes response:", res.data)
          setAutoClassesRaw(Array.isArray(res.data) ? res.data : [])
        })
        .catch((error) => {
          console.error("Error fetching classes:", error)
          setAutoClassesRaw([])
        })
        .finally(() => setAutoLoading(false))

      // Reset dependent fields
      setAutoGenerateFormState((prev) => ({
        ...prev,
        class_id: "",
        group_id: "",
      }))
      setAutoGroupsRaw([])
    } else {
      setAutoClassesRaw([])
      setAutoGroupsRaw([])
    }
  }, [autoGenerateFormState.semester_id, autoGenerateFormState.program_id])

  // Fetch groups when class changes
  useEffect(() => {
    if (autoGenerateFormState.class_id) {
      setAutoLoading(true)
      console.log("Fetching groups for class:", autoGenerateFormState.class_id)

      axios
        .get("/api/auto-generate-timetable/groups", {
          params: { class_id: autoGenerateFormState.class_id },
        })
        .then((res) => {
          console.log("Groups response:", res.data)
          setAutoGroupsRaw(Array.isArray(res.data) ? res.data : [])
        })
        .catch((error) => {
          console.error("Error fetching groups:", error)
          setAutoGroupsRaw([])
        })
        .finally(() => setAutoLoading(false))

      setAutoGenerateFormState((prev) => ({
        ...prev,
        group_id: "",
      }))
    } else {
      setAutoGroupsRaw([])
    }
  }, [autoGenerateFormState.class_id])

  const handleOpenModal = (type: "view" | "edit" | "delete" | "create", classtimetable: ClassTimetable | null) => {
    setModalType(type)
    setSelectedClassTimetable(classtimetable)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
    setFilteredGroups([])

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
        class_id: null,  // ✅ Initialize as null instead of 0
        group_id: null,  // ✅ Initialize as null instead of 0
        school_id: null, // ✅ Initialize as null instead of 0
      })
      setFilteredUnits([])
    } else if (classtimetable) {
      const unit = units.find((u) => u.code === classtimetable.unit_code)
      const classtimeSlot = classtimeSlots.find(
        (ts) =>
          ts.day === classtimetable.day &&
          ts.start_time === classtimetable.start_time &&
          ts.end_time === classtimetable.end_time,
      )
      const unitEnrollment = enrollments.find(
        (e) => e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
      )

      setFormState({
        ...classtimetable,
        enrollment_id: unitEnrollment?.id || 0,
        unit_id: unit?.id || 0,
        classtimeslot_id: classtimeSlot?.id || 0,
        lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null,
        lecturer_name: unitEnrollment?.lecturer_name || "",
        class_id: classtimetable.class_id || null,  // ✅ Use null instead of 0
        group_id: classtimetable.group_id || null,  // ✅ Use null instead of 0
      })

      if (classtimetable.semester_id) {
        const semesterUnits = units.filter((unit) => unit.semester_id === classtimetable.semester_id)
        setFilteredUnits(semesterUnits)
        if (unit) {
          findLecturersForUnit(unit.id, classtimetable.semester_id)
        }
      }

      if (classtimetable.class_id) {
        const filteredGroups = groups.filter((group) => group.class_id === classtimetable.class_id)
        setFilteredGroups(filteredGroups)
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
    setFilteredGroups([])
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this class timetable?")) {
      try {
        await router.delete(`/classtimetable/${id}`, {
          onSuccess: () => toast.success("Class timetable deleted successfully."),
          onError: (errors) => {
            console.error("Failed to delete class timetable:", errors)
            toast.error("An error occurred while deleting the class timetable.")
          },
        })
      } catch (error) {
        console.error("Unexpected error:", error)
        toast.error("An unexpected error occurred.")
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
    if (!day || !startTime || !endTime || !unitId || !venueId) {
      setConflictWarning(null)
      return false
    }

    const conflicts = classTimetables.data.filter((classtimetable) => {
      if (selectedClassTimetable && classtimetable.id === selectedClassTimetable.id) return false
      const hasTimeOverlap = classtimetable.day === day && checkTimeOverlap(classtimetable, day, startTime, endTime)
      const isSameUnit = classtimetable.unit_code === units.find((u) => u.id === unitId)?.code
      const isSameVenue = classtimetable.venue === venueId
      return hasTimeOverlap && (isSameUnit || isSameVenue)
    })

    if (conflicts.length > 0) {
      const unitConflicts = conflicts.filter(
        (classtimetable) => classtimetable.unit_code === units.find((u) => u.id === unitId)?.code,
      )
      const venueConflicts = conflicts.filter((classtimetable) => classtimetable.venue === venueId)

      let warningMsg = "Scheduling conflicts detected: "
      if (unitConflicts.length > 0) {
        warningMsg += `This unit already has a class scheduled at this time. `
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
        classtimeslot_id: Number(classtimeSlotId),
        day: selectedClassTimeSlot.day,
        start_time: selectedClassTimeSlot.start_time,
        end_time: selectedClassTimeSlot.end_time,
      }))

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
    const unitEnrollments = enrollments.filter(
      (e) => e.unit_id === unitId && Number(e.semester_id) === Number(semesterId) && e.lecturer_code,
    )
    const uniqueLecturerCodes = Array.from(new Set(unitEnrollments.map((e) => e.lecturer_code).filter(Boolean)))
    const unitLecturersList = lecturers.filter((l) => uniqueLecturerCodes.includes(l.id.toString()))
    setUnitLecturers(unitLecturersList)
    return unitLecturersList.length > 0 ? unitLecturersList[0] : null
  }

  const handleSemesterChange = (semesterId: number | string) => {
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

    setFormState((prev) => ({
      ...prev!,
      semester_id: numericSemesterId,
      class_id: null,  // ✅ Reset to null instead of 0
      group_id: null,  // ✅ Reset to null instead of 0
      unit_id: 0,
      unit_code: "",
      unit_name: "",
      no: 0,
      lecturer_id: null,
      lecturer_name: "",
      lecturer: "",
    }))

    // Reset dependent dropdowns
    setFilteredGroups([])
    setFilteredUnits([])
    setIsLoading(false)
  }

  // ✅ NEW: Added handleClassChange function
  const handleClassChange = async (classId: number | string) => {
    if (!formState) return

    // Convert string to number, handle empty string properly
    const numericClassId = classId === "" ? null : Number(classId)

    setFormState((prev) => ({
      ...prev!,
      class_id: numericClassId,  // ✅ Store null for empty selection
      group_id: null, // Reset group selection to null
      unit_id: 0,
      unit_code: "",
      unit_name: "",
      no: 0,
      lecturer: "",
    }))

    // Only proceed if valid class selected
    if (numericClassId === null) {
      setFilteredGroups([])
      setFilteredUnits([])
      return
    }

    // Simple fetch of groups for the selected class
    const filteredGroupsForClass = groups.filter((group) => group.class_id === numericClassId)
    setFilteredGroups(filteredGroupsForClass)

    // Fetch ALL units for the selected class
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await axios.get("/api/units/by-class", {
        params: {
          class_id: numericClassId,
          semester_id: formState.semester_id,
        },
      })

      if (response.data && response.data.length > 0) {
        const unitsWithDetails = response.data.map((unit: any) => ({
          ...unit,
          student_count: unit.student_count || 0,
          lecturer_name: unit.lecturer_name || "",
        }))

        setFilteredUnits(unitsWithDetails)
        setErrorMessage(null)
      } else {
        setFilteredUnits([])
        setErrorMessage("No units found for the selected class in this semester.")
      }
    } catch (error: any) {
      console.error("Error fetching units for class:", error.response?.data || error.message)
      setErrorMessage("Failed to fetch units for the selected class. Please try again.")
      setFilteredUnits([])
    } finally {
      setIsLoading(false)
    }
  }

  // ✅ UPDATED: handleGroupChange function to handle null properly
  const handleGroupChange = (groupId: number | string) => {
    if (!formState) return

    // Convert string to number, handle empty string properly
    const numericGroupId = groupId === "" ? null : Number(groupId)

    setFormState((prev) => ({
      ...prev!,
      group_id: numericGroupId,  // ✅ Store null for empty selection
    }))
  }

  const handleUnitChange = (unitId: number) => {
    if (!formState) return

    const selectedUnit = filteredUnits.find((u) => u.id === unitId)
    if (selectedUnit) {
      const studentCount = selectedUnit.student_count || 0
      const lecturerName = selectedUnit.lecturer_name || ""

      setFormState((prev) => ({
        ...prev!,
        unit_id: unitId,
        unit_code: selectedUnit.code,
        unit_name: selectedUnit.name,
        no: studentCount,
        lecturer: lecturerName,
      }))

      // Find lecturers for this unit
      if (formState.semester_id) {
        findLecturersForUnit(unitId, formState.semester_id)
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
        lecturer: selectedLecturer.name,
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
  }

  // ✅ UPDATED: handleSubmitForm with validation and proper null handling
  const handleSubmitForm = (data: FormState) => {
    console.log('Raw form data received:', data);
    console.log('class_id:', data.class_id, 'type:', typeof data.class_id);
    console.log('group_id:', data.group_id, 'type:', typeof data.group_id);
    
    // ✅ Add frontend validation
    if (!data.class_id) {
      toast.error("Please select a class before submitting.")
      return
    }
    
    // Remove undefined/null fields that are not in the DB table
    const formattedData: any = {
      ...data,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
    };

    // Only send fields that exist in the DB table
    // Remove fields like unit_code, unit_name, classtimeslot_id, lecturer_id, lecturer_name, enrollment_id
    delete formattedData.unit_code;
    delete formattedData.unit_name;
    delete formattedData.classtimeslot_id;
    delete formattedData.lecturer_id;
    delete formattedData.lecturer_name;
    delete formattedData.enrollment_id;

    // Remove any undefined fields (especially group_id/class_id if not set)
    Object.keys(formattedData).forEach(
      (key) => (formattedData[key] === undefined ? delete formattedData[key] : undefined)
    );

    // Client-side conflict detection for semesters
    const semesterConflictExists = checkSemesterConflict(
      classTimetables,
      formattedData.day,
      formattedData.start_time,
      formattedData.end_time,
      formattedData.semester_id,
    )

    if (semesterConflictExists) {
      toast.error("Conflict detected: Another class is already scheduled for this semester during this time.")
      return
    }

    // Client-side conflict detection
    const conflictExists = checkLecturerConflict(
      classTimetables,
      formattedData.day,
      formattedData.start_time,
      formattedData.end_time,
      formattedData.lecturer,
    )

    if (conflictExists) {
      toast.error("Conflict detected: The lecturer is already assigned to another class during this time.")
      return
    }

    if (data.id === 0) {
      router.post(`/classtimetable`, formattedData, {
        onSuccess: () => {
          toast.success("Class timetable created successfully.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: (errors: any) => {
          // Show all error messages from Laravel validation or SQL errors
          let msg = "Failed to create class timetable."
          if (errors && typeof errors === "object") {
            if (errors.error) {
              msg = errors.error
            } else {
              const errorMsgs = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" ")
              if (errorMsgs) msg = errorMsgs
            }
          }
          toast.error(msg)
        },
      })
    } else {
      router.put(`/classtimetable/${data.id}`, formattedData, {
        onSuccess: () => {
          toast.success("Class timetable updated successfully.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: (errors: any) => {
          let msg = "Failed to update class timetable."
          if (errors && typeof errors === "object") {
            if (errors.error) {
              msg = errors.error
            } else {
              const errorMsgs = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" ")
              if (errorMsgs) msg = errorMsgs
            }
          }
          toast.error(msg)
        },
      })
    }
  }

  const handleProcessClassTimetable = async () => {
    try {
      // Show a loading toast
      toast.loading("Processing class timetable...")

      // Make the POST request
      const response = await axios.post("/process-classtimetables")

      // Handle success
      toast.dismiss() // Dismiss the loading toast
      toast.success(response.data.message || "Class timetable processed successfully.")
      router.reload({ only: ["classTimetables"] })
    } catch (error: any) {
      // Handle errors
      toast.dismiss() // Dismiss the loading toast

      if (error.response) {
        // Server responded with a status code outside the 2xx range
        console.error("Error response:", error.response.data)
        toast.error(error.response.data.message || "Failed to process class timetable.")
      } else if (error.request) {
        // Request was made but no response received
        console.error("Error request:", error.request)
        toast.error("No response received from the server. Please try again.")
      } else {
        // Something else happened
        console.error("Error:", error.message)
        toast.error("An unexpected error occurred. Please try again.")
      }
    }
  }

  const handleSolveConflicts = () => {
    toast.promise(router.get("/solve-class-conflicts", {}), {
      loading: "Resolving conflicts...",
      success: "Conflicts resolved successfully.",
      error: "Failed to resolve conflicts.",
    })
  }

  const handleDownloadClassTimetable = () => {
    toast.promise(
      new Promise((resolve) => {
        const link = document.createElement("a")
        link.href = "/download-classtimetables"
        link.setAttribute("download", "classtimetable.pdf")
        link.setAttribute("target", "_blank")
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        resolve(true)
      }),
      {
        loading: "Downloading class timetable...",
        success: "Class timetable downloaded successfully.",
        error: "Failed to download class timetable.",
      },
    )
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

  const handleOpenAutoGenerateModal = () => {
    setIsAutoGenerateModalOpen(true)
    // Reset form state when opening modal
    setAutoGenerateFormState({
      semester_id: "",
      program_id: "",
      class_id: "",
      group_id: "",
    })
    setAutoProgramsRaw([])
    setAutoClassesRaw([])
    setAutoGroupsRaw([])
  }

  const handleCloseAutoGenerateModal = () => {
    setIsAutoGenerateModalOpen(false)
    setAutoGenerateFormState({
      semester_id: "",
      program_id: "",
      class_id: "",
      group_id: "",
    })
    setAutoProgramsRaw([])
    setAutoClassesRaw([])
    setAutoGroupsRaw([])
  }

  const handleAutoGenerateChange = (field: string, value: string) => {
    setAutoGenerateFormState((prev) => ({
      ...prev,
      [field]: value,
    }))
  }

  const handleAutoGenerateSubmit = async () => {
    try {
      // Validate form
      if (!autoGenerateFormState.semester_id || !autoGenerateFormState.program_id || !autoGenerateFormState.class_id) {
        toast.error("Please fill in all required fields")
        return
      }

      // Show loading toast
      const loadingToast = toast.loading("Generating timetable...")

      // Make the request using axios for better error handling
      const response = await axios.post("/auto-generate-timetable", autoGenerateFormState)

      // Dismiss loading toast
      toast.dismiss(loadingToast)

      if (response.data.success) {
        toast.success(response.data.message || "Timetable generated successfully.")
        handleCloseAutoGenerateModal()
        router.reload({ only: ["classTimetables"] })
      } else {
        toast.error(response.data.message || "Failed to generate timetable.")
      }
    } catch (error: any) {
      // Dismiss loading toast
      toast.dismiss()

      console.error("Auto-generate error:", error)

      if (error.response?.data?.message) {
        toast.error(error.response.data.message)
      } else if (error.response?.data?.errors) {
        const errorMessages = Object.values(error.response.data.errors).flat()
        toast.error(Array.isArray(errorMessages) ? errorMessages.join(", ") : "Validation failed")
      } else {
        toast.error("An unexpected error occurred while generating the timetable.")
      }
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Class Timetable</h1>

        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                + Add
              </Button>
            )}

            {can.process && (
              <Button onClick={handleProcessClassTimetable} className="bg-blue-500 hover:bg-blue-600">
                Process
              </Button>
            )}

            {can.solve_conflicts && (
              <Button onClick={handleSolveConflicts} className="bg-purple-500 hover:bg-purple-600">
                Solve Conflicts
              </Button>
            )}

            {can.download && (
              <Button onClick={handleDownloadClassTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download
              </Button>
            )}

            {can.create && (
              <Button onClick={handleOpenAutoGenerateModal} className="bg-orange-500 hover:bg-orange-600">
                Auto-Generate
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

        {classTimetables?.data?.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full mt-6 border text-sm text-left">
                <thead className="bg-gray-100 border-b">
                  <tr>
                    <th className="px-3 py-2">ID</th>
                    <th className="px-3 py-2">Semester</th>
                    <th className="px-3 py-2">Unit</th>
                    <th className="px-3 py-2">Class</th>
                    <th className="px-3 py-2">Group</th>
                    <th className="px-3 py-2">Day</th>
                    <th className="px-3 py-2">Start Time</th>
                    <th className="px-3 py-2">End Time</th>
                    <th className="px-3 py-2">Mode</th>
                    <th className="px-3 py-2">Venue</th>                   
                    <th className="px-3 py-2">No</th>
                    <th className="px-3 py-2">Lecturer</th>
                    <th className="px-3 py-2">Program</th>
                    <th className="px-3 py-2">School</th>                    
                    <th className="px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {classTimetables.data.map((ct) => (
                    <tr key={ct.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2">{ct.id}</td>
                      <td className="px-3 py-2">{ct.semester_name || ct.semester_id}</td>
                      <td className="px-3 py-2">{ct.unit_code || ct.unit_id}</td>
                      <td className="px-3 py-2">{ct.class_name || ct.class_id || "-"}</td>
                      <td className="px-3 py-2">{ct.group_name || ct.group_id || "-"}</td>
                      <td className="px-3 py-2">{ct.day}</td>
                      <td className="px-3 py-2">{ct.start_time}</td>
                      <td className="px-3 py-2">{ct.end_time}</td>
                      <td className="px-3 py-2">{ct.teaching_mode}</td>
                      <td className="px-3 py-2">{ct.venue}</td>                     
                      <td className="px-3 py-2">{ct.no}</td>
                      <td className="px-3 py-2">{ct.lecturer}</td>
                      <td className="px-3 py-2">{ct.program_code}</td>
                      <td className="px-3 py-2">{ct.school_code}</td>
                      
                      <td className="px-3 py-2 flex space-x-2">
                        <Button
                          onClick={() => handleOpenModal("view", ct)}
                          className="bg-blue-500 hover:bg-blue-600 text-white"
                        >
                          View
                        </Button>
                        {can.edit && (
                          <Button
                            onClick={() => handleOpenModal("edit", ct)}
                            className="bg-yellow-500 hover:bg-yellow-600 text-white"
                          >
                            Edit
                          </Button>
                        )}
                        {can.delete && (
                          <Button
                            onClick={() => handleDelete(ct.id)}
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

            <Pagination links={classTimetables?.links || []} />
          </>
        ) : (
          <p className="mt-6 text-gray-600">No class timetables available yet.</p>
        )}

        {/* Auto-Generate Modal */}
        {isAutoGenerateModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded shadow-md w-[500px] max-h-[90vh] overflow-y-auto">
              <h2 className="text-xl font-semibold mb-4">Auto-Generate Timetable</h2>
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  handleAutoGenerateSubmit()
                }}
              >
                <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
                <select
                  value={autoGenerateFormState.semester_id}
                  onChange={(e) => handleAutoGenerateChange("semester_id", e.target.value)}
                  className="w-full border rounded p-2 mb-3"
                  required
                >
                  <option value="">Select Semester</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
                    </option>
                  ))}
                </select>

                <label className="block text-sm font-medium text-gray-700 mb-1">Program *</label>
                <select
                  value={autoGenerateFormState.program_id}
                  onChange={(e) => handleAutoGenerateChange("program_id", e.target.value)}
                  className="w-full border rounded p-2 mb-3"
                  required
                  disabled={!autoGenerateFormState.semester_id || autoLoading}
                >
                  <option value="">
                    {autoGenerateFormState.semester_id ? "Select Program" : "Select Semester First"}
                  </option>
                  {autoPrograms.map((program) => (
                    <option key={program.id} value={program.id}>
                      {program.code} - {program.name}
                    </option>
                  ))}
                </select>

                <label className="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                <select
                  value={autoGenerateFormState.class_id}
                  onChange={(e) => handleAutoGenerateChange("class_id", e.target.value)}
                  className="w-full border rounded p-2 mb-3"
                  required
                  disabled={!autoGenerateFormState.program_id || autoLoading}
                >
                  <option value="">{autoGenerateFormState.program_id ? "Select Class" : "Select Program First"}</option>
                  {autoClasses.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name}
                    </option>
                  ))}
                </select>

                <label className="block text-sm font-medium text-gray-700 mb-1">Group (Optional)</label>
                <select
                  value={autoGenerateFormState.group_id}
                  onChange={(e) => handleAutoGenerateChange("group_id", e.target.value)}
                  className="w-full border rounded p-2 mb-3"
                  disabled={!autoGenerateFormState.class_id || autoLoading}
                >
                  <option value="">All Groups</option>
                  {autoGroups.map((group) => (
                    <option key={group.id} value={group.id}>
                      {group.name}
                    </option>
                  ))}
                </select>

                {autoLoading && <div className="text-blue-600 text-sm mb-2">Loading...</div>}

                <div className="mt-4 flex justify-end space-x-2">
                  <Button type="submit" className="bg-blue-500 hover:bg-blue-600 text-white">
                    Generate
                  </Button>
                  <Button type="button" onClick={handleCloseAutoGenerateModal} className="bg-gray-400 text-white">
                    Cancel
                  </Button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Regular Modal for Create/Edit/View/Delete */}
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
                      <strong>Mode of Teaching:</strong> {selectedClassTimetable.teaching_mode}
                    </p>
                    <p>
                      <strong>Lecturer:</strong> {selectedClassTimetable.lecturer}
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
                    {modalType === "create" ? "Create Class Timetable" : "Edit Class Timetable"}
                  </h2>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <input
                          type="text"
                          value={formState.day}
                          onChange={(e) => handleCreateChange("day", e.target.value)}
                          className="w-full border rounded p-2 mb-3 bg-gray-50"
                          readOnly
                          placeholder="Day will be populated based on time slot"
                        />
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

                    {/* --- SCHOOL SELECT DROPDOWN --- */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">School</label>
                    <select
                      value={formState.school_id || ""}
                      onChange={e => handleCreateChange("school_id", e.target.value ? Number(e.target.value) : null)}
                      className="w-full border rounded p-2 mb-3"
                      required
                    >
                      <option value="">Select School</option>
                      {schools.map((school) => (
                        <option key={school.id} value={school.id}>
                          {school.code ? `${school.code} - ` : ""}{school.name}
                        </option>
                      ))}
                    </select>


                    <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
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

                    {/* --- CLASS SELECT DROPDOWN --- */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select
                      value={formState.class_id || ""}
                      onChange={(e) => handleClassChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                      required
                      disabled={!formState.semester_id}
                    >
                      <option value="">Select Class</option>
                      {classes.map((classItem) => (
                        <option key={classItem.id} value={classItem.id}>
                          {classItem.name}
                        </option>
                      ))}
                    </select>

                    {/* --- GROUP SELECT DROPDOWN --- */}
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Group{" "}
                      <span className="text-sm text-gray-500">
                        (Optional - Select which group this timetable is for)
                      </span>
                    </label>
                    <select
                      value={formState.group_id || ""}
                      onChange={(e) => handleGroupChange(Number(e.target.value))}
                      className="w-full border rounded p-2 mb-3"
                      disabled={!formState.class_id}
                    >
                      <option value="">No specific group (applies to all groups)</option>
                      {filteredGroups.map((group) => (
                        <option key={group.id} value={group.id}>
                          {group.name}
                        </option>
                      ))}
                    </select>

                    {/* Update the info section */}
                    {formState.class_id && (
                      <div className="mb-3 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
                        <p className="text-blue-700">
                          {formState.group_id
                            ? `This timetable will be assigned to ${classes.find((c) => c.id === formState.class_id)?.name} - Group ${filteredGroups.find((g) => g.id === formState.group_id)?.name}`
                            : `This timetable will apply to all groups in ${classes.find((c) => c.id === formState.class_id)?.name}`}
                        </p>
                      </div>
                    )}

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
                      disabled={!formState.class_id || isLoading}
                      required
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
                          {formState.class_id ? "No units available for this class" : "Please select a class first"}
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
                      // Remove required to allow random assignment
                    >
                      <option value="">Random Venue (auto-assign)</option>
                      {classrooms?.map((classroom) => (
                        <option key={classroom.id} value={classroom.name}>
                          {classroom.name} (Capacity: {classroom.capacity})
                        </option>
                      )) || null}
                    </select>
                    <span className="text-xs text-gray-500 block mb-2">
                      Leave blank to assign a random available venue with enough capacity.
                    </span>

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

                    <label className="block text-sm font-medium text-gray-700 mb-1">Lecturer Name</label>
                    <input
                      type="text"
                      value={formState.lecturer}
                      onChange={(e) => handleCreateChange("lecturer", e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                      placeholder="Enter lecturer name"
                      required
                    />

                    
                    <div className="mt-4 flex justify-end space-x-2">
                      <Button
                        type="submit"
                        disabled={isSubmitting}
                        className="bg-blue-500 hover:bg-blue-600 text-white"
                      >
                        {isSubmitting ? "Submitting..." : "Save"}
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
                    <Button type="button" onClick={handleCloseModal} className="bg-gray-400 text-white">
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

export default ClassTimetable
