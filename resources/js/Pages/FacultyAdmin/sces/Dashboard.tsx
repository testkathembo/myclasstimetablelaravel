import { Head, Link } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import {
  Users,
  BookOpen,
  Calendar,
  GraduationCap,
  UserCheck,
  School,
  Clock,
  AlertCircle,
  BarChart3,
  PlusCircle,
  Building,
} from "lucide-react"

interface FacultyStatistics {
  totalStudents: { count: number; growthRate: number; period: string }
  totalLecturers: { count: number; growthRate: number; period: string }
  totalUnits: { count: number; growthRate: number; period: string }
  activeEnrollments: { count: number; growthRate: number; period: string }
}

interface RecentActivity {
  id: number
  type: "enrollment" | "lecturer_assignment" | "unit_creation" | "timetable_update"
  description: string
  user_name?: string
  unit_name?: string
  created_at: string
}

interface Props {
  statistics?: FacultyStatistics
  currentSemester?: {
    id: number
    name: string
    start_date?: string
    end_date?: string
    is_active: boolean
  }
  facultyInfo?: {
    name: string
    code: string
    totalPrograms: number
    totalClasses: number
  }
  schoolCode: string
  schoolName: string
  recentActivities?: RecentActivity[]
  pendingApprovals?: {
    enrollments: number
    lecturerRequests: number
    unitChanges: number
  }
  error?: string
}

export default function Dashboard({
  statistics,
  currentSemester,
  facultyInfo,
  schoolCode,
  schoolName,
  recentActivities = [],
  pendingApprovals,
  error,
}: Props) {
  // Helper function to format growth rate display
  const formatGrowthRate = (rate: number, period: string) => {
    const isPositive = rate >= 0
    const colorClass = isPositive ? "text-green-600" : "text-red-600"
    const sign = isPositive ? "+" : ""
    return (
      <div className="mt-4">
        <span className={`${colorClass} text-sm font-medium`}>
          {sign}
          {rate}%
        </span>
        <span className="text-slate-500 text-sm ml-1">{period}</span>
      </div>
    )
  }

  // Get school-specific colors and branding
  const getSchoolTheme = (code: string) => {
    const themes = {
      SCES: {
        primary: "from-blue-500 to-indigo-600",
        accent: "bg-blue-100 text-blue-800",
        gradient: "from-blue-50 to-indigo-50",
      },
      SBS: {
        primary: "from-green-500 to-emerald-600",
        accent: "bg-green-100 text-green-800",
        gradient: "from-green-50 to-emerald-50",
      },
      SLS: {
        primary: "from-purple-500 to-violet-600",
        accent: "bg-purple-100 text-purple-800",
        gradient: "from-purple-50 to-violet-50",
      },
      TOURISM: {
        primary: "from-orange-500 to-amber-600",
        accent: "bg-orange-100 text-orange-800",
        gradient: "from-orange-50 to-amber-50",
      },
      SHM: {
        primary: "from-red-500 to-rose-600",
        accent: "bg-red-100 text-red-800",
        gradient: "from-red-50 to-rose-50",
      },
    }
    return themes[code] || themes.SCES
  }

  const theme = getSchoolTheme(schoolCode)

  // Handle error state
  if (error) {
    return (
      <AuthenticatedLayout>
        <Head title={`${schoolCode} Faculty Admin Dashboard`} />
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex">
                <AlertCircle className="w-5 h-5 text-red-400" />
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-red-800">Dashboard Error</h3>
                  <div className="mt-2 text-sm text-red-700">
                    <p>{error}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${schoolCode} Faculty Admin Dashboard`} />
      <div className={`min-h-screen bg-gradient-to-br from-slate-50 ${theme.gradient}`}>
        {/* Header Section */}
        <div className="bg-white shadow-sm border-b border-slate-200">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-slate-800">{schoolName}</h1>
                <p className="text-slate-600 mt-1">
                  Faculty Administration Dashboard
                  <span className={`ml-2 px-2 py-1 ${theme.accent} rounded text-sm font-medium`}>{schoolCode}</span>
                  {currentSemester && (
                    <span className="ml-2 px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                      {currentSemester.name}
                    </span>
                  )}
                </p>
              </div>
              <div className="flex items-center space-x-4">
                {pendingApprovals && (
                  <div className="bg-amber-100 text-amber-800 px-3 py-2 rounded-lg text-sm font-medium">
                    {pendingApprovals.enrollments + pendingApprovals.lecturerRequests + pendingApprovals.unitChanges}{" "}
                    Pending Approvals
                  </div>
                )}
                <div className={`bg-gradient-to-r ${theme.primary} text-white px-4 py-2 rounded-lg`}>
                  <span className="font-semibold">Faculty Admin</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Quick Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {/* Total Students Card */}
            <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-slate-600 text-sm font-medium">Total Students</p>
                  <p className="text-2xl font-bold text-slate-800 mt-1">
                    {statistics?.totalStudents?.count?.toLocaleString() || "0"}
                  </p>
                </div>
                <div className="bg-blue-100 p-3 rounded-full">
                  <GraduationCap className="w-6 h-6 text-blue-600" />
                </div>
              </div>
              {statistics?.totalStudents &&
                formatGrowthRate(statistics.totalStudents.growthRate, statistics.totalStudents.period)}
            </div>

            {/* Total Lecturers Card */}
            <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-slate-600 text-sm font-medium">Total Lecturers</p>
                  <p className="text-2xl font-bold text-slate-800 mt-1">
                    {statistics?.totalLecturers?.count?.toLocaleString() || "0"}
                  </p>
                </div>
                <div className="bg-emerald-100 p-3 rounded-full">
                  <Users className="w-6 h-6 text-emerald-600" />
                </div>
              </div>
              {statistics?.totalLecturers &&
                formatGrowthRate(statistics.totalLecturers.growthRate, statistics.totalLecturers.period)}
            </div>

            {/* Total Units Card */}
            <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-slate-600 text-sm font-medium">Total Units</p>
                  <p className="text-2xl font-bold text-slate-800 mt-1">
                    {statistics?.totalUnits?.count?.toLocaleString() || "0"}
                  </p>
                </div>
                <div className="bg-purple-100 p-3 rounded-full">
                  <BookOpen className="w-6 h-6 text-purple-600" />
                </div>
              </div>
              {statistics?.totalUnits &&
                formatGrowthRate(statistics.totalUnits.growthRate, statistics.totalUnits.period)}
            </div>

            {/* Active Enrollments Card */}
            <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-slate-600 text-sm font-medium">Active Enrollments</p>
                  <p className="text-2xl font-bold text-slate-800 mt-1">
                    {statistics?.activeEnrollments?.count?.toLocaleString() || "0"}
                  </p>
                </div>
                <div className="bg-amber-100 p-3 rounded-full">
                  <UserCheck className="w-6 h-6 text-amber-600" />
                </div>
              </div>
              {statistics?.activeEnrollments &&
                formatGrowthRate(statistics.activeEnrollments.growthRate, statistics.activeEnrollments.period)}
            </div>
          </div>

          {/* Main Content Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Faculty Management Section */}
            <div className="lg:col-span-2">
              <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-2xl font-bold text-slate-800">{schoolCode} Management</h2>
                  <div className="bg-slate-100 p-2 rounded-lg">
                    <Building className="w-5 h-5 text-slate-600" />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Students Management */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/students`}
                    className="group block p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-300 border border-blue-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-blue-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <GraduationCap className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Students</h3>
                        <p className="text-slate-600 text-sm">Manage {schoolCode} students</p>
                      </div>
                    </div>
                  </Link>

                  {/* Lecturers Management */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/lecturers`}
                    className="group block p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl hover:from-emerald-100 hover:to-emerald-200 transition-all duration-300 border border-emerald-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-emerald-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <Users className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Lecturers</h3>
                        <p className="text-slate-600 text-sm">Manage {schoolCode} faculty</p>
                      </div>
                    </div>
                  </Link>

                  {/* Units Management */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/units`}
                    className="group block p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all duration-300 border border-purple-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-purple-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <BookOpen className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Units</h3>
                        <p className="text-slate-600 text-sm">Manage {schoolCode} courses</p>
                      </div>
                    </div>
                  </Link>

                  {/* Enrollments Management */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/enrollments`}
                    className="group block p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl hover:from-amber-100 hover:to-amber-200 transition-all duration-300 border border-amber-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-amber-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <UserCheck className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Enrollments</h3>
                        <p className="text-slate-600 text-sm">Manage {schoolCode} enrollments</p>
                      </div>
                    </div>
                  </Link>

                  {/* Timetables Management */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/timetables`}
                    className="group block p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all duration-300 border border-indigo-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-indigo-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <Calendar className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Timetables</h3>
                        <p className="text-slate-600 text-sm">Manage {schoolCode} schedules</p>
                      </div>
                    </div>
                  </Link>

                  {/* Reports */}
                  <Link
                    href={`/${schoolCode.toLowerCase()}/reports`}
                    className="group block p-4 bg-gradient-to-br from-rose-50 to-rose-100 rounded-xl hover:from-rose-100 hover:to-rose-200 transition-all duration-300 border border-rose-200"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="bg-rose-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                        <BarChart3 className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-slate-800">Reports</h3>
                        <p className="text-slate-600 text-sm">View {schoolCode} analytics</p>
                      </div>
                    </div>
                  </Link>
                </div>
              </div>
            </div>

            {/* Sidebar */}
            <div className="space-y-6">
              {/* Quick Actions */}
              <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-bold text-slate-800">Quick Actions</h3>
                  <div className="bg-green-100 p-2 rounded-lg">
                    <PlusCircle className="w-5 h-5 text-green-600" />
                  </div>
                </div>
                <div className="space-y-3">
                  <Link
                    href={`/${schoolCode.toLowerCase()}/students/create`}
                    className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200"
                  >
                    <span className="text-blue-700 font-medium">Add {schoolCode} Student</span>
                  </Link>
                  <Link
                    href={`/${schoolCode.toLowerCase()}/lecturers/create`}
                    className="block p-3 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors duration-200"
                  >
                    <span className="text-emerald-700 font-medium">Add {schoolCode} Lecturer</span>
                  </Link>
                  <Link
                    href={`/${schoolCode.toLowerCase()}/units/create`}
                    className="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors duration-200"
                  >
                    <span className="text-purple-700 font-medium">Create {schoolCode} Unit</span>
                  </Link>
                  <Link
                    href={`/${schoolCode.toLowerCase()}/enrollments/bulk`}
                    className="block p-3 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors duration-200"
                  >
                    <span className="text-amber-700 font-medium">Bulk {schoolCode} Enrollment</span>
                  </Link>
                </div>
              </div>

              {/* Faculty Information */}
              <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-bold text-slate-800">Faculty Info</h3>
                  <div className="bg-slate-100 p-2 rounded-lg">
                    <School className="w-5 h-5 text-slate-600" />
                  </div>
                </div>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-slate-600">Faculty Name</span>
                    <span className="font-semibold text-slate-800">{schoolName}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-slate-600">Faculty Code</span>
                    <span className="font-semibold text-slate-800">{schoolCode}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-slate-600">Total Programs</span>
                    <span className="font-semibold text-slate-800">{facultyInfo?.totalPrograms || 0}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-slate-600">Total Classes</span>
                    <span className="font-semibold text-slate-800">{facultyInfo?.totalClasses || 0}</span>
                  </div>
                  {currentSemester && (
                    <div className="pt-2 border-t border-slate-200">
                      <div className="text-slate-600 text-sm mb-1">Current Semester</div>
                      <div className="font-semibold text-slate-800">{currentSemester.name}</div>
                      {currentSemester.start_date && currentSemester.end_date && (
                        <div className="text-xs text-slate-500 mt-1">
                          {new Date(currentSemester.start_date).toLocaleDateString()} -{" "}
                          {new Date(currentSemester.end_date).toLocaleDateString()}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Pending Approvals */}
              {pendingApprovals && (
                <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-xl font-bold text-slate-800">Pending Approvals</h3>
                    <div className="bg-amber-100 p-2 rounded-lg">
                      <Clock className="w-5 h-5 text-amber-600" />
                    </div>
                  </div>
                  <div className="space-y-3">
                    {pendingApprovals.enrollments > 0 && (
                      <Link
                        href={`/${schoolCode.toLowerCase()}/approvals/enrollments`}
                        className="flex justify-between items-center p-3 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors duration-200"
                      >
                        <span className="text-amber-700 font-medium">Enrollment Requests</span>
                        <span className="bg-amber-200 text-amber-800 px-2 py-1 rounded-full text-xs font-bold">
                          {pendingApprovals.enrollments}
                        </span>
                      </Link>
                    )}
                    {pendingApprovals.lecturerRequests > 0 && (
                      <Link
                        href={`/${schoolCode.toLowerCase()}/approvals/lecturers`}
                        className="flex justify-between items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200"
                      >
                        <span className="text-blue-700 font-medium">Lecturer Requests</span>
                        <span className="bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-xs font-bold">
                          {pendingApprovals.lecturerRequests}
                        </span>
                      </Link>
                    )}
                    {pendingApprovals.unitChanges > 0 && (
                      <Link
                        href={`/${schoolCode.toLowerCase()}/approvals/units`}
                        className="flex justify-between items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors duration-200"
                      >
                        <span className="text-purple-700 font-medium">Unit Changes</span>
                        <span className="bg-purple-200 text-purple-800 px-2 py-1 rounded-full text-xs font-bold">
                          {pendingApprovals.unitChanges}
                        </span>
                      </Link>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
