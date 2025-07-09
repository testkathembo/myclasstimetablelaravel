"use client"

import { useState } from "react"
import { Head, router } from "@inertiajs/react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import Input from "@/components/ui/input"
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Users, BookOpen, GraduationCap, Plus } from "lucide-react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Enrollment {
  id: number
  student_code: string
  unit: {
    id: number
    name: string
    code: string
    program?: {
      name: string
    }
  }
  group: {
    id: number
    name: string
    class?: {
      name: string
    }
  }
  created_at: string
}

interface LecturerAssignment {
  unit_id: number
  unit_name: string
  unit_code: string
  lecturer_code: string
  lecturer_name: string
}

interface Props {
  enrollments: {
    data: Enrollment[]
    links: any[]
    total: number
    current_page: number
    per_page: number
  }
  lecturerAssignments: {
    data: LecturerAssignment[]
    links: any[]
    total: number
    current_page: number
    per_page: number
  }
  semesters: Array<{
    id: number
    name: string
    is_active: boolean
  }>
  groups: Array<{
    id: number
    name: string
    class?: {
      name: string
    }
  }>
  classes: Array<{
    id: number
    name: string
  }>
  units: Array<{
    id: number
    name: string
    code: string
  }>
  schoolCode: string
  schoolName: string
  errors?: {
    error?: string
  }
}

export default function FacultyEnrollments({
  enrollments,
  lecturerAssignments,
  semesters,
  groups,
  classes,
  units,
  schoolCode,
  schoolName,
  errors,
}: Props) {
  const [searchTerm, setSearchTerm] = useState("")
  const [selectedSemester, setSelectedSemester] = useState("all")
  const [selectedGroup, setSelectedGroup] = useState("all")

  const filteredEnrollments =
    enrollments?.data?.filter((enrollment) => {
      const matchesSearch =
        !searchTerm ||
        enrollment.student_code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        enrollment.unit.name.toLowerCase().includes(searchTerm.toLowerCase())

      const matchesSemester =
        !selectedSemester || selectedSemester === "all" || enrollment.unit.id.toString() === selectedSemester
      const matchesGroup = !selectedGroup || selectedGroup === "all" || enrollment.group.id.toString() === selectedGroup

      return matchesSearch && matchesSemester && matchesGroup
    }) || []

  const handleCreateEnrollment = () => {
    router.visit(`/${schoolCode.toLowerCase()}/enrollments/create`)
  }

  const handleBulkEnrollment = () => {
    router.visit(`/${schoolCode.toLowerCase()}/enrollments/bulk`)
  }

  if (errors?.error) {
    return (
      
      <div className="container mx-auto py-8">
        <Head title={`${schoolName} - Enrollments`} />
        <Card>
          <CardContent className="pt-6">
            <div className="text-center text-red-600">
              <p>Error loading enrollments: {errors.error}</p>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${schoolName} - Enrollments`} />
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8 relative overflow-hidden">
              <div className="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-purple-600/5"></div>
              <div className="relative">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2 animate-in slide-in-from-left duration-500">
                      {schoolName} Enrollments
                    </h1>
                    <p className="text-slate-600 text-lg">
                      Manage student enrollments and lecturer assignments
                    </p>
                  </div>
                  <div className="flex flex-col sm:flex-row gap-3 mt-6 sm:mt-0">
                    <Button onClick={handleBulkEnrollment} variant="outline">
                      <Users className="h-4 w-4 mr-2" />
                      Bulk Enrollment
                    </Button>
                    <Button onClick={handleCreateEnrollment}>
                      <Plus className="h-4 w-4 mr-2" />
                      New Enrollment
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {errors?.error && (
            <div className="mb-6">
              <Card>
                <CardContent>
                  <div className="text-center text-red-600 py-4">
                    <p>Error loading enrollments: {errors.error}</p>
                  </div>
                </CardContent>
              </Card>
            </div>
          )}

          {/* Statistics Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <Card>
              <CardHeader>
                <CardTitle>Total Enrollments</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{enrollments?.total || 0}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Active Units</CardTitle>
                <BookOpen className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{units?.length || 0}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Active Groups</CardTitle>
                <GraduationCap className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{groups?.length || 0}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Lecturer Assignments</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{lecturerAssignments?.total || 0}</div>
              </CardContent>
            </Card>
          </div>

          {/* Main Content */}
          <Tabs defaultValue="enrollments" className="space-y-4">
            <TabsList>
              <TabsTrigger value="enrollments">Student Enrollments</TabsTrigger>
              <TabsTrigger value="lecturers">Lecturer Assignments</TabsTrigger>
            </TabsList>

            <TabsContent value="enrollments" className="space-y-4">
              {/* Filters */}
              <Card>
                <CardHeader>
                  <CardTitle>Filter Enrollments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex gap-4">
                    <div className="flex-1">
                      <Input
                        placeholder="Search by student code or unit name..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full"
                      />
                    </div>
                    <Select value={selectedSemester} onValueChange={setSelectedSemester}>
                      <SelectTrigger className="w-48">
                        <SelectValue placeholder="Select semester" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Semesters</SelectItem>
                        {semesters?.map((semester) => (
                          <SelectItem key={semester.id} value={semester.id.toString()}>
                            {semester.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <Select value={selectedGroup} onValueChange={setSelectedGroup}>
                      <SelectTrigger className="w-48">
                        <SelectValue placeholder="Select group" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Groups</SelectItem>
                        {groups?.map((group) => (
                          <SelectItem key={group.id} value={group.id.toString()}>
                            {group.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </CardContent>
              </Card>

              {/* Enrollments Table */}
              <Card>
                <CardHeader>
                  <CardTitle>Student Enrollments</CardTitle>
                  <CardDescription>
                    Showing {filteredEnrollments.length} of {enrollments?.total || 0} enrollments
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Student Code</TableHead>
                        <TableHead>Unit</TableHead>
                        <TableHead>Program</TableHead>
                        <TableHead>Group</TableHead>
                        <TableHead>Class</TableHead>
                        <TableHead>Enrolled Date</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {filteredEnrollments.map((enrollment) => (
                        <TableRow key={enrollment.id}>
                          <TableCell className="font-medium">{enrollment.student_code}</TableCell>
                          <TableCell>
                            <div>
                              <div className="font-medium">{enrollment.unit.name}</div>
                              {enrollment.unit.code && (
                                <div className="text-sm text-muted-foreground">{enrollment.unit.code}</div>
                              )}
                            </div>
                          </TableCell>
                          <TableCell>{enrollment.unit.program?.name || "N/A"}</TableCell>
                          <TableCell>
                            <Badge variant="outline">{enrollment.group.name}</Badge>
                          </TableCell>
                          <TableCell>{enrollment.group.class?.name || "N/A"}</TableCell>
                          <TableCell>{new Date(enrollment.created_at).toLocaleDateString()}</TableCell>
                          <TableCell>
                            <div className="flex gap-2">
                              <Button variant="outline" size="sm">
                                Edit
                              </Button>
                              <Button variant="destructive" size="sm">
                                Remove
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="lecturers" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Lecturer Assignments</CardTitle>
                  <CardDescription>Manage unit assignments for lecturers in {schoolName}</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Unit</TableHead>
                        <TableHead>Unit Code</TableHead>
                        <TableHead>Assigned Lecturer</TableHead>
                        <TableHead>Lecturer Code</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {lecturerAssignments?.data?.map((assignment, index) => (
                        <TableRow key={`${assignment.unit_id}-${assignment.lecturer_code}-${index}`}>
                          <TableCell className="font-medium">{assignment.unit_name}</TableCell>
                          <TableCell>
                            <Badge variant="outline">{assignment.unit_code}</Badge>
                          </TableCell>
                          <TableCell>{assignment.lecturer_name || "Unknown"}</TableCell>
                          <TableCell>{assignment.lecturer_code}</TableCell>
                          <TableCell>
                            <div className="flex gap-2">
                              <Button variant="outline" size="sm">
                                Change
                              </Button>
                              <Button variant="destructive" size="sm">
                                Remove
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}