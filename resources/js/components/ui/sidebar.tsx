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

  // Get role names for display
  const getRoleNames = () => {
    if (!user || !user.roles) return "None"

    if (Array.isArray(user.roles)) {
      if (typeof user.roles[0] === "string") {
        return user.roles.join(", ")
      }
      if (typeof user.roles[0] === "object") {
        return user.roles.map((r: any) => r.name).join(", ")
      }
    }

    if (typeof user.roles === "object" && user.roles.name) {
      return user.roles.name
    }

    return JSON.stringify(user.roles)
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
          {/* Dashboard - Available to all */}
          <Link
            href="/dashboard"
            className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
          >
            <Home className="mr-3 h-5 w-5" />
            Dashboard
          </Link>

          {/* Admin Section - Check individual permissions with correct names */}
          {hasAnyRole(["Admin"]) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Administration</p>

              {/* Users - Check "manage users" permission (with space) */}
              {user.permissions && hasPermission(user, "manage-users") && (
                <Link
                  href="/users"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Users className="mr-3 h-5 w-5" />
                  Users
                </Link>
              )}

              {/* Roles - Check "manage roles" permission (with space) */}
              {user.permissions && hasPermission(user, "manage-roles") && (
                <Link
                  href="/roles"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Layers className="mr-3 h-5 w-5" />
                  Roles & Permissions
                </Link>
              )}

              {/* Settings - Check "manage settings" permission (with space) */}
              {user.permissions && hasPermission(user, "manage settings") && (
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

          {/* Academic Management - Check individual permissions with correct names */}
          {hasAnyRole(["Admin"]) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic Management</p>

              {/* Schools - Check "manage schools" permission (with space) */}
              {user.permissions && hasPermission(user, "manage schools") && (
                <Link
                  href="/schools"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Schools
                </Link>
              )}

              {/* Programs - Check "manage programs" permission (with space) */}
              {user.permissions && hasPermission(user, "manage programs") && (
                <Link
                  href="/programs"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <GraduationCap className="mr-3 h-5 w-5" />
                  Programs
                </Link>
              )}

              {/* Units - Check "manage units" permission (with space) */}
              {user.permissions && hasPermission(user, "manage units") && (
                <Link
                  href="/units"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <BookOpen className="mr-3 h-5 w-5" />
                  Units
                </Link>
              )}

              {/* Classes - Check "manage classes" permission (with space) */}
              {user.permissions && hasPermission(user, "manage classes") && (
                <Link
                  href="/classes"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardList className="mr-3 h-5 w-5" />
                  Classes
                </Link>
              )}

              {/* Enrollments - Check "manage enrollments" permission (with space) */}
              {user.permissions && hasPermission(user, "manage enrollments") && (
                <Link
                  href="/enrollments"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardList className="mr-3 h-5 w-5" />
                  Enrollments
                </Link>
              )}

              {/* Semesters - Check "manage semesters" permission (with space) */}
              {user.permissions && hasPermission(user, "manage semesters") && (
                <Link
                  href="/semesters"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Semesters
                </Link>
              )}

              {/* Classrooms - Check "manage classrooms" permission (with space) */}
              {user.permissions && hasPermission(user, "manage classrooms") && (
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

          {/* Timetable Management - Check individual permissions with correct names */}
          {hasAnyRole(["Admin"]) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Timetables</p>

              {/* Manage Timetables - Check "manage timetables" permission (with space) */}
              {user.permissions && hasPermission(user, "manage timetables") && (
                <Link
                  href="/timetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Manage Timetables
                </Link>
              )}

              {/* Class Timetables - Check "manage class timetables" permission (with space) */}
              {user.permissions && hasPermission(user, "manage class timetables") && (
                <Link
                  href="/classtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Class Timetables
                </Link>
              )}

              {/* Exam Timetables - Check "manage exam timetables" permission (with space) */}
              {user.permissions && hasPermission(user, "manage exam timetables") && (
                <Link
                  href="/examtimetable"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Exam Timetables
                </Link>
              )}

              {/* Exam Rooms - Check "manage exam rooms" permission (with space) */}
              {user.permissions && hasPermission(user, "manage exam rooms") && (
                <Link
                  href="/examrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Exam Rooms
                </Link>
              )}
            </div>
          )}

          {/* Student Section */}
          {hasRole(user, "Student") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Student Portal</p>
              <Link
                href="/my-timetable"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Timetable
              </Link>
              <Link
                href="/enroll"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <ClipboardList className="mr-3 h-5 w-5" />
                Enrollment
              </Link>
            </div>
          )}

          {/* Lecturer Section */}
          {hasRole(user, "Lecturer") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Lecturer Portal</p>
              <Link
                href="/my-classes"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Classes
              </Link>
            </div>
          )}

          {/* Faculty Admin Section */}
          {hasRole(user, "Faculty Admin") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Faculty Admin</p>
              <Link
                href="/faculty/lecturers"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Users className="mr-3 h-5 w-5" />
                Faculty Lecturers
              </Link>
              <Link
                href="/faculty/students"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <GraduationCap className="mr-3 h-5 w-5" />
                Faculty Students
              </Link>
              <Link
                href="/faculty/enrollments"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <ClipboardList className="mr-3 h-5 w-5" />
                Faculty Enrollments
              </Link>
            </div>
          )}

          {/* Exam Office Section */}
          {hasRole(user, "Exam Office") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Exam Office</p>
              <Link
                href="/examtimetable"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <ClipboardCheck className="mr-3 h-5 w-5" />
                Exam Timetables
              </Link>
              <Link
                href="/examrooms"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Building className="mr-3 h-5 w-5" />
                Exam Rooms
              </Link>
            </div>
          )}
        </nav>
      </div>

      {/* Enhanced Debug Info
      {user && (
        <div className="pt-4 px-4 text-xs text-gray-400 border-t border-gray-700">
          <p className="font-semibold">Debug Info:</p>
          <p>User: {user.first_name}</p>
          <p>Email: {user.email}</p>
          <p>Permissions: {user.permissions ? user.permissions.length : 0}</p>
          <p>Raw Permissions: {JSON.stringify(user.permissions || [])}</p>
          <p>Roles: {JSON.stringify(user.roles || [])}</p>
          <p>Has Admin Role: {hasRole(user, "Admin") ? "Yes" : "No"}</p>
          <p>Can Manage Users: {hasPermission(user, "manage users") ? "Yes" : "No"}</p>
          <p>Can Manage Roles: {hasPermission(user, "manage roles") ? "Yes" : "No"}</p>
          <p>Auth Object Keys: {Object.keys(user).join(", ")}</p>
        </div>
      )} */}
    </div>
  )
}
