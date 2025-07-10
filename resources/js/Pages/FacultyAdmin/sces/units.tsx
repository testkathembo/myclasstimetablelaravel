"use client"

import { useState } from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Badge } from "@/Components/ui/badge"
import { Input } from "@/Components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table"
import { BookOpen, Plus, Edit, Trash2, Search } from "lucide-react"

interface Unit {
  id: number
  name: string
  code: string
  credit_hours: number
  program?: {
    name: string
  }
  created_at: string
}

interface Props {
  units: {
    data: Unit[]
    links: any[]
    total: number
    current_page: number
    per_page: number
  }
  schoolCode: string
  schoolName: string
  errors?: {
    error?: string
  }
}

export default function SCESUnits({
  units,
  schoolCode = "SCES",
  schoolName = "School of Computing and Engineering Sciences",
  errors,
}: Props) {
  const [searchTerm, setSearchTerm] = useState("")

  const filteredUnits =
    units?.data?.filter((unit) => {
      const matchesSearch =
        !searchTerm ||
        unit.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        unit.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (unit.program?.name && unit.program.name.toLowerCase().includes(searchTerm.toLowerCase()))

      return matchesSearch
    }) || []

  const handleCreateUnit = () => {
    router.visit(`/sces/units/create`)
  }

  const handleEditUnit = (unitId: number) => {
    router.visit(`/sces/units/${unitId}/edit`)
  }

  const handleDeleteUnit = (unitId: number) => {
    if (confirm("Are you sure you want to delete this unit?")) {
      router.delete(`/sces/units/${unitId}`)
    }
  }

  if (errors?.error) {
    return (
      <AuthenticatedLayout
        header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName} - Units</h2>}
      >
        <Head title={`${schoolName} - Units`} />
        <div className="py-12">
          <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <Card>
              <CardContent className="pt-6">
                <div className="text-center text-red-600">
                  <p>Error loading units: {errors.error}</p>
                  <Button onClick={() => window.location.reload()} className="mt-4">
                    Retry
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <div>
            <h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName}</h2>
            <p className="text-sm text-gray-600">Manage units in {schoolCode}</p>
          </div>
          <Badge variant="secondary">{schoolCode}</Badge>
        </div>
      }
    >
      <Head title={`${schoolName} - Units`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {/* Statistics Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Units</CardTitle>
              <BookOpen className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{units?.total || 0}</div>
            </CardContent>
          </Card>

          {/* Actions and Search */}
          <Card>
            <CardContent className="pt-6">
              <div className="flex justify-between items-center gap-4">
                <div className="relative flex-1 max-w-sm">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search units..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10"
                  />
                </div>
                <Button onClick={handleCreateUnit}>
                  <Plus className="h-4 w-4 mr-2" />
                  Add Unit
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Units Table */}
          <Card>
            <CardHeader>
              <CardTitle>Units</CardTitle>
              <CardDescription>
                Showing {filteredUnits.length} of {units?.total || 0} units
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Unit Code</TableHead>
                    <TableHead>Unit Name</TableHead>
                    <TableHead>Credit Hours</TableHead>
                    <TableHead>Program</TableHead>
                    <TableHead>Created Date</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredUnits.map((unit) => (
                    <TableRow key={unit.id}>
                      <TableCell className="font-medium">{unit.code}</TableCell>
                      <TableCell>{unit.name}</TableCell>
                      <TableCell>{unit.credit_hours}</TableCell>
                      <TableCell>
                        <Badge variant="outline">{unit.program?.name || "N/A"}</Badge>
                      </TableCell>
                      <TableCell>{new Date(unit.created_at).toLocaleDateString()}</TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEditUnit(unit.id)}>
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDeleteUnit(unit.id)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {filteredUnits.length === 0 && (
                <div className="text-center py-8 text-gray-500">No units found matching your criteria.</div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
