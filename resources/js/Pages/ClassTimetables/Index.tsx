"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { AlertCircle, CheckCircle, XCircle, Clock, Users, MapPin, Calendar, Zap, Brain } from "lucide-react"
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
  unit_code?: string
  unit_name?: string
  semester_name?: string
  class_name?: string
  group_name?: string
  status?: string
  credit_hours?: number
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
  credit_hours?: number
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

interface ConflictReport {
  type: "lecturer_conflict" | "venue_conflict" | "group_conflict" | "constraint_violation"
  severity: "high" | "medium" | "low"
  description: string
  affectedSessions: any[]
  group?: string
  day?: string
  lecturer?: string
  venue?: string
}

// Enhanced helper functions with constraint validation
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
  const startMinutes = timeToMinutes(startTime)
  const endMinutes = timeToMinutes(endTime)
  return (endMinutes - startMinutes) / 60
}

// Enhanced constraint validation
const validateGroupDailyConstraints = (
  groupId: number | null,
  day: string,
  startTime: string,
  endTime: string,
  teachingMode: string,
  classTimetables: PaginatedClassTimetables,
  constraints: SchedulingConstraints,
  excludeId?: number,
) => {
  if (!groupId) return { isValid: true, message: "", warnings: [] }

  const groupDaySlots = classTimetables.data.filter(
    (ct) => ct.group_id === groupId && ct.day === day && ct.id !== excludeId,
  )

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

  // Check for consecutive slots
  if (constraints.avoidConsecutiveSlots) {
    const hasConsecutive = groupDaySlots.some((ct) => ct.end_time === startTime || ct.start_time === endTime)
    if (hasConsecutive) {
      errors.push("Consecutive time slots are not allowed for the same group")
    }
  }

  // Soft constraints (warnings)
  if (totalHours < constraints.minHoursPerDay && groupDaySlots.length === 0) {
    warnings.push(
      `Groups should have at least ${constraints.minHoursPerDay} hours per day. Current slot is ${newSlotHours.toFixed(1)} hours`,
    )
  }

  // Mixed mode requirement
  if (constraints.requireMixedMode && groupDaySlots.length > 0) {
    const hasPhysical = physicalCount > 0 || teachingMode === "physical"
    const hasOnline = onlineCount > 0 || teachingMode === "online"

    if (!hasPhysical || !hasOnline) {
      warnings.push("Groups should have a mix of physical and online classes per day")
    }
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

// Enhanced conflict detection
const detectScheduleConflicts = (
  classTimetables: PaginatedClassTimetables,
  constraints: SchedulingConstraints,
): ConflictReport[] => {
  const conflicts: ConflictReport[] = []

  // Group conflicts by day
  const groupDaySlots: Record<string, ClassTimetable[]> = {}

  classTimetables.data.forEach((ct) => {
    if (!ct.group_id) return

    const key = `${ct.group_id}_${ct.day}`
    if (!groupDaySlots[key]) {
      groupDaySlots[key] = []
    }
    groupDaySlots[key].push(ct)
  })

  // Check constraint violations
  Object.entries(groupDaySlots).forEach(([key, slots]) => {
    const [groupId, day] = key.split("_")
    const group = slots[0]?.group_name || `Group ${groupId}`

    const physicalCount = slots.filter((s) => s.teaching_mode === "physical").length
    const onlineCount = slots.filter((s) => s.teaching_mode === "online").length
    const totalHours = slots.reduce((sum, s) => sum + calculateDuration(s.start_time, s.end_time), 0)

    if (physicalCount > constraints.maxPhysicalPerDay) {
      conflicts.push({
        type: "constraint_violation",
        severity: "high",
        description: `${group} has ${physicalCount} physical classes on ${day} (max: ${constraints.maxPhysicalPerDay})`,
        affectedSessions: slots.filter((s) => s.teaching_mode === "physical"),
        group,
        day,
      })
    }

    if (onlineCount > constraints.maxOnlinePerDay) {
      conflicts.push({
        type: "constraint_violation",
        severity: "high",
        description: `${group} has ${onlineCount} online classes on ${day} (max: ${constraints.maxOnlinePerDay})`,
        affectedSessions: slots.filter((s) => s.teaching_mode === "online"),
        group,
        day,
      })
    }

    if (totalHours > constraints.maxHoursPerDay) {
      conflicts.push({
        type: "constraint_violation",
        severity: "high",
        description: `${group} has ${totalHours.toFixed(1)} hours on ${day} (max: ${constraints.maxHoursPerDay})`,
        affectedSessions: slots,
        group,
        day,
      })
    }

    if (constraints.requireMixedMode && slots.length > 1) {
      const hasPhysical = physicalCount > 0
      const hasOnline = onlineCount > 0

      if (!hasPhysical || !hasOnline) {
        conflicts.push({
          type: "constraint_violation",
          severity: "medium",
          description: `${group} doesn't have mixed teaching modes on ${day}`,
          affectedSessions: slots,
          group,
          day,
        })
      }
    }

    // Check for time overlaps within the same group
    for (let i = 0; i < slots.length; i++) {
      for (let j = i + 1; j < slots.length; j++) {
        if (timeSlotsOverlap(slots[i], slots[j])) {
          conflicts.push({
            type: "group_conflict",
            severity: "high",
            description: `${group} has overlapping classes on ${day}`,
            affectedSessions: [slots[i], slots[j]],
            group,
            day,
          })
        }
      }
    }
  })

  // Check lecturer conflicts
  const lecturerDaySlots: Record<string, ClassTimetable[]> = {}

  classTimetables.data.forEach((ct) => {
    if (!ct.lecturer) return

    const key = `${ct.lecturer}_${ct.day}`
    if (!lecturerDaySlots[key]) {
      lecturerDaySlots[key] = []
    }
    lecturerDaySlots[key].push(ct)
  })

  Object.entries(lecturerDaySlots).forEach(([key, slots]) => {
    const [lecturer, day] = key.split("_")

    for (let i = 0; i < slots.length; i++) {
      for (let j = i + 1; j < slots.length; j++) {
        if (timeSlotsOverlap(slots[i], slots[j])) {
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
  })

  // Check venue conflicts (excluding online venues)
  const venueDaySlots: Record<string, ClassTimetable[]> = {}

  classTimetables.data.forEach((ct) => {
    if (!ct.venue || ct.venue.toLowerCase() === "remote") return

    const key = `${ct.venue}_${ct.day}`
    if (!venueDaySlots[key]) {
      venueDaySlots[key] = []
    }
    venueDaySlots[key].push(ct)
  })

  Object.entries(venueDaySlots).forEach(([key, slots]) => {
    const [venue, day] = key.split("_")

    for (let i = 0; i < slots.length; i++) {
      for (let j = i + 1; j < slots.length; j++) {
        if (timeSlotsOverlap(slots[i], slots[j])) {
          conflicts.push({
            type: "venue_conflict",
            severity: "high",
            description: `${venue} is double-booked on ${day}`,
            affectedSessions: [slots[i], slots[j]],
            venue,
            day,
          })
        }
      }
    }
  })

  return conflicts
}

const timeSlotsOverlap = (slot1: ClassTimetable, slot2: ClassTimetable): boolean => {
  if (slot1.day !== slot2.day) return false

  const start1 = timeToMinutes(slot1.start_time)
  const end1 = timeToMinutes(slot1.end_time)
  const start2 = timeToMinutes(slot2.start_time)
  const end2 = timeToMinutes(slot2.end_time)

  return start1 < end2 && start2 < end1
}

// Helper function to organize timetables by day and time
const organizeTimetablesByDayAndTime = (timetables: ClassTimetable[]) => {
  const organized: { [day: string]: ClassTimetable[] } = {}

  timetables.forEach((timetable) => {
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
}

// Helper function to get group daily hours summary
const getGroupDailyHoursSummary = (groupId: number | null, classTimetables: PaginatedClassTimetables) => {
  const dailyStats: { [day: string]: { hours: number; physical: number; online: number } } = {}

  classTimetables.data.forEach((ct) => {
    if (ct.group_id === groupId) {
      if (!dailyStats[ct.day]) {
        dailyStats[ct.day] = { hours: 0, physical: 0, online: 0 }
      }

      dailyStats[ct.day].hours += calculateDuration(ct.start_time, ct.end_time)
      if (ct.teaching_mode === "physical") {
        dailyStats[ct.day].physical++
      } else if (ct.teaching_mode === "online") {
        dailyStats[ct.day].online++
      }
    }
  })

  return dailyStats
}

const EnhancedClassTimetable = () => {
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
    schools: { id: number; code: string; name: string }[]
    constraints: SchedulingConstraints
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  const {
    classTimetables = { data: [] },
    perPage = 10,
    search = "",
    semesters = [],
    can = { create: false, edit: false, delete: false, download: false },
    enrollments = [],
    classrooms = [],
    classtimeSlots = [],
    units = [],
    lecturers = [],
    schools = [],
    constraints = {
      maxPhysicalPerDay: 2,
      maxOnlinePerDay: 2,
      minHoursPerDay: 2,
      maxHoursPerDay: 5,
      requireMixedMode: true,
      avoidConsecutiveSlots: true,
    },
  } = pageProps

  const programs = Array.isArray(pageProps.programs) ? pageProps.programs : []
  const classes = Array.isArray(pageProps.classes) ? pageProps.classes : []
  const groups = Array.isArray(pageProps.groups) ? pageProps.groups : []

  // Enhanced state management
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "conflicts" | "csp_solver" | "">(
    "",
  )
  const [selectedClassTimetable, setSelectedClassTimetable] = useState<ClassTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<Lecturer[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])

  // Enhanced conflict detection state
  const [detectedConflicts, setDetectedConflicts] = useState<ConflictReport[]>([])
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [showConflictAnalysis, setShowConflictAnalysis] = useState(false)

  // CSP Solver state
  const [isCSPSolving, setIsCSPSolving] = useState(false)

  // Auto-generate modal state
  const [isAutoGenerateModalOpen, setIsAutoGenerateModalOpen] = useState(false)
  const [autoGenerateFormState, setAutoGenerateFormState] = useState({
    semester_id: "",
    program_id: "",
    class_id: "",
    group_id: "",
  })
  const [autoPrograms, setAutoProgramsRaw] = useState<Program[]>([])
  const [autoClasses, setAutoClassesRaw] = useState<Class[]>([])
  const [autoGroups, setAutoGroupsRaw] = useState<Group[]>([])
  const [autoLoading, setAutoLoading] = useState(false)

  // Organize timetables and detect conflicts
  const organizedTimetables = organizeTimetablesByDayAndTime(classTimetables.data)

  // Real-time conflict detection
  useEffect(() => {
    if (classTimetables.data.length > 0) {
      const conflicts = detectScheduleConflicts(classTimetables, constraints)
      setDetectedConflicts(conflicts)
    }
  }, [classTimetables.data, constraints])

  // Enhanced conflict analysis
  const analyzeScheduleConflicts = async () => {
    setIsAnalyzing(true)
    try {
      const response = await axios.post("/api/detect-conflicts", {
        semester_id: formState?.semester_id,
        class_id: formState?.class_id,
        group_id: formState?.group_id,
      })

      if (response.data.success) {
        setDetectedConflicts(response.data.conflicts)
        setShowConflictAnalysis(true)
        toast.success(`Analysis complete. Found ${response.data.total_conflicts} conflicts.`)
      }
    } catch (error) {
      console.error("Error analyzing conflicts:", error)
      toast.error("Failed to analyze schedule conflicts")
    } finally {
      setIsAnalyzing(false)
    }
  }

  // CSP Solver function
  const runAdvancedCSPSolver = async (algorithm: string, mode: string) => {
    setIsCSPSolving(true)
    try {
      // Use the correct endpoints that match your routes
      const endpoint = mode === "optimize" ? "/optimize-schedule" : "/generate-optimal-schedule"

      const response = await axios.post(endpoint, {
        algorithm,
        semester_id: null,
        class_id: null,
        group_id: null,
      })

      if (response.data.success) {
        toast.success(`CSP Solver completed! ${response.data.message}`)
        router.reload({ only: ["classTimetables"] })
        handleCloseModal()
      } else {
        toast.error(response.data.message || "CSP Solver failed")
      }
    } catch (error: any) {
      console.error("Error running CSP solver:", error)
      toast.error("Failed to run CSP solver: " + (error.response?.data?.message || error.message))
    } finally {
      setIsCSPSolving(false)
    }
  }

  // Enhanced form validation with constraints
  const validateFormWithConstraints = (data: FormState): { isValid: boolean; message: string; warnings: string[] } => {
    if (!data.group_id || !data.day || !data.start_time || !data.end_time || !data.teaching_mode) {
      return { isValid: true, message: "", warnings: [] }
    }

    return validateGroupDailyConstraints(
      data.group_id,
      data.day,
      data.start_time,
      data.end_time,
      data.teaching_mode,
      classTimetables,
      constraints,
      data.id !== 0 ? data.id : undefined,
    )
  }

  // Enhanced modal handlers
  const handleOpenModal = (
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
        (e) => e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
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
    setShowConflictAnalysis(false)
  }

  // Enhanced form submission with constraint validation
  const handleSubmitForm = (data: FormState) => {
    console.log("Enhanced form submission with constraints:", data)

    if (!data.class_id) {
      toast.error("Please select a class before submitting.")
      return
    }

    // Validate constraints
    const validation = validateFormWithConstraints(data)
    if (!validation.isValid) {
      toast.error(validation.message)
      return
    }

    if (validation.warnings.length > 0) {
      validation.warnings.forEach((warning) => toast(warning, { icon: "⚠️" }))
    }

    const formattedData: any = {
      ...data,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
    }

    // Clean up form data
    delete formattedData.unit_code
    delete formattedData.unit_name
    delete formattedData.classtimeslot_id
    delete formattedData.lecturer_id
    delete formattedData.lecturer_name
    delete formattedData.enrollment_id

    Object.keys(formattedData).forEach((key) =>
      formattedData[key] === undefined ? delete formattedData[key] : undefined,
    )

    setIsSubmitting(true)

    if (data.id === 0) {
      router.post(`/classtimetables`, formattedData, {
        onSuccess: () => {
          toast.success("Class timetable created successfully with constraint validation.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: (errors: any) => {
          let msg = "Failed to create class timetable."
          if (errors && typeof errors === "object") {
            if (errors.error) {
              msg = errors.error
            } else {
              const errorMsgs = Object.values(errors).flat().filter(Boolean).join(" ")
              if (errorMsgs) msg = errorMsgs
            }
          }
          toast.error(msg)
        },
        onFinish: () => setIsSubmitting(false),
      })
    } else {
      router.put(`/classtimetable/${data.id}`, formattedData, {
        onSuccess: () => {
          toast.success("Class timetable updated successfully with constraint validation.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: (errors: any) => {
          let msg = "Failed to update class timetable."
          if (errors && typeof errors === "object") {
            if (errors.error) {
              msg = errors.error
            } else {
              const errorMsgs = Object.values(errors).flat().filter(Boolean).join(" ")
              if (errorMsgs) msg = errorMsgs
            }
          }
          toast.error(msg)
        },
        onFinish: () => setIsSubmitting(false),
      })
    }
  }

 // ...existing code...
useEffect(() => {
  if (
    formState &&
    formState.group_id &&
    formState.day &&
    formState.start_time &&
    formState.end_time &&
    formState.teaching_mode
  ) {
    const validation = validateFormWithConstraints(formState);

    if (!validation.isValid) {
      setConflictWarning(validation.message);
    } else if (validation.warnings.length > 0) {
      setConflictWarning(validation.warnings.join("; "));
    } else {
      setConflictWarning(null);
    }
  }
  // Only watch the specific fields, not the whole objects
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, [
  formState?.group_id,
  formState?.day,
  formState?.start_time,
  formState?.end_time,
  formState?.teaching_mode,
  constraints.maxPhysicalPerDay,
  constraints.maxOnlinePerDay,
  constraints.minHoursPerDay,
  constraints.maxHoursPerDay,
  constraints.requireMixedMode,
  constraints.avoidConsecutiveSlots,
  classTimetables.data // Only watch the data array, not the whole object
]);

  // Keep existing handlers but enhance them
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
      class_id: null,
      group_id: null,
      unit_id: 0,
      unit_code: "",
      unit_name: "",
      no: 0,
      lecturer_id: null,
      lecturer_name: "",
      lecturer: "",
    }))

    setFilteredGroups([])
    setFilteredUnits([])
    setIsLoading(false)
  }

  const handleClassChange = async (classId: number | string) => {
    if (!formState) return

    const numericClassId = classId === "" ? null : Number(classId)

    setFormState((prev) => ({
      ...prev!,
      class_id: numericClassId,
      group_id: null,
      unit_id: 0,
      unit_code: "",
      unit_name: "",
      no: 0,
      lecturer: "",
    }))

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
      console.error("Error fetching units for class:", error.response?.data || error.message)
      setErrorMessage("Failed to fetch units for the selected class. Please try again.")
      setFilteredUnits([])
    } finally {
      setIsLoading(false)
    }
  }

  const handleGroupChange = (groupId: number | string) => {
    if (!formState) return

    const numericGroupId = groupId === "" ? null : Number(groupId)

    setFormState((prev) => ({
      ...prev!,
      group_id: numericGroupId,
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

      if (formState.semester_id) {
        findLecturersForUnit(unitId, formState.semester_id)
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

  const handleDelete = async (id: number) => {
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
                <Users className="w-4 h-4 mr-2" />
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

            {/* <Button
              onClick={() => handleOpenModal("csp_solver", null)}
              className="bg-purple-500 hover:bg-purple-600"
              disabled={isCSPSolving}
            >
              {isCSPSolving ? <Clock className="w-4 h-4 mr-2 animate-spin" /> : <Brain className="w-4 h-4 mr-2" />}
              CSP Solver
            </Button> */}

            {can.download && (
              <Button onClick={handleDownloadClassTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download PDF
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue}
              onChange={handleSearchChange}
              placeholder="Search timetables..."
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
                  {detectedConflicts.filter((c) => c.severity === "high").length} high priority,
                  {detectedConflicts.filter((c) => c.severity === "medium").length} medium priority
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
                              conflict.affectedSessions.some((session) => session.id === ct.id),
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
                                      View
                                    </Button>
                                    {can.edit && (
                                      <Button
                                        onClick={() => handleOpenModal("edit", ct)}
                                        className="bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-2 py-1"
                                      >
                                        Edit
                                      </Button>
                                    )}
                                    {can.delete && (
                                      <Button
                                        onClick={() => handleDelete(ct.id)}
                                        className="bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1"
                                      >
                                        Delete
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

        {/* Enhanced Modal for Create/Edit/View/Delete/Conflicts/CSP Solver */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded-lg shadow-xl w-[600px] max-h-[90vh] overflow-y-auto">
              {/* CSP Solver Modal */}
              {modalType === "csp_solver" && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <Brain className="w-5 h-5 mr-2 text-purple-500" />
                    Advanced CSP Schedule Optimizer
                  </h2>

                  <div className="space-y-6">
                    <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                      <h3 className="font-medium text-purple-800 mb-2">What is CSP Solver?</h3>
                      <p className="text-sm text-purple-700">
                        The Constraint Satisfaction Problem (CSP) solver uses advanced algorithms to automatically
                        optimize your timetable by resolving conflicts and improving schedule efficiency while
                        respecting all constraints.
                      </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card className="cursor-pointer hover:shadow-md transition-shadow">
        <CardContent className="p-4">
          <h4 className="font-medium text-center mb-2">Simulated Annealing</h4>
          <p className="text-xs text-gray-600 text-center mb-3">
            Gradually improves schedule quality through iterative optimization
          </p>
          <Button
            onClick={() => runAdvancedCSPSolver("simulated_annealing", "optimize")}
            disabled={isCSPSolving}
            className="w-full bg-blue-500 hover:bg-blue-600"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Optimize Current"}
          </Button>
          <Button
            onClick={() => runAdvancedCSPSolver("simulated_annealing", "generate")}
            disabled={isCSPSolving}
            className="w-full bg-blue-600 hover:bg-blue-700 mt-2"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Generate New"}
          </Button>
        </CardContent>
      </Card>

      <Card className="cursor-pointer hover:shadow-md transition-shadow">
        <CardContent className="p-4">
          <h4 className="font-medium text-center mb-2">Genetic Algorithm</h4>
          <p className="text-xs text-gray-600 text-center mb-3">
            Evolves multiple schedule solutions to find the optimal arrangement
          </p>
          <Button
            onClick={() => runAdvancedCSPSolver("genetic", "optimize")}
            disabled={isCSPSolving}
            className="w-full bg-green-500 hover:bg-green-600"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Optimize Current"}
          </Button>
          <Button
            onClick={() => runAdvancedCSPSolver("genetic", "generate")}
            disabled={isCSPSolving}
            className="w-full bg-green-600 hover:bg-green-700 mt-2"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Generate New"}
          </Button>
        </CardContent>
      </Card>

      <Card className="cursor-pointer hover:shadow-md transition-shadow">
        <CardContent className="p-4">
          <h4 className="font-medium text-center mb-2">Backtracking Search</h4>
          <p className="text-xs text-gray-600 text-center mb-3">
            Systematically explores solutions to guarantee conflict-free schedules
          </p>
          <Button
            onClick={() => runAdvancedCSPSolver("backtracking", "optimize")}
            disabled={isCSPSolving}
            className="w-full bg-orange-500 hover:bg-orange-600"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Optimize Current"}
          </Button>
          <Button
            onClick={() => runAdvancedCSPSolver("backtracking", "generate")}
            disabled={isCSPSolving}
            className="w-full bg-orange-600 hover:bg-orange-700 mt-2"
            size="sm"
          >
            {isCSPSolving ? "Running..." : "Generate New"}
          </Button>
        </CardContent>
      </Card>
    </div>

                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                      <h4 className="font-medium text-gray-800 mb-2">Current Constraints</h4>
                      <div className="grid grid-cols-2 gap-2 text-sm text-gray-600">
                        <div>• Max Physical/Day: {constraints.maxPhysicalPerDay}</div>
                        <div>• Max Online/Day: {constraints.maxOnlinePerDay}</div>
                        <div>• Min Hours/Day: {constraints.minHoursPerDay}</div>
                        <div>• Max Hours/Day: {constraints.maxHoursPerDay}</div>
                        <div>• Mixed Mode: {constraints.requireMixedMode ? "Required" : "Optional"}</div>
                        <div>• Consecutive Slots: {constraints.avoidConsecutiveSlots ? "Avoided" : "Allowed"}</div>
                      </div>
                    </div>

                    {detectedConflicts.length > 0 && (
                      <Alert className="border-yellow-200 bg-yellow-50">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-700">
                          <strong>{detectedConflicts.length} conflicts detected</strong> - The CSP solver will
                          automatically resolve these conflicts during optimization.
                        </AlertDescription>
                      </Alert>
                    )}

                    {isCSPSolving && (
                      <div className="text-center py-4">
                        <Clock className="w-8 h-8 animate-spin mx-auto mb-2 text-purple-500" />
                        <p className="text-purple-600 font-medium">CSP Solver is running...</p>
                        <p className="text-sm text-gray-600">This may take a few moments to complete.</p>
                      </div>
                    )}
                  </div>

                  <div className="mt-6 flex justify-end">
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white" disabled={isCSPSolving}>
                      {isCSPSolving ? "Running..." : "Close"}
                    </Button>
                  </div>
                </>
              )}

              {/* Conflicts Analysis Modal */}
              {modalType === "conflicts" && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <AlertCircle className="w-5 h-5 mr-2 text-red-500" />
                    Schedule Conflict Analysis
                  </h2>

                  {detectedConflicts.length === 0 ? (
                    <div className="text-center py-8">
                      <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                      <h3 className="text-lg font-medium text-green-800">No Conflicts Detected!</h3>
                      <p className="text-green-600 mt-2">Your schedule meets all constraints and has no conflicts.</p>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {detectedConflicts.map((conflict, index) => (
                        <Alert
                          key={index}
                          className={`border-l-4 ${
                            conflict.severity === "high"
                              ? "border-red-500 bg-red-50"
                              : conflict.severity === "medium"
                                ? "border-yellow-500 bg-yellow-50"
                                : "border-blue-500 bg-blue-50"
                          }`}
                        >
                          <AlertDescription>
                            <div className="flex items-start justify-between">
                              <div className="flex-1">
                                <div className="flex items-center mb-2">
                                  <Badge
                                    variant={
                                      conflict.severity === "high"
                                        ? "destructive"
                                        : conflict.severity === "medium"
                                          ? "default"
                                          : "secondary"
                                    }
                                    className="mr-2"
                                  >
                                    {conflict.type.replace("_", " ").toUpperCase()} - {conflict.severity.toUpperCase()}
                                  </Badge>
                                  {conflict.day && (
                                    <Badge variant="outline" className="mr-2">
                                      {conflict.day}
                                    </Badge>
                                  )}
                                </div>
                                <p className="text-sm font-medium">{conflict.description}</p>
                                {conflict.affectedSessions && conflict.affectedSessions.length > 0 && (
                                  <div className="mt-2 text-xs text-gray-600">
                                    <strong>Affected sessions:</strong>
                                    <ul className="list-disc list-inside mt-1">
                                      {conflict.affectedSessions.slice(0, 3).map((session, idx) => (
                                        <li key={idx}>
                                          {session.unit || session.unit_code} -{" "}
                                          {session.time || `${session.start_time}-${session.end_time}`}
                                          {session.group && ` (${session.group})`}
                                        </li>
                                      ))}
                                      {conflict.affectedSessions.length > 3 && (
                                        <li>... and {conflict.affectedSessions.length - 3} more</li>
                                      )}
                                    </ul>
                                  </div>
                                )}
                              </div>
                            </div>
                          </AlertDescription>
                        </Alert>
                      ))}
                    </div>
                  )}

                  <div className="mt-6 flex justify-end space-x-2">
                    <Button
                      onClick={analyzeScheduleConflicts}
                      disabled={isAnalyzing}
                      className="bg-orange-500 hover:bg-orange-600"
                    >
                      {isAnalyzing ? (
                        <>
                          <Clock className="w-4 h-4 mr-2 animate-spin" />
                          Analyzing...
                        </>
                      ) : (
                        "Re-analyze"
                      )}
                    </Button>
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white">
                      Close
                    </Button>
                  </div>
                </>
              )}

              {/* View Modal */}
              {modalType === "view" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Class Timetable</h2>
                  <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <strong>Day:</strong> {selectedClassTimetable.day}
                      </div>
                      <div>
                        <strong>Time:</strong> {selectedClassTimetable.start_time} - {selectedClassTimetable.end_time}
                      </div>
                      <div>
                        <strong>Duration:</strong>{" "}
                        {calculateDuration(selectedClassTimetable.start_time, selectedClassTimetable.end_time).toFixed(
                          1,
                        )}{" "}
                        hours
                      </div>
                      <div>
                        <strong>Mode:</strong>
                        <Badge
                          className={`ml-2 ${selectedClassTimetable.teaching_mode === "online" ? "bg-blue-100 text-blue-800" : "bg-green-100 text-green-800"}`}
                        >
                          {selectedClassTimetable.teaching_mode || "Physical"}
                        </Badge>
                      </div>
                    </div>

                    <hr />

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <strong>Unit:</strong> {selectedClassTimetable.unit_code} - {selectedClassTimetable.unit_name}
                      </div>
                      <div>
                        <strong>Credit Hours:</strong> {selectedClassTimetable.credit_hours || "N/A"}
                      </div>
                      <div>
                        <strong>Class:</strong> {selectedClassTimetable.class_name || "N/A"}
                      </div>
                      <div>
                        <strong>Group:</strong> {selectedClassTimetable.group_name || "All Groups"}
                      </div>
                    </div>

                    <hr />

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <strong>Venue:</strong> {selectedClassTimetable.venue}
                      </div>
                      <div>
                        <strong>Location:</strong> {selectedClassTimetable.location}
                      </div>
                      <div>
                        <strong>Students:</strong> {selectedClassTimetable.no}
                      </div>
                      <div>
                        <strong>Lecturer:</strong> {selectedClassTimetable.lecturer}
                      </div>
                    </div>
                  </div>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">
                    Close
                  </Button>
                </>
              )}

              {/* Enhanced Create/Edit Modal */}
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
                    {/* Time Slot Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Time Slot *</label>
                      <select
                        value={formState.start_time === "" ? "Random Time Slot (auto-assign)" : formState.start_time}
                        onChange={(e) => {
                          if (e.target.value === "Random Time Slot (auto-assign)") {
                            setFormState((prev) => ({
                              ...prev!,
                              start_time: "",
                              end_time: "",
                              day: "",
                            }))
                          } else {
                            const slot = classtimeSlots.find((s) => s.start_time === e.target.value)
                            if (slot) {
                              setFormState((prev) => ({
                                ...prev!,
                                start_time: slot.start_time,
                                end_time: slot.end_time,
                                day: slot.day,
                              }))
                            }
                          }
                        }}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="Random Time Slot (auto-assign)">Random Time Slot (auto-assign)</option>
                        {classtimeSlots.map((slot) => (
                          <option key={slot.id} value={slot.start_time}>
                            {slot.day} {slot.start_time} - {slot.end_time} (
                            {calculateDuration(slot.start_time, slot.end_time).toFixed(1)}h)
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* School Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">School *</label>
                      <select
                        value={formState.school_id || ""}
                        onChange={(e) =>
                          handleCreateChange("school_id", e.target.value ? Number(e.target.value) : null)
                        }
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select School</option>
                        {schools.map((school) => (
                          <option key={school.id} value={school.id}>
                            {school.code ? `${school.code} - ` : ""}
                            {school.name}
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
                        {semesters?.map((semester) => (
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
                        onChange={(e) => handleClassChange(Number(e.target.value))}
                        className="w-full border rounded p-2"
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
                    </div>

                    {/* Group Selection with Enhanced Info */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Group{" "}
                        <span className="text-sm text-gray-500">
                          (Optional - Select which group this timetable is for)
                        </span>
                      </label>
                      <select
                        value={formState.group_id || ""}
                        onChange={(e) => handleGroupChange(Number(e.target.value))}
                        className="w-full border rounded p-2"
                        disabled={!formState.class_id}
                      >
                        <option value="">No specific group (applies to all groups)</option>
                        {filteredGroups.map((group) => (
                          <option key={group.id} value={group.id}>
                            {group.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Enhanced Group Daily Hours Summary */}
                    {formState.group_id && (
                      <div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 className="font-medium text-blue-800 mb-3 flex items-center">
                          <Users className="w-4 h-4 mr-2" />
                          Group Schedule Analysis
                        </h4>
                        {(() => {
                          const dailyStats = getGroupDailyHoursSummary(formState.group_id, classTimetables)
                          return Object.keys(dailyStats).length > 0 ? (
                            <div className="space-y-2">
                              <div className="grid grid-cols-2 gap-2 text-sm">
                                {Object.entries(dailyStats).map(([day, stats]) => (
                                  <div
                                    key={day}
                                    className="flex justify-between items-center p-2 bg-white rounded border"
                                  >
                                    <span className="font-medium">{day}:</span>
                                    <div className="text-right">
                                      <div
                                        className={`font-bold ${
                                          stats.hours > constraints.maxHoursPerDay
                                            ? "text-red-600"
                                            : stats.hours < constraints.minHoursPerDay
                                              ? "text-yellow-600"
                                              : "text-green-600"
                                        }`}
                                      >
                                        {stats.hours.toFixed(1)}h
                                      </div>
                                      <div className="text-xs text-gray-600">
                                        {stats.physical}P / {stats.online}O
                                      </div>
                                    </div>
                                  </div>
                                ))}
                              </div>
                              <div className="mt-3 text-xs text-blue-700 bg-blue-100 p-2 rounded">
                                <div className="grid grid-cols-2 gap-2">
                                  <div>• Min: {constraints.minHoursPerDay}h per day</div>
                                  <div>• Max: {constraints.maxHoursPerDay}h per day</div>
                                  <div>• Max Physical: {constraints.maxPhysicalPerDay} per day</div>
                                  <div>• Max Online: {constraints.maxOnlinePerDay} per day</div>
                                </div>
                                {constraints.requireMixedMode && (
                                  <div className="mt-1">• Mixed mode required (both physical and online)</div>
                                )}
                              </div>
                            </div>
                          ) : (
                            <p className="text-blue-600 text-sm">No classes scheduled yet for this group.</p>
                          )
                        })()}
                      </div>
                    )}

                    {/* Teaching Mode Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Teaching Mode *</label>
                      <select
                        value={formState.teaching_mode || "physical"}
                        onChange={(e) => handleCreateChange("teaching_mode", e.target.value)}
                        className="w-full border rounded p-2"
                      >
                        <option value="physical">Physical</option>
                        <option value="online">Online</option>
                      </select>
                    </div>

                    {/* Class Assignment Info */}
                    {formState.class_id && (
                      <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded text-sm">
                        <div className="flex items-center text-green-700">
                          <CheckCircle className="w-4 h-4 mr-2" />
                          <span>
                            {formState.group_id
                              ? `This timetable will be assigned to ${classes.find((c) => c.id === formState.class_id)?.name} - Group ${filteredGroups.find((g) => g.id === formState.group_id)?.name}`
                              : `This timetable will apply to all groups in ${classes.find((c) => c.id === formState.class_id)?.name}`}
                          </span>
                        </div>
                      </div>
                    )}

                    {/* Loading State */}
                    {isLoading && (
                      <div className="text-center py-3 mb-4">
                        <div className="inline-flex items-center">
                          <Clock className="w-4 h-4 mr-2 animate-spin" />
                          <span>Loading units...</span>
                        </div>
                      </div>
                    )}

                    {/* Error Message */}
                    {errorMessage && (
                      <Alert className="mb-4 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-600">{errorMessage}</AlertDescription>
                      </Alert>
                    )}

                    {/* Unit Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                      <select
                        value={formState.unit_id || ""}
                        onChange={(e) => handleUnitChange(Number(e.target.value))}
                        className="w-full border rounded p-2"
                        disabled={!formState.class_id || isLoading}
                        required
                      >
                        <option value="">Select Unit</option>
                        {filteredUnits && filteredUnits.length > 0 ? (
                          filteredUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                              {unit.code} - {unit.name} ({unit.credit_hours || 3} credits, {unit.student_count || 0}{" "}
                              students)
                            </option>
                          ))
                        ) : (
                          <option value="" disabled>
                            {formState.class_id ? "No units available for this class" : "Please select a class first"}
                          </option>
                        )}
                      </select>
                    </div>

                    {/* Unit Details */}
                    <div className="grid grid-cols-2 gap-4 mb-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Unit Code</label>
                        <input
                          type="text"
                          value={formState.unit_code || ""}
                          className="w-full border rounded p-2 bg-gray-50"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Number of Students</label>
                        <input
                          type="number"
                          value={formState.no}
                          className="w-full border rounded p-2 bg-gray-50"
                          readOnly
                        />
                      </div>
                    </div>

                    {/* Lecturer Selection */}
                    {unitLecturers.length > 0 && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Lecturer (from enrollments)
                        </label>
                        <select
                          value={formState.lecturer_id || ""}
                          onChange={(e) => {
                            const selectedLecturer = lecturers.find((l) => l.id === Number(e.target.value))
                            if (selectedLecturer) {
                              setFormState((prev) => ({
                                ...prev!,
                                lecturer_id: Number(e.target.value),
                                lecturer_name: selectedLecturer.name,
                                lecturer: selectedLecturer.name,
                              }))
                            }
                          }}
                          className="w-full border rounded p-2"
                        >
                          <option value="">Select Lecturer</option>
                          {unitLecturers.map((lecturer) => (
                            <option key={lecturer.id} value={lecturer.id}>
                              {lecturer.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}

                    {/* Manual Lecturer Input */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Lecturer Name {unitLecturers.length > 0 ? "(or enter manually)" : "*"}
                      </label>
                      <input
                        type="text"
                        value={formState.lecturer}
                        onChange={(e) => handleCreateChange("lecturer", e.target.value)}
                        className="w-full border rounded p-2"
                        placeholder="Enter lecturer name"
                        required={unitLecturers.length === 0}
                      />
                    </div>

                    {/* Venue Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                      <select
                        value={formState.venue}
                        onChange={(e) => {
                          const venueName = e.target.value
                          if (venueName === "") {
                            setFormState((prev) => ({
                              ...prev!,
                              venue: "",
                              location: "",
                            }))
                            setCapacityWarning(null)
                          } else {
                            const selectedClassroom = classrooms.find((c) => c.name === venueName)
                            if (selectedClassroom) {
                              setFormState((prev) => ({
                                ...prev!,
                                venue: venueName,
                                location: selectedClassroom.location,
                              }))

                              // Check capacity
                              if (formState.no > selectedClassroom.capacity) {
                                setCapacityWarning(
                                  `Warning: Venue ${venueName} has capacity ${selectedClassroom.capacity}, but ${formState.no} students are enrolled (exceeding by ${formState.no - selectedClassroom.capacity})`,
                                )
                              } else {
                                setCapacityWarning(null)
                              }
                            }
                          }
                        }}
                        className="w-full border rounded p-2"
                      >
                        <option value="">Random Venue (auto-assign)</option>
                        {classrooms?.map((classroom) => (
                          <option key={classroom.id} value={classroom.name}>
                            {classroom.name} (Capacity: {classroom.capacity}, Location: {classroom.location})
                          </option>
                        )) || null}
                      </select>
                      <span className="text-xs text-gray-500 block mt-1">
                        Leave blank to assign a random available venue with enough capacity.
                      </span>
                    </div>

                    {/* Capacity Warning */}
                    {capacityWarning && (
                      <Alert className="mb-4 bg-red-50 border-red-200">
                        <AlertCircle className="h-4 w-4 text-red-500" />
                        <AlertDescription className="text-red-500">{capacityWarning}</AlertDescription>
                      </Alert>
                    )}

                    {/* Enhanced Constraint Validation Warning */}
                    {conflictWarning && (
                      <Alert className="mb-4 bg-yellow-50 border-yellow-200">
                        <AlertCircle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription>
                          <div className="text-yellow-700">
                            <strong>Constraint Validation:</strong>
                            <div className="mt-1">{conflictWarning}</div>
                          </div>
                        </AlertDescription>
                      </Alert>
                    )}

                    {/* Location (Read-only) */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                      <input
                        type="text"
                        value={formState.location}
                        className="w-full border rounded p-2 bg-gray-50"
                        readOnly
                      />
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
                      <Button
                        type="submit"
                        disabled={isSubmitting || (conflictWarning && conflictWarning.includes("cannot"))}
                        className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {isSubmitting ? (
                          <>
                            <Clock className="w-4 h-4 mr-2 animate-spin" />
                            {modalType === "create" ? "Creating..." : "Updating..."}
                          </>
                        ) : (
                          <>
                            <CheckCircle className="w-4 h-4 mr-2" />
                            {modalType === "create" ? "Create Timetable" : "Update Timetable"}
                          </>
                        )}
                      </Button>
                    </div>
                  </form>
                </>
              )}

              {/* Delete Confirmation Modal */}
              {modalType === "delete" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <XCircle className="w-5 h-5 mr-2 text-red-500" />
                    Delete Class Timetable
                  </h2>
                  <div className="mb-6">
                    <p className="text-gray-700 mb-4">Are you sure you want to delete this timetable entry?</p>
                    <div className="bg-gray-50 p-4 rounded border">
                      <div className="grid grid-cols-2 gap-2 text-sm">
                        <div>
                          <strong>Unit:</strong> {selectedClassTimetable.unit_code}
                        </div>
                        <div>
                          <strong>Day:</strong> {selectedClassTimetable.day}
                        </div>
                        <div>
                          <strong>Time:</strong> {selectedClassTimetable.start_time} - {selectedClassTimetable.end_time}
                        </div>
                        <div>
                          <strong>Venue:</strong> {selectedClassTimetable.venue}
                        </div>
                        <div>
                          <strong>Class:</strong> {selectedClassTimetable.class_name || "N/A"}
                        </div>
                        <div>
                          <strong>Group:</strong> {selectedClassTimetable.group_name || "All Groups"}
                        </div>
                      </div>
                    </div>
                    <p className="text-red-600 text-sm mt-3">
                      <strong>Warning:</strong> This action cannot be undone.
                    </p>
                  </div>
                  <div className="flex justify-end space-x-3">
                    <Button
                      type="button"
                      onClick={handleCloseModal}
                      className="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2"
                    >
                      Cancel
                    </Button>
                    <Button
                      onClick={() => {
                        handleDelete(selectedClassTimetable.id)
                        handleCloseModal()
                      }}
                      className="bg-red-500 hover:bg-red-600 text-white px-6 py-2"
                    >
                      <XCircle className="w-4 h-4 mr-2" />
                      Delete Timetable
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
