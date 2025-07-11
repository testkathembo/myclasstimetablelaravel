"use client"

import { useState } from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  Home,
  Users,
  Building,
  Calendar,
  ClipboardList,
  Layers,
  ClipboardCheck,
  Settings,
  BookOpen,
  GraduationCap,
  Bell,
  BarChart3,
  Clock,
} from "lucide-react"

export default function Sidebar() {
  const { auth } = usePage().props as any
  const user = auth.user

  // Enhanced permission checking function with debugging
  function hasPermission(user: any, permission: string): boolean {
    if (!user) {
      console.log("No user found for permission check:", permission)
      return false
    }

    if (!user.permissions) {
      console.log("No permissions array found for user:", user.first_name, "checking:", permission)
      return false
    }

    const hasIt = Array.isArray(user.permissions) && user.permissions.includes(permission)
    console.log(`Permission check: ${permission} = ${hasIt}`, "User permissions:", user.permissions)
    return hasIt
  }

  // Enhanced role checking function with debugging
  function hasRole(user: any, role: string): boolean {
    if (!user || !user.roles) {
      console.log("No user or roles found for role check:", role)
      return false
    }

    if (Array.isArray(user.roles)) {
      const hasIt = user.roles.some((r: any) => (typeof r === "string" ? r === role : r.name === role))
      console.log(`Role check: ${role} = ${hasIt}`, "User roles:", user.roles)
      return hasIt
    }

    return false
  }

  const hasAnyRole = (roles: string[]) => {
    return roles.some((role) => hasRole(user, role))
  }

  // Get user's assigned school
  const getUserSchool = () => {
    return user?.school || user?.assigned_school || user?.school_id || null
  }

  // Check if user is Faculty Admin for a specific school
  const isFacultyAdminForSchool = () => {
    return hasRole(user, "Faculty Admin") && getUserSchool()
  }

  // Get school name for display
  const getSchoolName = () => {
    const school = getUserSchool()
    if (typeof school === "object" && school?.name) {
      return school.name
    }
    if (typeof school === "string") {
      return school
    }
    return "Your School"
  }

  // Check if user has Faculty Admin role for SCES
  const isFacultyAdminSCES = () => {
    return hasRole(user, "Faculty Admin - SCES")
  }

  // Check if user has Faculty Admin role for SBS
  const isFacultyAdminSBS = () => {
    return hasRole(user, "Faculty Admin - SBS")
  }

  const [openSchool, setOpenSchool] = useState<string | null>(null)
  const [openProgram, setOpenProgram] = useState<string | null>(null)
  const [openSection, setOpenSection] = useState<string | null>(null)

  const toggleSchool = (school: string) => {
    setOpenSchool(openSchool === school ? null : school)
  }

  const toggleProgram = (program: string) => {
    setOpenProgram(openProgram === program ? null : program)
  }

  const toggleSection = (section: string) => {
    setOpenSection(openSection === section ? null : section)
  }

  return (
    <div className="w-64 bg-blue-800 text-white h-full flex flex-col">
      <div className="p-4 border-b border-gray-700">
        <h1 className="text-xl font-bold">Timetabling System</h1>
      </div>

      <div className="flex-1 overflow-y-auto py-4">
        <nav className="px-2 space-y-1">
          {/* Dashboard - Available to all with permission */}
          {hasPermission(user, "view-dashboard") && (
            <Link
              href="/dashboard"
              className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
            >
              <Home className="mr-3 h-5 w-5" />
              Dashboard
            </Link>
          )}

          {/* Administration Section */}
          {(hasPermission(user, "manage-users") ||
            hasPermission(user, "manage-roles") ||
            hasPermission(user, "manage-permissions") ||
            hasPermission(user, "manage-settings")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Administration</p>
              {hasPermission(user, "manage-users") && (
                <Link
                  href="/users"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Users className="mr-3 h-5 w-5" />
                  Users
                </Link>
              )}

              {hasPermission(user, "manage-roles") && (
                <Link
                  href="/roles"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Layers className="mr-3 h-5 w-5" />
                  Roles & Permissions
                </Link>
              )}

              {hasPermission(user, "manage-settings") && (
                <Link
                  href="/settings"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Settings className="mr-3 h-5 w-5" />
                  Settings
                </Link>
              )}
            </div>
          )}

          {/* Academic Management Section */}
          {(hasPermission(user, "manage-schools") ||
            hasPermission(user, "manage-programs") ||
            hasPermission(user, "manage-units") ||
            hasPermission(user, "manage-classes") ||
            hasPermission(user, "manage-enrollments") ||
            hasPermission(user, "manage-semesters") ||
            hasPermission(user, "manage-classrooms") ||
            // Add Faculty Admin SCES permissions
            isFacultyAdminSCES() ||
            isFacultyAdminSBS()) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic Management</p>
              {(hasPermission(user, "manage-schools") || isFacultyAdminSCES() || isFacultyAdminSBS()) && (
                <div>
                  <button
                    type="button"
                    className="flex items-center w-full px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                    onClick={() => toggleSchool("schools")}
                  >
                    <Building className="mr-3 h-5 w-5" />
                    Schools
                    <span className="ml-auto">{openSchool === "schools" ? "▲" : "▼"}</span>
                  </button>
                  {openSchool === "schools" && (
                    <div className="ml-6 mt-1 space-y-1">
                      {/* SCES Section - Show if user has SCES faculty admin role */}
                      {isFacultyAdminSCES() && (
                        <div>
                          <button
                            type="button"
                            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                            onClick={() => toggleProgram("sces")}
                          >
                            <GraduationCap className="mr-3 h-5 w-5" />
                            SCES
                            <span className="ml-auto">{openProgram === "sces" ? "▲" : "▼"}</span>
                          </button>
                          {openProgram === "sces" && (
                            <div className="ml-6 mt-1 space-y-1">
                              {/* {hasPermission(user, "view-faculty-dashboard-sces") && (
                                <Link
                                  href="/facultyadmin/sces"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <Home className="mr-3 h-5 w-5" />
                                  SCES Dashboard
                                </Link>
                              )} */}
                              {(hasPermission(user, "manage-programs") || hasPermission(user, "view-faculty-programs-sces")) && (
                                <Link
                                  href="/programs"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Programs
                                </Link>
                              )}
                              {(hasPermission(user, "manage-units") || hasPermission(user, "manage-faculty-units-sces")) && (
                                <Link
                                  href="/facultyadmin/sces/units"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Units
                                </Link>
                              )}
                              {(hasPermission(user, "view-faculty-enrollments-sces")) && (
                                <Link
                                  href="/facultyadmin/sces/enrollments"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Enrollments
                                </Link>
                              )}
                              {(hasPermission(user, "manage-classes") || hasPermission(user, "view-faculty-classes-sces")) && (
                                <Link
                                  href="/classes"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Classes
                                </Link>
                              )}
                              {(hasPermission(user, "manage-faculty-students-sces")) && (
                                <Link
                                  href="/facultyadmin/sces/students"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <Users className="mr-3 h-5 w-5" />
                                  Students
                                </Link>
                              )}
                              {(hasPermission(user, "manage-faculty-lecturers-sces")) && (
                                <Link
                                  href="/facultyadmin/sces/lecturers"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <Users className="mr-3 h-5 w-5" />
                                  Lecturers
                                </Link>
                              )}
                            </div>
                          )}
                        </div>
                      )}
                      
                      {/* SBS Section - Show if user has SBS faculty admin role */}
                      {isFacultyAdminSBS() && (
                        <div>
                          <button
                            type="button"
                            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                            onClick={() => toggleProgram("sbs")}
                          >
                            <GraduationCap className="mr-3 h-5 w-5" />
                            SBS
                            <span className="ml-auto">{openProgram === "sbs" ? "▲" : "▼"}</span>
                          </button>
                          {openProgram === "sbs" && (
                            <div className="ml-6 mt-1 space-y-1">
                              {hasPermission(user, "view-faculty-dashboard-sbs") && (
                                <Link
                                  href="/sbs/dashboard"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <Home className="mr-3 h-5 w-5" />
                                  SBS Dashboard
                                </Link>
                              )}
                              {(hasPermission(user, "manage-programs") || hasPermission(user, "manage-faculty-programs-sbs")) && (
                                <Link
                                  href="/programs"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Programs
                                </Link>
                              )}
                              {(hasPermission(user, "manage-units") || hasPermission(user, "manage-faculty-units-sbs")) && (
                                <Link
                                  href="/sbs/units"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Units
                                </Link>
                              )}
                              {(hasPermission(user, "manage-enrollments") || hasPermission(user, "manage-faculty-enrollments-sbs")) && (
                                <Link
                                  href="/sbs/enrollments"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Enrollments
                                </Link>
                              )}
                              {(hasPermission(user, "manage-classes") || hasPermission(user, "manage-faculty-classes-sbs")) && (
                                <Link
                                  href="/classes"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Classes
                                </Link>
                              )}
                            </div>
                          )}
                        </div>
                      )}

                      {/* Original admin sections - only show for admin users */}
                      {hasPermission(user, "manage-schools") && !isFacultyAdminSCES() && !isFacultyAdminSBS() && (
                        <div>
                          <button
                            type="button"
                            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                            onClick={() => toggleProgram("sces")}
                          >
                            <GraduationCap className="mr-3 h-5 w-5" />
                            SCES
                            <span className="ml-auto">{openProgram === "sces" ? "▲" : "▼"}</span>
                          </button>
                          {openProgram === "sces" && (
                            <div className="ml-6 mt-1 space-y-1">
                              {hasPermission(user, "manage-programs") && (
                                <Link
                                  href="/programs"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Programs
                                </Link>
                              )}
                              {hasPermission(user, "manage-units") && (
                                <Link
                                  href="/FacultyAdmin/sces/units"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Units
                                </Link>
                              )}
                              {hasPermission(user, "manage-enrollments") && (
                                <Link
                                  href="FacultyAdmin/sces/enrollments"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Enrollments
                                </Link>
                              )}
                              {hasPermission(user, "manage-classes") && (
                                <Link
                                  href="/classes"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Classes
                                </Link>
                              )}
                            </div>
                          )}
                           <button
                            type="button"
                            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                            onClick={() => toggleProgram("sbs")}
                          >
                            <GraduationCap className="mr-3 h-5 w-5" />
                            SBS
                            <span className="ml-auto">{openProgram === "sbs" ? "▲" : "▼"}</span>
                          </button>
                          {openProgram === "sbs" && (
                            <div className="ml-6 mt-1 space-y-1">
                              {hasPermission(user, "manage-programs") && (
                                <Link
                                  href="/programs"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Programs
                                </Link>
                              )}
                              {hasPermission(user, "manage-units") && (
                                <Link
                                  href="/FacultyAdmin/sbs/units"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <BookOpen className="mr-3 h-5 w-5" />
                                  Units
                                </Link>
                              )}
                              {hasPermission(user, "manage-enrollments") && (
                                <Link
                                  href="/enrollments"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Enrollments
                                </Link>
                              )}
                              {hasPermission(user, "manage-classes") && (
                                <Link
                                  href="/classes"
                                  className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                  <ClipboardList className="mr-3 h-5 w-5" />
                                  Classes
                                </Link>
                              )}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}
             
              {hasPermission(user, "manage-semesters") && (
                <Link
                  href="/semesters"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Semesters
                </Link>
              )}

              {hasPermission(user, "manage-classrooms") && (
                <Link
                  href="/classrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Classrooms
                </Link>
              )}
            </div>
          )}

          {/* Timetables Section */}
          {(hasPermission(user, "manage-timetables") ||
            hasPermission(user, "manage-class-timetables") ||
            hasPermission(user, "manage-exam-timetables") ||
            hasPermission(user, "manage-exam-rooms") ||
            hasPermission(user, "manage-time-slots")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Timetables</p>
              {hasPermission(user, "manage-timetables") && (
                <Link
                  href="/timetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Manage Timetables
                </Link>
              )}

              {hasPermission(user, "manage-class-timetables") && (
                <Link
                  href="/classtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Class Timetables
                </Link>
              )}

              {hasPermission(user, "manage-exam-timetables") && (
                <Link
                  href="/examtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Exam Timetables
                </Link>
              )}

              {hasPermission(user, "manage-exam-rooms") && (
                <Link
                  href="/examrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Exam Rooms
                </Link>
              )}

              {/* Time Slots - Added here */}
              {hasPermission(user, "manage-time-slots") && (
                <Link
                  href="/timeslots"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Clock className="mr-3 h-5 w-5" />
                  Time Slots
                </Link>
              )}
            </div>
          )}

          {/* Student Section */}
          {hasRole(user, "Student") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Student Portal</p>
              {hasPermission(user, "view-own-class-timetables") && (
                <Link
                  href="/my-timetable"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  My Timetable
                </Link>
              )}

              {hasPermission(user, "view-enrollments") && (
                <Link
                  href="/enroll"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardList className="mr-3 h-5 w-5" />
                  Enrollment
                </Link>
              )}
            </div>
          )}

          {/* Lecturer Section */}
          {hasRole(user, "Lecturer") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Lecturer Portal</p>
              {hasPermission(user, "view-own-class-timetables") && (
                <Link
                  href="/my-classes"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  My Classes
                </Link>
              )}

              {hasPermission(user, "download-own-class-timetables") && (
                <Link
                  href="/my-timetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  My Timetables
                </Link>
              )}

              {hasPermission(user, "view-own-exam-timetables") && (
                <Link
                  href="/examsupervision"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  My Exam Timetables
                </Link>
              )}
            </div>
          )}

          {/* Faculty Admin Section - School-Specific Management */}
          {isFacultyAdminForSchool() && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                {getSchoolName()} Management
              </p>
              {hasPermission(user, "view-programs") && (
                <Link
                  href={`/faculty/programs?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <GraduationCap className="mr-3 h-5 w-5" />
                  School Programs
                </Link>
              )}

              {hasPermission(user, "manage-units") && (
                <Link
                  href={`/faculty/units?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <BookOpen className="mr-3 h-5 w-5" />
                  School Units
                </Link>
              )}

              {hasPermission(user, "manage-classes") && (
                <Link
                  href={`/faculty/classes?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardList className="mr-3 h-5 w-5" />
                  School Classes
                </Link>
              )}

              {hasPermission(user, "view-users") && (
                <Link
                  href={`/faculty/lecturers?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Users className="mr-3 h-5 w-5" />
                  School Lecturers
                </Link>
              )}

              {hasPermission(user, "view-users") && (
                <Link
                  href={`/faculty/students?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <GraduationCap className="mr-3 h-5 w-5" />
                  School Students
                </Link>
              )}

              {hasPermission(user, "view-enrollments") && (
                <Link
                  href={`/faculty/enrollments?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardList className="mr-3 h-5 w-5" />
                  School Enrollments
                </Link>
              )}

              {hasPermission(user, "manage-class-timetables") && (
                <Link
                  href={`/faculty/timetables?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  School Timetables
                </Link>
              )}

              {hasPermission(user, "manage-classrooms") && (
                <Link
                  href={`/faculty/classrooms?school=${getUserSchool()?.id || getUserSchool()}`}
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  School Classrooms
                </Link>
              )}
            </div>
          )}

          {/* Exam Office Section */}
          {hasRole(user, "Exam office") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Exam Office</p>
              {hasPermission(user, "manage-exam-timetables") && (
                <Link
                  href="/examtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Exam Timetables
                </Link>
              )}

              {hasPermission(user, "manage-exam-rooms") && (
                <Link
                  href="/examrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Exam Rooms
                </Link>
              )}

              {hasPermission(user, "manage-time-slots") && (
                <Link
                  href="/timeslots"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Clock className="mr-3 h-5 w-5" />
                  Time Slots
                </Link>
              )}

              {hasPermission(user, "process-exam-timetables") && (
                <Link
                  href="/exam-processing"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Process Exam Timetables
                </Link>
              )}

              {hasPermission(user, "solve-exam-conflicts") && (
                <Link
                  href="/exam-conflicts"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Solve Exam Conflicts
                </Link>
              )}
            </div>
          )}

          {/* Notifications Section */}
          {(hasPermission(user, "manage-notifications") ||
            hasPermission(user, "view-notifications") ||
            hasPermission(user, "view-own-notifications")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Notifications</p>
              {hasPermission(user, "manage-notifications") && (
                <Link
                  href="/notifications/manage"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Bell className="mr-3 h-5 w-5" />
                  Manage Notifications
                </Link>
              )}

              {hasPermission(user, "view-notifications") && (
                <Link
                  href="/notifications"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Bell className="mr-3 h-5 w-5" />
                  All Notifications
                </Link>
              )}

              {hasPermission(user, "view-own-notifications") && (
                <Link
                  href="/my-notifications"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Bell className="mr-3 h-5 w-5" />
                  My Notifications
                </Link>
              )}
            </div>
          )}

          {/* Reports Section */}
          {hasPermission(user, "generate-reports") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</p>
              <Link
                href="/reports"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BarChart3 className="mr-3 h-5 w-5" />
                Generate Reports
              </Link>
            </div>
          )}
        </nav>
      </div>
    </div>
  )
}