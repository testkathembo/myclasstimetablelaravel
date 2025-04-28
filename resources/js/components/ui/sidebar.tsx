"use client"

import { useEffect, useState } from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  LayoutDashboard,
  Users,
  Settings,
  Book,
  Building,
  MapPin,
  Layers,
  Calendar,
  FileSpreadsheet,
  BookOpen,
  DownloadCloud,
  Puzzle,
  Briefcase,
  Shield,
} from "lucide-react"

const Sidebar = () => {
  const { auth } = usePage().props as any
  const roles: string[] = auth?.user?.roles?.map((r: any) => r.name) ?? []
  const permissions: string[] = auth?.user?.permissions ?? []

  const hasRole = (role: string) => roles.includes(role)
  const can = (permission: string) => permissions.includes(permission)

  // Special case for Admin
  const [userPermissions, setUserPermissions] = useState<string[]>([])
  const [userRoles, setUserRoles] = useState<string[]>([])

  useEffect(() => {
    console.log("Current user permissions:", userPermissions)
    console.log("Current user roles:", userRoles)

    // Check specific permissions
    console.log("Has manage-create:", userPermissions.includes("manage-create"))
    console.log("Has create-actions:", userPermissions.includes("create-actions"))
    console.log("Has view-update:", userPermissions.includes("view-update"))
    console.log("Has update-actions:", userPermissions.includes("update-actions"))
    console.log("Has manage-delete:", userPermissions.includes("manage-delete"))
    console.log("Has delete-actions:", userPermissions.includes("delete-actions"))
  }, [userPermissions, userRoles])

  useEffect(() => {
    fetch("/user/roles-permissions", { credentials: "include" }) // Ensure session cookies are included
      .then((res) => res.json())
      .then((data) => {
        setUserRoles(data.roles || [])
        setUserPermissions(data.permissions || [])
      })
      .catch((error) => console.error("Error fetching roles & permissions:", error))
  }, [])
  return (
    <div className="bg-blue-800 text-white w-64 h-full flex flex-col">
      <div className="p-4 text-lg font-bold border-b border-blue-700">
        <h2 className="text-lg font-bold">Timetable Management</h2>
      </div>

      <nav className="flex-1 p-4 space-y-2">
        {/* Dashboard - available to all authenticated users */}

        {userPermissions.includes("view-dashboard") && (
          <Link href="/dashboard" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <LayoutDashboard className="h-5 w-5" />
            <span>Dashboard</span>
          </Link>
        )}

        {userPermissions.includes("manage-users") && (
          <Link href="/users" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Users className="h-5 w-5" />
            <span>Users</span>
          </Link>
        )}

        {userPermissions.includes("manage-roles") && (
          <Link href="/roles" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Shield className="h-5 w-5" />
            <span>Roles</span>
          </Link>
        )}

        {userPermissions.includes("manage-faculties") && (
          <Link href="/faculties" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Building className="h-5 w-5" />
            <span>Faculties</span>
          </Link>
        )}

        {userPermissions.includes("manage-units") && (
          <Link
            href={can("manage-units") ? "/units" : can("manage-faculty-units") ? "/faculty/units" : "/units"}
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <Book className="h-5 w-5" />
            <span>Units</span>
          </Link>
        )}

        {userPermissions.includes("manage-classrooms") && (
          <Link href="/classrooms" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <MapPin className="h-5 w-5" />
            <span>Exam Rooms</span>
          </Link>
        )}

        {userPermissions.includes("manage-semesters") && (
          <Link href="/all-semesters" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>Semesters</span>
          </Link>
        )}

        {userPermissions.includes("manage-enrollments") && (
          <Link href="/enrollments" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Book className="h-5 w-5" />
            <span>Enrollment</span>
          </Link>
        )}

        {userPermissions.includes("manage-time-slots") && (
          <Link href="/timeslots" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Layers className="h-5 w-5" />
            <span>Time Slots</span>
          </Link>
        )}

        {userPermissions.includes("manage-timetable") && (
          <Link href="/examtimetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <FileSpreadsheet className="h-5 w-5" />
            <span>Exam Timetable</span>
          </Link>
        )}

        {userPermissions.includes("process-timetable") && (
          <Link href="/process-timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Puzzle className="h-5 w-5" />
            <span>Process Timetable</span>
          </Link>
        )}

        {userPermissions.includes("solve-conflicts") && (
          <Link href="/solve-conflicts" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Briefcase className="h-5 w-5" />
            <span>Solve Conflicts</span>
          </Link>
        )}

        {userPermissions.includes("view-own-timetable") && (
          <Link href="/lecturer/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Timetable</span>
          </Link>
        )}

        {userPermissions.includes("download-timetable") && (
          <Link
            href={
              can("download-timetable")
                ? "/download-timetable"
                : can("download-faculty-timetable")
                  ? "/faculty/timetable/download"
                  : hasRole("Lecturer")
                    ? "/lecturer/timetable/download"
                    : "/student/timetable/download"
            }
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <DownloadCloud className="h-5 w-5" />
            <span>Download Timetable</span>
          </Link>
        )}

        {userPermissions.includes("manage-settings") && (
          <Link href="/settings" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Settings className="h-5 w-5" />
            <span>Settings</span>
          </Link>
        )}

        {/* Lecturer View Own Units */}
        {hasRole("Lecturer") && can("view-own-units") && (
          <Link href="/lecturer/units" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <BookOpen className="h-5 w-5" />
            <span>My Units</span>
          </Link>
        )}

        {/* Student View Own Timetable */}
        {hasRole("Student") && can("view-own-timetable") && (
          <Link href="/student/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Exam Schedule</span>
          </Link>
        )}

        {/* Student View Own Units */}
        {hasRole("Student") && can("view-own-units") && (
          <Link href="/student/units" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <BookOpen className="h-5 w-5" />
            <span>My Units</span>
          </Link>
        )}
      </nav>
    </div>
  )
}

export default Sidebar
