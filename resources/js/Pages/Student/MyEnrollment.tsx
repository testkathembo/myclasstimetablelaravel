"use client"
import { Head, usePage } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"
import { router, useRoute } from "@inertiajs/react"

export default function MyEnrollments() {
  const { auth, enrollments, semesters, selectedSemester } = usePage().props
  const route = useRoute()

  const handleSemesterChange = (value) => {
    router.get(route("student.my-enrollments"), { semester_id: value }, { preserveState: true })
  }

  return (
    <AppLayout title="My Enrollments">
      <Head title="My Enrollments" />

      <div className="p-6">
        <h1 className="text-2xl font-bold mb-6">My Enrollments</h1>

        <div className="mb-6 max-w-xs">
          <Select value={selectedSemester} onValueChange={handleSemesterChange}>
            <SelectTrigger>
              <SelectValue placeholder="Select Semester" />
            </SelectTrigger>
            <SelectContent>
              {semesters.map((semester) => (
                <SelectItem key={semester.id} value={semester.id.toString()}>
                  {semester.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Enrolled Units</CardTitle>
          </CardHeader>
          <CardContent>
            {enrollments.length > 0 ? (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Unit Code</TableHead>
                    <TableHead>Unit Name</TableHead>
                    <TableHead>Semester</TableHead>
                    <TableHead>Group</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {enrollments.map((enrollment) => (
                    <TableRow key={enrollment.id}>
                      <TableCell>{enrollment.unit.code}</TableCell>
                      <TableCell>{enrollment.unit.name}</TableCell>
                      <TableCell>{enrollment.semester.name}</TableCell>
                      <TableCell>
                        <Badge variant="outline">Group {enrollment.group}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="success">Enrolled</Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <div className="text-center py-8 text-gray-500">
                You are not enrolled in any units for the selected semester.
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}
