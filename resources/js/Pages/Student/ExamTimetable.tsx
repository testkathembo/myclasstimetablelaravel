"use client"

import type React from "react"
import { Head, useForm } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, Download, Filter, MapPin, User, AlertCircle } from "lucide-react"
import { format } from "date-fns"

interface Unit {
  id: number
  unit_code?: string
  unit_name?: string
  code?: string
  name?: string
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Class {
  id: number
  name: string
  class_name?: string
}

interface Examroom {
  id: number
  name?: string
  room_name?: string
  capacity?: number
}

interface TimeSlot {
  id: number
  start_time: string
  end_time: string
}

interface Lecturer {
  id: number
  first_name: string
  last_name: string
  email?: string
}

interface ExamTimetable {
  id: number
  exam_date: string
  start_time: string
  end_time: string
  duration?: number
  special_requirements?: string
  status?: string
  unit?: Unit
  semester?: Semester
  class?: Class
  examroom?: Examroom
  timeSlot?: TimeSlot
  lecturer?: Lecturer
  invigilator?: Lecturer
  // Legacy fields for backward compatibility
  date?: string
  day?: string
  venue?: string
  location?: string
  no?: number
  chief_invigilator?: string
}

interface Enrollment {
  id: number
  unit: Unit
  semester: Semester
  class: Class
}

interface Props {
  auth: {
    user: any
  }
  examTimetables: ExamTimetable[]
  enrollments?: Enrollment[]
  semesters?: Semester[]
  selectedSemesterId?: number
  student?: any
  message?: string
}

export default function ExamTimetable({
  auth,
  examTimetables,
  enrollments,
  semesters,
  selectedSemesterId,
  student,
  message,
}: Props) {
  const { data, setData, get, processing } = useForm({
    semester_id: selectedSemesterId || semesters?.[0]?.id || 1,
  })

  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newSemesterId = Number.parseInt(e.target.value)
    setData("semester_id", newSemesterId)
    get("/my-exams", {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleDownload = () => {
    window.location.href = `/my-exams/download?semester_id=${data.semester_id}`
  }

  const formatDate = (dateString: string) => {
    try {
      return format(new Date(dateString), "MMM d, yyyy")
    } catch {
      return dateString
    }
  }

  const formatTime = (timeString: string) => {
    try {
      return format(new Date(timeString), "h:mm a")
    } catch {
      return timeString
    }
  }

  const getDayName = (dateString: string) => {
    try {
      return format(new Date(dateString), "EEEE")
    } catch {
      return ""
    }
  }

  const getExamStatus = (examDate: string) => {
    const today = new Date()
    const exam = new Date(examDate)

    if (exam < today) {
      return { status: "completed", color: "text-gray-500", bg: "bg-gray-100" }
    } else if (exam.toDateString() === today.toDateString()) {
      return { status: "today", color: "text-red-600", bg: "bg-red-50" }
    } else {
      return { status: "upcoming", color: "text-blue-600", bg: "bg-blue-50" }
    }
  }

  const getUnitCode = (exam: ExamTimetable) => {
    return exam.unit?.unit_code || exam.unit?.code || "N/A"
  }

  const getUnitName = (exam: ExamTimetable) => {
    return exam.unit?.unit_name || exam.unit?.name || "Unknown Unit"
  }

  const getVenue = (exam: ExamTimetable) => {
    return exam.examroom?.name || exam.examroom?.room_name || exam.venue || "TBA"
  }

  const getChiefInvigilator = (exam: ExamTimetable) => {
    if (exam.lecturer) {
      return `${exam.lecturer.first_name} ${exam.lecturer.last_name}`
    }
    if (exam.invigilator) {
      return `${exam.invigilator.first_name} ${exam.invigilator.last_name}`
    }
    return exam.chief_invigilator || "TBA"
  }

  const getExamDate = (exam: ExamTimetable) => {
    return exam.exam_date || exam.date || ""
  }

  const getStartTime = (exam: ExamTimetable) => {
    return exam.start_time || ""
  }

  const getEndTime = (exam: ExamTimetable) => {
    return exam.end_time || ""
  }

  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title="My Exam Timetable" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {message && (
            <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-md p-4">
              <div className="flex">
                <AlertCircle className="w-5 h-5 text-yellow-400 mr-3" />
                <p className="text-yellow-800">{message}</p>
              </div>
            </div>
          )}

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-semibold text-gray-900">My Exam Timetable</h1>

                <div className="flex items-center space-x-4">
                  {semesters && semesters.length > 0 && (
                    <div className="flex items-center">
                      <Filter className="h-4 w-4 text-gray-500 mr-2" />
                      <select
                        value={data.semester_id}
                        onChange={handleSemesterChange}
                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        disabled={processing}
                      >
                        {semesters.map((semester) => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name} {semester.year || ""}
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  {examTimetables && examTimetables.length > 0 && (
                    <button
                      onClick={handleDownload}
                      className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                      <Download className="h-4 w-4 mr-1" />
                      Download PDF
                    </button>
                  )}
                </div>
              </div>

              {/* Summary Stats */}
              {examTimetables && examTimetables.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <div className="text-2xl font-bold text-blue-600">{examTimetables.length}</div>
                    <div className="text-sm text-blue-800">Total Exams</div>
                  </div>
                  <div className="bg-green-50 p-4 rounded-lg">
                    <div className="text-2xl font-bold text-green-600">
                      {examTimetables.filter((exam) => new Date(getExamDate(exam)) >= new Date()).length}
                    </div>
                    <div className="text-sm text-green-800">Upcoming Exams</div>
                  </div>
                  <div className="bg-orange-50 p-4 rounded-lg">
                    <div className="text-2xl font-bold text-orange-600">{enrollments ? enrollments.length : "N/A"}</div>
                    <div className="text-sm text-orange-800">Enrolled Units</div>
                  </div>
                </div>
              )}

              {examTimetables && examTimetables.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Date
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Unit
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Time
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Venue
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Invigilator
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Status
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {examTimetables.map((exam) => {
                        const statusInfo = getExamStatus(getExamDate(exam))

                        return (
                          <tr key={exam.id} className={`hover:bg-gray-50 ${statusInfo.bg}`}>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <Calendar className="h-4 w-4 text-gray-400 mr-2" />
                                <div>
                                  <div className="text-sm font-medium text-gray-900">
                                    {formatDate(getExamDate(exam))}
                                  </div>
                                  <div className="text-sm text-gray-500">{getDayName(getExamDate(exam))}</div>
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm font-medium text-gray-900">{getUnitCode(exam)}</div>
                              <div className="text-sm text-gray-500">{getUnitName(exam)}</div>
                              {exam.special_requirements && (
                                <div className="text-xs text-yellow-600 mt-1">Special: {exam.special_requirements}</div>
                              )}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <Clock className="h-4 w-4 text-gray-400 mr-2" />
                                <div className="text-sm text-gray-900">
                                  {formatTime(getStartTime(exam))} - {formatTime(getEndTime(exam))}
                                </div>
                              </div>
                              {exam.duration && (
                                <div className="text-xs text-gray-500 mt-1">Duration: {exam.duration} mins</div>
                              )}
                            </td>
                            <td className="px-6 py-4">
                              <div className="flex items-center">
                                <MapPin className="h-4 w-4 text-gray-400 mr-2" />
                                <div className="text-sm text-gray-900">{getVenue(exam)}</div>
                              </div>
                              {exam.location && <div className="text-sm text-gray-500">{exam.location}</div>}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <User className="h-4 w-4 text-gray-400 mr-2" />
                                <div className="text-sm text-gray-500">{getChiefInvigilator(exam)}</div>
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span
                                className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusInfo.color} ${statusInfo.bg}`}
                              >
                                {statusInfo.status.charAt(0).toUpperCase() + statusInfo.status.slice(1)}
                              </span>
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-12">
                  <Calendar className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No Exams Scheduled</h3>
                  <div className="text-gray-500 mb-4">
                    {semesters && semesters.length > 0
                      ? "No exams found for the selected semester."
                      : "You don't have any exams scheduled at the moment."}
                  </div>
                  <p className="text-sm text-gray-400 mb-4">
                    Try selecting a different semester or contact your administrator if you believe this is an error.
                  </p>
                  {enrollments && (
                    <a
                      href="/my-enrollments"
                      className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                      View My Enrollments
                    </a>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
