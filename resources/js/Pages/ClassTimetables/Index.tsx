"use client"

import type React from "react"
import { useState, useEffect, useCallback, useMemo, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  AlertCircle,
  CheckCircle,
  XCircle,
  Clock,
  Users,
  MapPin,
  Calendar,
  Zap,
  Eye,
  Edit,
  Trash2,
  Plus,
  Download,
  Search,
} from "lucide-react"
import { toast } from "react-hot-toast"
import Pagination from "@/Components/Pagination"
import axios from "axios"

// Keep all existing interfaces...
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
  unit_code?: string
  unit_name?: string
  semester_name?: string
  class_name?: string
  group_name?: string
  status?: string
  credit_hours?: number
}

interface PaginatedClassTimetables {
  data: ClassTimetable[]
  links: any[]
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
  teaching_mode?: string | null
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

interface SchedulingConstraints {
  maxPhysicalPerDay: number
  maxOnlinePerDay: number
  minHoursPerDay: number
  maxHoursPerDay: number
  requireMixedMode: boolean
  avoidConsecutiveSlots: boolean
}

// ✅ FIXED: Memoized helper functions to prevent re-creation
const formatTimeToHi = (time: string) => {
  if (!time) return ""
  return time.slice(0, 5)
}

const timeToMinutes = (time: string) => {
  if (!time) return 0
  const [hours, minutes] = time.split(":").map(Number)
  return hours * 60 + minutes
}

const calculateDuration = (startTime: string, endTime: string): number => {
  if (!startTime || !endTime) return 0
  const startMinutes = timeToMinutes(startTime)
  const endMinutes = timeToMinutes(endTime)
  return (endMinutes - startMinutes) / 60
}

// ✅ NEW: Duration-based teaching mode determination
const getTeachingModeFromDuration = (startTime: string, endTime: string): string => {
  const duration = calculateDuration(startTime, endTime)
  return duration >= 2 ? "physical" : "online"
}

// ✅ NEW: Auto venue assignment based on teaching mode
const getVenueForTeachingMode = (teachingMode: string, classrooms: any[], studentCount = 0): string => {
  if (teachingMode === "online") {
    return "Remote"
  }

  // Find suitable physical venue
  const suitableClassroom = classrooms
    .filter((c) => c.capacity >= studentCount)
    .sort((a, b) => a.capacity - b.capacity)[0]

  return suitableClassroom ? suitableClassroom.name : classrooms[0]?.name || "TBD"
}

// ✅ FIXED: Memoized constraint validation function
const validateGroupDailyConstraints = (
  groupId: number | null,
  day: string,
  startTime: string,
  endTime: string,
  teachingMode: string,
  classTimetables: ClassTimetable[],
  constraints: SchedulingConstraints,
  excludeId?: number,
) => {
  if (!groupId || !day || !startTime || !endTime || !teachingMode) {
    return { isValid: true, message: "", warnings: [] }
  }

  const groupDaySlots = classTimetables.filter((ct) => ct.group_id === groupId && ct.day === day && ct.id !== excludeId)

  const physicalCount = groupDaySlots.filter((ct) => ct.teaching_mode === "physical").length
  const onlineCount = groupDaySlots.filter((ct) => ct.teaching_mode === "online").length

  const totalHoursAssigned = groupDaySlots.reduce((total, ct) => {
    return total + calculateDuration(ct.start_time, ct.end_time)
  }, 0)

  const newSlotHours = calculateDuration(startTime, endTime)
  const totalHours = totalHoursAssigned + newSlotHours

  const errors: string[] = []
  const warnings: string[] = []

  // Hard constraints
  if (teachingMode === "physical" && physicalCount >= constraints.maxPhysicalPerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxPhysicalPerDay} physical classes per day. Current: ${physicalCount}`,
    )
  }

  if (teachingMode === "online" && onlineCount >= constraints.maxOnlinePerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxOnlinePerDay} online classes per day. Current: ${onlineCount}`,
    )
  }

  if (totalHours > constraints.maxHoursPerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxHoursPerDay} hours per day. Total would be ${totalHours.toFixed(1)} hours`,
    )
  }

  return {
    isValid: errors.length === 0,
    message: errors.join("; "),
    warnings: warnings,
    stats: {
      physicalCount: physicalCount + (teachingMode === "physical" ? 1 : 0),
      onlineCount: onlineCount + (teachingMode === "online" ? 1 : 0),
      totalHours: totalHours,
    },
  }
}

const EnhancedClassTimetable = () => {
  const [isSubmitting, setIsSubmitting] = useState(false)

  const pageProps = usePage().props as unknown as {
    classTimetables: PaginatedClassTimetables
    perPage: number
    search: string
    semesters: any[]
    enrollments: any[]
    classrooms: any[]
    classtimeSlots: any[]
    units: any[]
    lecturers: any[]
    classes: any[]
    groups: any[]
    programs: any[]
    schools: { id: number; code: string; name: string }[]
    constraints?: SchedulingConstraints
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  // ✅ FIXED: Destructure with proper defaults and memoization
  const {
    classTimetables = { data: [], links: [], total: 0, per_page: 10, current_page: 1 },
    perPage = 10,
    search = "",
    semesters = [],
    can = { create: false, edit: false, delete: false, download: false, solve_conflicts: false },
    enrollments = [],
    classrooms = [],
    classtimeSlots = [],
    units = [],
    lecturers = [],
    schools = [],
  } = pageProps

  // ✅ FIXED: Memoized derived data to prevent re-calculations
  const programs = useMemo(() => (Array.isArray(pageProps.programs) ? pageProps.programs : []), [pageProps.programs])
  const classes = useMemo(() => (Array.isArray(pageProps.classes) ? pageProps.classes : []), [pageProps.classes])
  const groups = useMemo(() => (Array.isArray(pageProps.groups) ? pageProps.groups : []), [pageProps.groups])

  const constraints = useMemo(
    () =>
      pageProps.constraints || {
        maxPhysicalPerDay: 2,
        maxOnlinePerDay: 2,
        minHoursPerDay: 2,
        maxHoursPerDay: 5,
        requireMixedMode: true,
        avoidConsecutiveSlots: true,
      },
    [pageProps.constraints],
  )

  // ✅ FIXED: State management with proper initialization
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "conflicts" | "csp_solver" | "">(
    "",
  )
  const [selectedClassTimetable, setSelectedClassTimetable] = useState<ClassTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)
  const [filteredUnits, setFilteredUnits] = useState<any[]>([])
  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<any[]>([])
  const [filteredGroups, setFilteredGroups] = useState<any[]>([])
  const [detectedConflicts, setDetectedConflicts] = useState<any[]>([])
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [showConflictAnalysis, setShowConflictAnalysis] = useState(false)

  // ✅ FIXED: Memoized organized timetables to prevent re-calculation
  const organizedTimetables = useMemo(() => {
    const organized: { [day: string]: ClassTimetable[] } = {}

    classTimetables.data.forEach((timetable) => {
      if (!organized[timetable.day]) {
        organized[timetable.day] = []
      }
      organized[timetable.day].push(timetable)
    })

    // Sort timetables within each day by start time
    Object.keys(organized).forEach((day) => {
      organized[day].sort((a, b) => {
        const timeA = timeToMinutes(a.start_time)
        const timeB = timeToMinutes(b.start_time)
        return timeA - timeB
      })
    })

    return organized
  }, [classTimetables.data])

  // ✅ FIXED: Memoized conflict detection to prevent infinite loops
  const detectScheduleConflicts = useCallback((timetableData: ClassTimetable[]) => {
    const conflicts: any[] = []
    // Simplified conflict detection to prevent performance issues
    const lecturerSlots: { [key: string]: ClassTimetable[] } = {}

    timetableData.forEach((ct) => {
      if (!ct.lecturer) return
      const key = `${ct.lecturer}_${ct.day}`
      if (!lecturerSlots[key]) {
        lecturerSlots[key] = []
      }
      lecturerSlots[key].push(ct)
    })

    Object.entries(lecturerSlots).forEach(([key, slots]) => {
      if (slots.length > 1) {
        const [lecturer, day] = key.split("_")
        for (let i = 0; i < slots.length; i++) {
          for (let j = i + 1; j < slots.length; j++) {
            const start1 = timeToMinutes(slots[i].start_time)
            const end1 = timeToMinutes(slots[j].end_time)
            const start2 = timeToMinutes(slots[j].start_time)
            const end2 = timeToMinutes(slots[j].end_time)

            if (start1 < end2 && start2 < end1) {
              conflicts.push({
                type: "lecturer_conflict",
                severity: "high",
                description: `${lecturer} has overlapping classes on ${day}`,
                affectedSessions: [slots[i], slots[j]],
                lecturer,
                day,
              })
            }
          }
        }
      }
    })

    return conflicts
  }, [])

  // ✅ FIXED: useEffect with proper dependencies and cleanup
  useEffect(() => {
    if (classTimetables.data.length > 0) {
      const conflicts = detectScheduleConflicts(classTimetables.data)
      setDetectedConflicts(conflicts)
    } else {
      setDetectedConflicts([])
    }
  }, [classTimetables.data, detectScheduleConflicts])

  // ✅ FIXED: Memoized form validation to prevent re-creation
  const validateFormWithConstraints = useCallback(
    (data: FormState) => {
      if (!data.group_id || !data.day || !data.start_time || !data.end_time || !data.teaching_mode) {
        return { isValid: true, message: "", warnings: [] }
      }

      return validateGroupDailyConstraints(
        data.group_id,
        data.day,
        data.start_time,
        data.end_time,
        data.teaching_mode,
        classTimetables.data,
        constraints,
        data.id !== 0 ? data.id : undefined,
      )
    },
    [classTimetables.data, constraints],
  )

  // ✅ FIXED: useEffect for form validation with stable dependencies
  useEffect(() => {
    if (
      formState &&
      formState.group_id &&
      formState.day &&
      formState.start_time &&
      formState.end_time &&
      formState.teaching_mode
    ) {
      const validation = validateFormWithConstraints(formState)

      if (!validation.isValid) {
        setConflictWarning(validation.message)
      } else if (validation.warnings.length > 0) {
        setConflictWarning(validation.warnings.join("; "))
      } else {
        setConflictWarning(null)
      }
    } else {
      setConflictWarning(null)
    }
  }, [formState, validateFormWithConstraints])

  // ✅ FIXED: Memoized handlers to prevent re-creation
  const handleOpenModal = useCallback(
    (
      type: "view" | "edit" | "delete" | "create" | "conflicts" | "csp_solver",
      classtimetable: ClassTimetable | null,
    ) => {
      setModalType(type)
      setSelectedClassTimetable(classtimetable)
      setCapacityWarning(null)
      setConflictWarning(null)
      setErrorMessage(null)
      setUnitLecturers([])
      setFilteredGroups([])

      if (type === "conflicts") {
        setShowConflictAnalysis(true)
        setIsModalOpen(true)
        return
      }

      if (type === "csp_solver") {
        setIsModalOpen(true)
        return
      }

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
          teaching_mode: "physical",
          semester_id: 0,
          unit_id: 0,
          unit_code: "",
          unit_name: "",
          classtimeslot_id: 0,
          lecturer_id: null,
          lecturer_name: "",
          class_id: null,
          group_id: null,
          school_id: null,
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
          (e) =>
            e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
        )

        setFormState({
          ...classtimetable,
          enrollment_id: unitEnrollment?.id || 0,
          unit_id: unit?.id || 0,
          classtimeslot_id: classtimeSlot?.id || 0,
          lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null,
          lecturer_name: unitEnrollment?.lecturer_name || "",
          class_id: classtimetable.class_id || null,
          group_id: classtimetable.group_id || null,
          teaching_mode: classtimetable.teaching_mode || "physical",
        })

        if (classtimetable.semester_id) {
          const semesterUnits = units.filter((unit) => unit.semester_id === classtimetable.semester_id)
          setFilteredUnits(semesterUnits)
        }

        if (classtimetable.class_id) {
          const filteredGroupsForClass = groups.filter((group) => group.class_id === classtimetable.class_id)
          setFilteredGroups(filteredGroupsForClass)
        }
      }

      setIsModalOpen(true)
    },
    [units, classtimeSlots, enrollments, groups],
  )

  const handleCloseModal = useCallback(() => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedClassTimetable(null)
    setFormState(null)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
    setFilteredGroups([])
    setShowConflictAnalysis(false)
  }, [])

  // ✅ ENHANCED: Time slot change handler with proper ID tracking
  const handleClassTimeSlotChange = useCallback(
    (classtimeSlotId: number | string) => {
      if (!formState) return

      if (classtimeSlotId === "Random Time Slot (auto-assign)" || classtimeSlotId === "") {
        setFormState((prev) =>
          prev
            ? {
                ...prev,
                start_time: "",
                end_time: "",
                day: "",
                classtimeslot_id: 0,
                teaching_mode: "physical",
                venue: "",
                location: "",
              }
            : null,
        )
        return
      }

      const selectedClassTimeSlot = classtimeSlots.find((ts) => ts.id === Number(classtimeSlotId))
      if (selectedClassTimeSlot) {
        // ✅ AUTO-ASSIGN: Determine teaching mode based on duration
        const autoTeachingMode = getTeachingModeFromDuration(
          selectedClassTimeSlot.start_time,
          selectedClassTimeSlot.end_time,
        )

        // ✅ AUTO-ASSIGN: Determine venue based on teaching mode
        const autoVenue = getVenueForTeachingMode(autoTeachingMode, classrooms, formState.no)
        const selectedClassroom = classrooms.find((c) => c.name === autoVenue)

        setFormState((prev) => ({
          ...prev!,
          classtimeslot_id: Number(classtimeSlotId),
          day: selectedClassTimeSlot.day, // ✅ CRITICAL: This properly updates the day
          start_time: selectedClassTimeSlot.start_time,
          end_time: selectedClassTimeSlot.end_time,
          teaching_mode: autoTeachingMode,
          venue: autoVenue,
          location: autoVenue === "Remote" ? "Online" : selectedClassroom?.location || "Physical",
        }))

        // Show user what was auto-assigned
        const duration = calculateDuration(selectedClassTimeSlot.start_time, selectedClassTimeSlot.end_time)
        toast.success(
          `Auto-assigned: ${selectedClassTimeSlot.day} ${duration.toFixed(1)}h → ${autoTeachingMode} class → ${autoVenue}`,
          {
            duration: 3000,
          },
        )

        // Check for conflicts if we have the necessary data
        if (formState.unit_id && autoVenue) {
          const validation = validateFormWithConstraints({
            ...formState,
            day: selectedClassTimeSlot.day,
            start_time: selectedClassTimeSlot.start_time,
            end_time: selectedClassTimeSlot.end_time,
            teaching_mode: autoTeachingMode,
          })

          if (!validation.isValid) {
            setConflictWarning(validation.message)
          } else if (validation.warnings.length > 0) {
            setConflictWarning(validation.warnings.join("; "))
          } else {
            setConflictWarning(null)
          }
        }
      }
    },
    [formState, classtimeSlots, classrooms, validateFormWithConstraints],
  )

  // ✅ FIXED: Enhanced form submission handler with better validation and error handling
  const handleSubmitForm = useCallback(
    (data: FormState) => {
      console.log("🚀 Form submission started with data:", data) // ✅ DEBUG

      // ✅ ENHANCED: More comprehensive validation
      if (!data.class_id) {
        toast.error("Please select a class before submitting.")
        return
      }

      if (!data.semester_id) {
        toast.error("Please select a semester before submitting.")
        return
      }

      if (!data.unit_id) {
        toast.error("Please select a unit before submitting.")
        return
      }

      if (!data.day || !data.start_time || !data.end_time) {
        toast.error("Please select a time slot before submitting.")
        return
      }

      if (!data.venue) {
        toast.error("Please select a venue before submitting.")
        return
      }

      if (!data.lecturer) {
        toast.error("Please enter a lecturer name before submitting.")
        return
      }

      // Validate constraints only if we have group_id and teaching_mode
      if (data.group_id && data.teaching_mode) {
        const validation = validateFormWithConstraints(data)
        if (!validation.isValid) {
          toast.error(validation.message)
          return
        }

        if (validation.warnings.length > 0) {
          validation.warnings.forEach((warning) => toast(warning, { icon: "⚠️" }))
        }
      }

      // ✅ SAFETY: Add timeout to prevent infinite loading
      const timeoutId = setTimeout(() => {
        console.warn("⏰ Form submission timeout - resetting loading state")
        setIsSubmitting(false)
        toast.error("Request timed out. Please try again.")
      }, 30000) // 30 second timeout

      // ✅ ENHANCED: Prepare form data with proper formatting
      const formattedData: any = {
        semester_id: Number(data.semester_id),
        class_id: Number(data.class_id),
        group_id: data.group_id ? Number(data.group_id) : null,
        unit_id: Number(data.unit_id),
        day: data.day,
        start_time: formatTimeToHi(data.start_time),
        end_time: formatTimeToHi(data.end_time),
        venue: data.venue,
        location: data.location,
        lecturer: data.lecturer,
        no: Number(data.no),
        teaching_mode: data.teaching_mode || "physical",
        classtimeslot_id: data.classtimeslot_id ? Number(data.classtimeslot_id) : null,
        school_id: data.school_id ? Number(data.school_id) : null,
      }

      // ✅ CLEAN: Remove undefined values
      Object.keys(formattedData).forEach((key) => {
        if (formattedData[key] === undefined || formattedData[key] === "") {
          delete formattedData[key]
        }
      })

      console.log("📤 Submitting formatted data:", formattedData) // ✅ DEBUG

      setIsSubmitting(true)

      if (data.id === 0 || !data.id) {
        // ✅ CREATE NEW TIMETABLE
        console.log("🆕 Creating new timetable...")

        router.post(`/classtimetables`, formattedData, {
          onSuccess: (response) => {
            console.log("✅ Create successful:", response)
            toast.success("Class timetable created successfully.")
            handleCloseModal()
            router.reload({ only: ["classTimetables"] })
          },
          onError: (errors: any) => {
            console.error("❌ Create failed with errors:", errors)
            let msg = "Failed to create class timetable."

            if (errors && typeof errors === "object") {
              if (errors.error) {
                msg = errors.error
              } else if (errors.message) {
                msg = errors.message
              } else {
                const errorMsgs = Object.values(errors).flat().filter(Boolean).join(" ")
                if (errorMsgs) msg = errorMsgs
              }
            } else if (typeof errors === "string") {
              msg = errors
            }

            toast.error(msg)
          },
          onFinish: () => {
            console.log("🏁 Create request finished")
            clearTimeout(timeoutId)
            setIsSubmitting(false)
          },
          onBefore: () => {
            console.log("🚀 Create request starting")
            return true
          },
        })
      } else {
        // ✅ UPDATE EXISTING TIMETABLE
        console.log("📝 Updating timetable with ID:", data.id)

        router.put(`/classtimetables/${data.id}`, formattedData, {
          onSuccess: (response) => {
            console.log("✅ Update successful:", response)
            toast.success("Class timetable updated successfully.")
            handleCloseModal()
            router.reload({ only: ["classTimetables"] })
          },
          onError: (errors: any) => {
            console.error("❌ Update failed with errors:", errors)
            let msg = "Failed to update class timetable."

            if (errors && typeof errors === "object") {
              if (errors.error) {
                msg = errors.error
              } else if (errors.message) {
                msg = errors.message
              } else {
                const errorMsgs = Object.values(errors).flat().filter(Boolean).join(" ")
                if (errorMsgs) msg = errorMsgs
              }
            } else if (typeof errors === "string") {
              msg = errors
            }

            toast.error(msg)
          },
          onFinish: () => {
            console.log("🏁 Update request finished")
            clearTimeout(timeoutId)
            setIsSubmitting(false)
          },
          onBefore: () => {
            console.log("🚀 Update request starting")
            return true
          },
        })
      }
    },
    [validateFormWithConstraints, handleCloseModal],
  )

  // ✅ FIXED: Stable handlers for form changes
  const handleSemesterChange = useCallback(
    (semesterId: number | string) => {
      if (!formState) return

      setIsLoading(true)
      setErrorMessage(null)
      setUnitLecturers([])

      const numericSemesterId = Number(semesterId)

      if (isNaN(numericSemesterId)) {
        setErrorMessage("Invalid semester ID")
        setIsLoading(false)
        return
      }

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              semester_id: numericSemesterId,
              class_id: null,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer_id: null,
              lecturer_name: "",
              lecturer: "",
            }
          : null,
      )

      setFilteredGroups([])
      setFilteredUnits([])
      setIsLoading(false)
    },
    [formState],
  )

  const handleClassChange = useCallback(
    async (classId: number | string) => {
      if (!formState) return

      const numericClassId = classId === "" ? null : Number(classId)

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              class_id: numericClassId,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer: "",
            }
          : null,
      )

      if (numericClassId === null) {
        setFilteredGroups([])
        setFilteredUnits([])
        return
      }

      const filteredGroupsForClass = groups.filter((group) => group.class_id === numericClassId)
      setFilteredGroups(filteredGroupsForClass)

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
            credit_hours: unit.credit_hours || 3,
          }))

          setFilteredUnits(unitsWithDetails)
          setErrorMessage(null)
        } else {
          setFilteredUnits([])
          setErrorMessage("No units found for the selected class in this semester.")
        }
      } catch (error: any) {
        setErrorMessage("Failed to fetch units for the selected class. Please try again.")
        setFilteredUnits([])
      } finally {
        setIsLoading(false)
      }
    },
    [formState, groups],
  )

  const handleDelete = useCallback(async (id: number) => {
    if (confirm("Are you sure you want to delete this class timetable?")) {
      try {
        await router.delete(`/classtimetables/${id}`, {
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
  }, [])

  const handleDownloadClassTimetable = useCallback(() => {
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
  }, [])

  const handleSearchSubmit = useCallback(
    (e: FormEvent) => {
      e.preventDefault()
      router.get("/classtimetable", { search: searchValue, perPage: rowsPerPage })
    },
    [searchValue, rowsPerPage],
  )

  const handlePerPageChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const newPerPage = Number.parseInt(e.target.value)
      setRowsPerPage(newPerPage)
      router.get("/classtimetable", { search: searchValue, perPage: newPerPage })
    },
    [searchValue],
  )

  // ✅ NEW: Conflict analysis handler
  const handleAnalyzeConflicts = useCallback(async () => {
    setIsAnalyzing(true)
    try {
      const response = await axios.post("/api/classtimetables/detect-conflicts")
      if (response.data.success) {
        setDetectedConflicts(response.data.conflicts || [])
        toast.success(`Analysis complete: ${response.data.conflicts_count || 0} conflicts found`)
      }
    } catch (error) {
      toast.error("Failed to analyze conflicts")
    } finally {
      setIsAnalyzing(false)
    }
  }, [])

  return (
    <AuthenticatedLayout>
      <Head title="Enhanced Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Smart Class Timetable</h1>
            <p className="text-gray-600 mt-1">Advanced constraint-based scheduling with conflict detection</p>
          </div>

          {detectedConflicts.length > 0 && (
            <Badge variant="destructive" className="text-lg px-4 py-2">
              {detectedConflicts.length} Conflicts Detected
            </Badge>
          )}
        </div>

        {/* Enhanced Controls */}
        <div className="flex justify-between items-center mb-6">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                <Plus className="w-4 h-4 mr-2" />
                Add Class
              </Button>
            )}

            <Button
              onClick={() => handleOpenModal("conflicts", null)}
              className="bg-orange-500 hover:bg-orange-600"
              disabled={isAnalyzing}
            >
              {isAnalyzing ? <Clock className="w-4 h-4 mr-2 animate-spin" /> : <AlertCircle className="w-4 h-4 mr-2" />}
              Analyze Conflicts
            </Button>

            {can.download && (
              <Button onClick={handleDownloadClassTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                <Download className="w-4 h-4 mr-2" />
                Download PDF
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue}
              onChange={(e) => setSearchValue(e.target.value)}
              placeholder="Search timetables..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              <Search className="w-4 h-4 mr-2" />
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

        {/* Constraints Summary */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="flex items-center">
              <Zap className="w-5 h-5 mr-2" />
              Scheduling Constraints
            </CardTitle>
            <CardDescription>Current rules for optimal timetable generation</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-6 gap-4 text-sm">
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">{constraints.maxPhysicalPerDay}</div>
                <div className="text-gray-600">Max Physical/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600">{constraints.maxOnlinePerDay}</div>
                <div className="text-gray-600">Max Online/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">{constraints.minHoursPerDay}</div>
                <div className="text-gray-600">Min Hours/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-red-600">{constraints.maxHoursPerDay}</div>
                <div className="text-gray-600">Max Hours/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-yellow-600">{constraints.requireMixedMode ? "✓" : "✗"}</div>
                <div className="text-gray-600">Mixed Mode</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-indigo-600">
                  {constraints.avoidConsecutiveSlots ? "✓" : "✗"}
                </div>
                <div className="text-gray-600">No Consecutive</div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Conflict Summary */}
        {detectedConflicts.length > 0 && (
          <Alert className="mb-6 border-red-200 bg-red-50">
            <XCircle className="h-4 w-4 text-red-500" />
            <AlertDescription>
              <div className="flex justify-between items-center">
                <span className="text-red-700">
                  <strong>{detectedConflicts.length} conflicts detected</strong> -
                  {detectedConflicts.filter((c) => c.severity === "high").length} high priority
                </span>
                <Button
                  onClick={() => handleOpenModal("conflicts", null)}
                  variant="outline"
                  size="sm"
                  className="border-red-300 text-red-700 hover:bg-red-100"
                >
                  View Details
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        )}

        {classTimetables?.data?.length > 0 ? (
          <>
            {/* Enhanced Timetable Display */}
            <div className="space-y-6">
              {Object.entries(organizedTimetables).map(([day, dayTimetables]) => {
                const dayConflicts = detectedConflicts.filter((c) => c.day === day)

                return (
                  <div key={day} className="border rounded-lg overflow-hidden">
                    <div className={`px-4 py-3 border-b ${dayConflicts.length > 0 ? "bg-red-50" : "bg-gray-100"}`}>
                      <div className="flex justify-between items-center">
                        <div>
                          <h3 className="text-lg font-semibold text-gray-800 flex items-center">
                            <Calendar className="w-5 h-5 mr-2" />
                            {day}
                            <Badge variant="outline" className="ml-2">
                              {dayTimetables.length} sessions
                            </Badge>
                          </h3>
                          <p className="text-sm text-gray-600">
                            {dayTimetables
                              .reduce((total, ct) => total + calculateDuration(ct.start_time, ct.end_time), 0)
                              .toFixed(1)}{" "}
                            total hours
                          </p>
                        </div>

                        {dayConflicts.length > 0 && (
                          <Badge variant="destructive">{dayConflicts.length} conflicts</Badge>
                        )}
                      </div>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b">
                          <tr>
                            <th className="px-3 py-2 text-left">Time</th>
                            <th className="px-3 py-2 text-left">Unit</th>
                            <th className="px-3 py-2 text-left">Class/Group</th>
                            <th className="px-3 py-2 text-left">Venue</th>
                            <th className="px-3 py-2 text-left">Mode</th>
                            <th className="px-3 py-2 text-left">Lecturer</th>
                            <th className="px-3 py-2 text-left">Students</th>
                            <th className="px-3 py-2 text-left">Status</th>
                            <th className="px-3 py-2 text-left">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          {dayTimetables.map((ct) => {
                            const hasConflict = detectedConflicts.some((conflict) =>
                              conflict.affectedSessions?.some((session: any) => session.id === ct.id),
                            )

                            return (
                              <tr key={ct.id} className={`border-b hover:bg-gray-50 ${hasConflict ? "bg-red-50" : ""}`}>
                                <td className="px-3 py-2 font-medium">
                                  <div className="flex items-center">
                                    {hasConflict && <AlertCircle className="w-4 h-4 text-red-500 mr-1" />}
                                    <div>
                                      <div>
                                        {formatTimeToHi(ct.start_time)} - {formatTimeToHi(ct.end_time)}
                                      </div>
                                      <div className="text-xs text-gray-500">
                                        {calculateDuration(ct.start_time, ct.end_time).toFixed(1)}h
                                      </div>
                                    </div>
                                  </div>
                                </td>
                                <td className="px-3 py-2">
                                  <div>
                                    <div className="font-medium">{ct.unit_code}</div>
                                    <div className="text-xs text-gray-500 truncate max-w-32">{ct.unit_name}</div>
                                    {ct.credit_hours && (
                                      <div className="text-xs text-blue-600">{ct.credit_hours} credits</div>
                                    )}
                                  </div>
                                </td>
                                <td className="px-3 py-2">
                                  <div>
                                    <div className="font-medium">{ct.class_name || ct.class_id || "-"}</div>
                                    {ct.group_name && (
                                      <Badge variant="outline" className="text-xs">
                                        {ct.group_name}
                                      </Badge>
                                    )}
                                  </div>
                                </td>
                                <td className="px-3 py-2">
                                  <div className="flex items-center">
                                    <MapPin className="w-3 h-3 mr-1" />
                                    <div>
                                      <div className="font-medium">{ct.venue}</div>
                                      <div className="text-xs text-gray-500">{ct.location}</div>
                                    </div>
                                  </div>
                                </td>
                                <td className="px-3 py-2">
                                  <Badge
                                    variant={ct.teaching_mode === "online" ? "default" : "secondary"}
                                    className={
                                      ct.teaching_mode === "online"
                                        ? "bg-blue-100 text-blue-800"
                                        : "bg-green-100 text-green-800"
                                    }
                                  >
                                    {ct.teaching_mode || "Physical"}
                                  </Badge>
                                </td>
                                <td className="px-3 py-2 text-sm">{ct.lecturer}</td>
                                <td className="px-3 py-2">
                                  <div className="flex items-center">
                                    <Users className="w-3 h-3 mr-1" />
                                    {ct.no}
                                  </div>
                                </td>
                                <td className="px-3 py-2">
                                  {hasConflict ? (
                                    <Badge variant="destructive" className="text-xs">
                                      Conflict
                                    </Badge>
                                  ) : (
                                    <Badge variant="outline" className="text-xs text-green-600">
                                      OK
                                    </Badge>
                                  )}
                                </td>
                                <td className="px-3 py-2">
                                  <div className="flex space-x-1">
                                    <Button
                                      onClick={() => handleOpenModal("view", ct)}
                                      className="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1"
                                    >
                                      <Eye className="w-3 h-3" />
                                    </Button>
                                    {can.edit && (
                                      <Button
                                        onClick={() => handleOpenModal("edit", ct)}
                                        className="bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-2 py-1"
                                      >
                                        <Edit className="w-3 h-3" />
                                      </Button>
                                    )}
                                    {can.delete && (
                                      <Button
                                        onClick={() => handleDelete(ct.id)}
                                        className="bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1"
                                      >
                                        <Trash2 className="w-3 h-3" />
                                      </Button>
                                    )}
                                  </div>
                                </td>
                              </tr>
                            )
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )
              })}
            </div>

            <Pagination links={classTimetables?.links || []} />
          </>
        ) : (
          <div className="text-center py-12">
            <Calendar className="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <p className="text-xl text-gray-600">No class timetables available yet.</p>
            <p className="text-gray-500 mt-2">Create your first timetable to get started.</p>
          </div>
        )}

        {/* ✅ RESTORED: All Modal Content */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded-lg shadow-xl w-[600px] max-h-[90vh] overflow-y-auto">
              {/* ✅ VIEW MODAL */}
              {modalType === "view" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Class Timetable</h2>
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Day</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.day}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Time</label>
                        <p className="mt-1 text-sm text-gray-900">
                          {formatTimeToHi(selectedClassTimetable.start_time)} -{" "}
                          {formatTimeToHi(selectedClassTimetable.end_time)}
                        </p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Unit</label>
                        <p className="mt-1 text-sm text-gray-900">
                          {selectedClassTimetable.unit_code} - {selectedClassTimetable.unit_name}
                        </p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Teaching Mode</label>
                        <Badge
                          className={
                            selectedClassTimetable.teaching_mode === "online"
                              ? "bg-blue-100 text-blue-800"
                              : "bg-green-100 text-green-800"
                          }
                        >
                          {selectedClassTimetable.teaching_mode || "Physical"}
                        </Badge>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Venue</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.venue}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Location</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.location}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Lecturer</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.lecturer}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Students</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.no}</p>
                      </div>
                    </div>
                  </div>
                  <div className="mt-6 flex justify-end">
                    <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500 text-white">
                      Close
                    </Button>
                  </div>
                </>
              )}

              {/* ✅ CREATE/EDIT MODAL */}
              {(modalType === "create" || modalType === "edit") && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">
                    {modalType === "create" ? "Create" : "Edit"} Duration-Based Timetable
                  </h2>

                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    {/* Duration Rules Info */}
                    <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                      <h4 className="font-medium text-blue-800 mb-1">Teaching Mode Rules:</h4>
                      <ul className="text-blue-700 space-y-1">
                        <li>• 2+ hour slots are automatically assigned as Physical classes</li>
                        <li>• 1 hour slots are automatically assigned as Online classes</li>
                        <li>• Teaching mode is determined by time slot duration</li>
                      </ul>
                    </div>

                    {/* School Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">School *</label>
                      <select
                        value={formState.school_id || ""}
                        onChange={(e) =>
                          setFormState((prev) => (prev ? { ...prev, school_id: Number(e.target.value) || null } : null))
                        }
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select School</option>
                        {schools.map((school) => (
                          <option key={school.id} value={school.id}>
                            {school.code} - {school.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Semester Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
                      <select
                        value={formState.semester_id || ""}
                        onChange={(e) => handleSemesterChange(e.target.value)}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select Semester</option>
                        {semesters.map((semester) => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Class Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                      <select
                        value={formState.class_id || ""}
                        onChange={(e) => handleClassChange(e.target.value)}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select Class</option>
                        {classes.map((cls) => (
                          <option key={cls.id} value={cls.id}>
                            {cls.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Group Selection */}
                    {filteredGroups.length > 0 && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Group</label>
                        <select
                          value={formState.group_id || ""}
                          onChange={(e) =>
                            setFormState((prev) =>
                              prev ? { ...prev, group_id: Number(e.target.value) || null } : null,
                            )
                          }
                          className="w-full border rounded p-2"
                        >
                          <option value="">Select Group (Optional)</option>
                          {filteredGroups.map((group) => (
                            <option key={group.id} value={group.id}>
                              {group.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}

                    {/* Unit Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                      <select
                        value={formState.unit_id || ""}
                        onChange={(e) => {
                          const selectedUnit = filteredUnits.find((u) => u.id === Number(e.target.value))
                          setFormState((prev) =>
                            prev
                              ? {
                                  ...prev,
                                  unit_id: Number(e.target.value),
                                  unit_code: selectedUnit?.code || "",
                                  unit_name: selectedUnit?.name || "",
                                  no: selectedUnit?.student_count || 0,
                                  lecturer: selectedUnit?.lecturer_name || "",
                                }
                              : null,
                          )
                        }}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select Unit</option>
                        {filteredUnits.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.code} - {unit.name} ({unit.student_count} students)
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Enhanced Time Slot Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Time Slot *
                        <span className="text-blue-600 text-xs ml-2">(Teaching mode auto-assigned by duration)</span>
                      </label>
                      <select
                        value={formState.classtimeslot_id || ""}
                        onChange={(e) => handleClassTimeSlotChange(e.target.value)}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select Time Slot</option>
                        {classtimeSlots.map((slot) => {
                          const duration = calculateDuration(slot.start_time, slot.end_time)
                          const autoMode = getTeachingModeFromDuration(slot.start_time, slot.end_time)
                          const modeIcon = autoMode === "physical" ? "🏫" : "📱"

                          return (
                            <option key={slot.id} value={slot.id}>
                              {modeIcon} {slot.day} {slot.start_time}-{slot.end_time} ({duration.toFixed(1)}h →{" "}
                              {autoMode})
                            </option>
                          )
                        })}
                      </select>
                    </div>

                    {/* Duration and Mode Display */}
                    {formState.start_time && formState.end_time && (
                      <div className="mb-4 p-3 bg-gray-50 border border-gray-200 rounded">
                        <div className="grid grid-cols-3 gap-4 text-sm">
                          <div>
                            <span className="font-medium text-gray-700">Duration:</span>
                            <div className="text-lg font-bold text-blue-600">
                              {calculateDuration(formState.start_time, formState.end_time).toFixed(1)} hours
                            </div>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Auto Mode:</span>
                            <div className="mt-1">
                              <Badge
                                className={
                                  formState.teaching_mode === "online"
                                    ? "bg-blue-100 text-blue-800"
                                    : "bg-green-100 text-green-800"
                                }
                              >
                                {formState.teaching_mode === "online" ? "📱 Online" : "🏫 Physical"}
                              </Badge>
                            </div>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Auto Venue:</span>
                            <div className="text-sm font-medium text-gray-800">
                              {formState.venue || "Auto-assigned"}
                            </div>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Read-only Teaching Mode Display */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Teaching Mode
                        <span className="text-blue-600 text-xs ml-2">(Auto-determined by duration)</span>
                      </label>
                      <div className="w-full border rounded p-2 bg-gray-50 flex items-center">
                        <Badge
                          className={`mr-2 ${
                            formState.teaching_mode === "online"
                              ? "bg-blue-100 text-blue-800"
                              : "bg-green-100 text-green-800"
                          }`}
                        >
                          {formState.teaching_mode === "online" ? "📱 Online" : "🏫 Physical"}
                        </Badge>
                        <span className="text-sm text-gray-600">
                          {formState.start_time && formState.end_time
                            ? `${calculateDuration(formState.start_time, formState.end_time).toFixed(1)}h duration`
                            : "Select time slot first"}
                        </span>
                      </div>
                      <div className="text-xs text-gray-500 mt-1">
                        💡 2+ hours = Physical class | 1 hour = Online class
                      </div>
                    </div>

                    {/* Enhanced Venue Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Venue
                        <span className="text-blue-600 text-xs ml-2">(Auto-assigned based on teaching mode)</span>
                      </label>

                      {formState.teaching_mode === "online" ? (
                        <div className="w-full border rounded p-2 bg-blue-50 flex items-center">
                          <Users className="w-4 h-4 text-blue-600 mr-2" />
                          <span className="text-blue-800 font-medium">Remote (Online Class)</span>
                        </div>
                      ) : (
                        <select
                          value={formState.venue}
                          onChange={(e) => {
                            const venueName = e.target.value
                            const selectedClassroom = classrooms.find((c) => c.name === venueName)
                            setFormState((prev) => ({
                              ...prev!,
                              venue: venueName,
                              location: selectedClassroom?.location || "Physical",
                            }))
                          }}
                          className="w-full border rounded p-2"
                        >
                          <option value="">Auto-assign suitable venue</option>
                          {classrooms
                            .filter((c) => c.capacity >= (formState.no || 0))
                            .map((classroom) => (
                              <option key={classroom.id} value={classroom.name}>
                                🏫 {classroom.name} (Capacity: {classroom.capacity}, {classroom.location})
                              </option>
                            ))}
                        </select>
                      )}
                    </div>

                    {/* Lecturer */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Lecturer *</label>
                      <input
                        type="text"
                        value={formState.lecturer}
                        onChange={(e) => setFormState((prev) => (prev ? { ...prev, lecturer: e.target.value } : null))}
                        className="w-full border rounded p-2"
                        required
                      />
                    </div>

                    {/* Number of Students */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Number of Students *</label>
                      <input
                        type="number"
                        value={formState.no}
                        onChange={(e) =>
                          setFormState((prev) => (prev ? { ...prev, no: Number(e.target.value) } : null))
                        }
                        className="w-full border rounded p-2"
                        min="1"
                        required
                      />
                    </div>

                    {/* Conflict Warning */}
                    {conflictWarning && (
                      <Alert className="mb-4 border-red-200 bg-red-50">
                        <AlertCircle className="h-4 w-4 text-red-500" />
                        <AlertDescription className="text-red-700">{conflictWarning}</AlertDescription>
                      </Alert>
                    )}

                    {/* Duration-Based Assignment Info */}
                    <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded text-sm">
                      <div className="flex items-center text-green-700">
                        <Zap className="w-4 h-4 mr-2" />
                        <span className="font-medium">Smart Assignment Active:</span>
                      </div>
                      <div className="mt-2 text-green-600 text-xs">
                        • Time slot duration automatically determines teaching mode
                        <br />• Teaching mode automatically determines venue type
                        <br />• Physical classes get classroom venues, online classes get "Remote"
                      </div>
                    </div>

                    {/* Form Actions */}
                    <div className="mt-6 flex justify-end space-x-3">
                      <Button
                        type="button"
                        onClick={handleCloseModal}
                        className="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2"
                      >
                        Cancel
                      </Button>

                      {/* ✅ EMERGENCY RESET: If stuck in loading state */}
                      {isSubmitting && (
                        <Button
                          type="button"
                          onClick={() => {
                            console.log("🔄 Emergency reset triggered")
                            setIsSubmitting(false)
                            toast.info("Loading state reset. Please try again.")
                          }}
                          className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2"
                        >
                          Reset
                        </Button>
                      )}

                      <Button
                        type="submit"
                        disabled={isSubmitting}
                        className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {isSubmitting ? (
                          <>
                            <Clock className="w-4 h-4 mr-2 animate-spin" />
                            {modalType === "create" ? "Creating..." : "Updating..."}
                          </>
                        ) : (
                          <>
                            <Zap className="w-4 h-4 mr-2" />
                            {modalType === "create" ? "Create Smart Timetable" : "Update Smart Timetable"}
                          </>
                        )}
                      </Button>
                    </div>
                  </form>
                </>
              )}

              {/* ✅ CONFLICTS MODAL */}
              {modalType === "conflicts" && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <AlertCircle className="w-5 h-5 mr-2 text-red-500" />
                    Conflict Analysis
                  </h2>

                  <div className="space-y-4">
                    <div className="flex justify-between items-center mb-4">
                      <div>
                        <p className="text-sm text-gray-600">
                          Found {detectedConflicts.length} conflicts in the current timetable
                        </p>
                      </div>
                      <Button
                        onClick={handleAnalyzeConflicts}
                        disabled={isAnalyzing}
                        className="bg-orange-500 hover:bg-orange-600 text-white"
                      >
                        {isAnalyzing ? (
                          <>
                            <Clock className="w-4 h-4 mr-2 animate-spin" />
                            Analyzing...
                          </>
                        ) : (
                          <>
                            <AlertCircle className="w-4 h-4 mr-2" />
                            Re-analyze
                          </>
                        )}
                      </Button>
                    </div>

                    {detectedConflicts.length > 0 ? (
                      <div className="space-y-3 max-h-96 overflow-y-auto">
                        {detectedConflicts.map((conflict, index) => (
                          <div key={index} className="border border-red-200 rounded-lg p-4 bg-red-50">
                            <div className="flex justify-between items-start mb-2">
                              <div>
                                <h4 className="font-medium text-red-800">
                                  {conflict.type.replace("_", " ").toUpperCase()}
                                </h4>
                                <p className="text-sm text-red-700">{conflict.description}</p>
                              </div>
                              <Badge variant="destructive" className="text-xs">
                                {conflict.severity}
                              </Badge>
                            </div>

                            {conflict.affectedSessions && (
                              <div className="mt-3 space-y-2">
                                <p className="text-xs font-medium text-red-800">Affected Sessions:</p>
                                {conflict.affectedSessions.map((session: any, sessionIndex: number) => (
                                  <div key={sessionIndex} className="text-xs bg-white p-2 rounded border">
                                    <span className="font-medium">{session.unit_code}</span> -{session.day}{" "}
                                    {formatTimeToHi(session.start_time)}-{formatTimeToHi(session.end_time)} -
                                    {session.lecturer} - {session.venue}
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                        <p className="text-lg text-green-600 font-medium">No conflicts detected!</p>
                        <p className="text-sm text-gray-500 mt-2">Your timetable is optimally scheduled.</p>
                      </div>
                    )}
                  </div>

                  <div className="mt-6 flex justify-end">
                    <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500 text-white">
                      Close
                    </Button>
                  </div>
                </>
              )}

              {/* ✅ CSP SOLVER MODAL */}
              {modalType === "csp_solver" && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <Zap className="w-5 h-5 mr-2 text-blue-500" />
                    Constraint Satisfaction Solver
                  </h2>

                  <div className="space-y-4">
                    <Alert className="border-blue-200 bg-blue-50">
                      <AlertCircle className="h-4 w-4 text-blue-500" />
                      <AlertDescription className="text-blue-700">
                        The CSP solver will automatically generate an optimal timetable based on your constraints. This
                        may take a few minutes for large datasets.
                      </AlertDescription>
                    </Alert>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="p-4 border rounded-lg">
                        <h4 className="font-medium text-gray-800 mb-2">Current Constraints</h4>
                        <ul className="text-sm text-gray-600 space-y-1">
                          <li>• Max {constraints.maxPhysicalPerDay} physical classes/day</li>
                          <li>• Max {constraints.maxOnlinePerDay} online classes/day</li>
                          <li>• Max {constraints.maxHoursPerDay} hours/day</li>
                          <li>• {constraints.requireMixedMode ? "Require" : "Don't require"} mixed mode</li>
                          <li>• {constraints.avoidConsecutiveSlots ? "Avoid" : "Allow"} consecutive slots</li>
                        </ul>
                      </div>

                      <div className="p-4 border rounded-lg">
                        <h4 className="font-medium text-gray-800 mb-2">Solver Options</h4>
                        <div className="space-y-2">
                          <label className="flex items-center">
                            <input type="checkbox" className="mr-2" defaultChecked />
                            <span className="text-sm">Optimize for minimal conflicts</span>
                          </label>
                          <label className="flex items-center">
                            <input type="checkbox" className="mr-2" defaultChecked />
                            <span className="text-sm">Balance teaching modes</span>
                          </label>
                          <label className="flex items-center">
                            <input type="checkbox" className="mr-2" />
                            <span className="text-sm">Preserve existing assignments</span>
                          </label>
                        </div>
                      </div>
                    </div>

                    <div className="flex justify-center">
                      <Button className="bg-blue-500 hover:bg-blue-600 text-white px-8 py-3">
                        <Zap className="w-4 h-4 mr-2" />
                        Run CSP Solver
                      </Button>
                    </div>
                  </div>

                  <div className="mt-6 flex justify-end">
                    <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500 text-white">
                      Close
                    </Button>
                  </div>
                </>
              )}
            </div>
          </div>
        )}

        {/* Enhanced Statistics Dashboard */}
        {classTimetables?.data?.length > 0 && (
          <div className="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-blue-600">{classTimetables.data.length}</div>
                    <div className="text-sm text-gray-600">Total Sessions</div>
                  </div>
                  <Calendar className="w-8 h-8 text-blue-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-green-600">
                      {classTimetables.data.filter((s) => s.teaching_mode === "physical").length}
                    </div>
                    <div className="text-sm text-gray-600">Physical Sessions</div>
                  </div>
                  <MapPin className="w-8 h-8 text-green-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-purple-600">
                      {classTimetables.data.filter((s) => s.teaching_mode === "online").length}
                    </div>
                    <div className="text-sm text-gray-600">Online Sessions</div>
                  </div>
                  <Users className="w-8 h-8 text-purple-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div
                      className={`text-2xl font-bold ${detectedConflicts.length > 0 ? "text-red-600" : "text-green-600"}`}
                    >
                      {detectedConflicts.length}
                    </div>
                    <div className="text-sm text-gray-600">
                      {detectedConflicts.length === 0 ? "No Conflicts" : "Conflicts"}
                    </div>
                  </div>
                  {detectedConflicts.length > 0 ? (
                    <XCircle className="w-8 h-8 text-red-500" />
                  ) : (
                    <CheckCircle className="w-8 h-8 text-green-500" />
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default EnhancedClassTimetable
