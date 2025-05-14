"use client"

import { useState } from "react"
import { Head, usePage } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Search, Plus, Trash2 } from "lucide-react"

export default function SemesterUnits() {
  const { units, semesters, programs, schools, filters, can } = usePage().props

  const [isAssignDialogOpen, setIsAssignDialogOpen] = useState(false)
  const [selectedSemester, setSelectedSemester] = useState("")
  const [selectedProgram, setSelectedProgram] = useState("")
  const [selectedUnits, setSelectedUnits] = useState([])
  const [searchQuery, setSearchQuery] = useState(filters.search || "")

  const handleSearch = (e) => {
    e.preventDefault()
    router.get(
      route("semester-units.index"),
      {
        search: searchQuery,
        semester_id: filters.semester_id,
        program_id: filters.program_id,
        school_id: filters.school_id,
      },
      { preserveState: true },
    )
  }

  const handleFilterChange = (field, value) => {
    router.get(
      route("semester-units.index"),
      {
        ...filters,
        [field]: value,
      },
      { preserveState: true },
    )
  }

  const handleUnitSelection = (unitId) => {
    setSelectedUnits((prev) => {
      if (prev.includes(unitId)) {
        return prev.filter((id) => id !== unitId)
      } else {
        return [...prev, unitId]
      }
    })
  }

  const handleAssignSubmit = (e) => {
    e.preventDefault()
    router.post(
      route("semester-units.store"),
      {
        semester_id: selectedSemester,
        unit_ids: selectedUnits,
      },
      {
        onSuccess: () => {
          setIsAssignDialogOpen(false)
          setSelectedSemester("")
          setSelectedUnits([])
        },
      },
    )
  }

  const handleRemoveFromSemester = (unitId) => {
    if (confirm("Are you sure you want to remove this unit from the semester?")) {
      router.delete(route("semester-units.destroy", unitId))
    }
  }

  const handlePageChange = (url) => {
    router.visit(url)
  }

  return (
    <AppLayout title="Semester Units">
      <Head title="Semester Units" />

      <div className="p-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold">Semester Unit Assignments</h1>
          {can.assign && (
            <Button onClick={() => setIsAssignDialogOpen(true)}>
              <Plus className="mr-2 h-4 w-4" /> Assign Units to Semester
            </Button>
          )}
        </div>

        <div className="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <Select
              value={filters.semester_id || ""}
              onValueChange={(value) => handleFilterChange("semester_id", value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Filter by Semester" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Semesters</SelectItem>
                {semesters.map((semester) => (
                  <SelectItem key={semester.id} value={semester.id.toString()}>
                    {semester.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Select value={filters.program_id || ""} onValueChange={(value) => handleFilterChange("program_id", value)}>
              <SelectTrigger>
                <SelectValue placeholder="Filter by Program" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Programs</SelectItem>
                {programs.map((program) => (
                  <SelectItem key={program.id} value={program.id.toString()}>
                    {program.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Select value={filters.school_id || ""} onValueChange={(value) => handleFilterChange("school_id", value)}>
              <SelectTrigger>
                <SelectValue placeholder="Filter by School" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Schools</SelectItem>
                {schools.map((school) => (
                  <SelectItem key={school.id} value={school.id.toString()}>
                    {school.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex">
            <Input
              type="text"
              placeholder="Search units..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="mr-2"
            />
            <Button onClick={handleSearch} variant="outline">
              <Search className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Program</TableHead>
              <TableHead>School</TableHead>
              <TableHead>Semester</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {units.data.length > 0 ? (
              units.data.map((unit) => (
                <TableRow key={unit.id}>
                  <TableCell>{unit.code}</TableCell>
                  <TableCell>{unit.name}</TableCell>
                  <TableCell>{unit.program?.name || "N/A"}</TableCell>
                  <TableCell>{unit.school?.name || "N/A"}</TableCell>
                  <TableCell>{unit.semester?.name || "Not Assigned"}</TableCell>
                  <TableCell>
                    <span
                      className={`px-2 py-1 rounded text-xs ${unit.is_active ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"}`}
                    >
                      {unit.is_active ? "Active" : "Inactive"}
                    </span>
                  </TableCell>
                  <TableCell>
                    {unit.semester_id && can.assign && (
                      <Button variant="destructive" size="sm" onClick={() => handleRemoveFromSemester(unit.id)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-4">
                  No units found
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>

        {units.links && (
          <div className="mt-4">
            {/* Pagination component would go here */}
            <div className="flex justify-center space-x-2">
              {units.links.map((link, i) => (
                <button
                  key={i}
                  onClick={() => link.url && handlePageChange(link.url)}
                  className={`px-3 py-1 rounded ${
                    link.active
                      ? "bg-blue-600 text-white"
                      : link.url
                        ? "bg-white text-blue-600 border border-blue-600"
                        : "bg-gray-100 text-gray-400 cursor-not-allowed"
                  }`}
                  disabled={!link.url}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        )}

        <Dialog open={isAssignDialogOpen} onOpenChange={setIsAssignDialogOpen}>
          <DialogContent className="sm:max-w-[600px]">
            <DialogHeader>
              <DialogTitle>Assign Units to Semester</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleAssignSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Semester</label>
                  <Select value={selectedSemester} onValueChange={setSelectedSemester} required>
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

                <div>
                  <label className="block text-sm font-medium mb-1">Program (Optional Filter)</label>
                  <Select value={selectedProgram} onValueChange={setSelectedProgram}>
                    <SelectTrigger>
                      <SelectValue placeholder="All Programs" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">All Programs</SelectItem>
                      {programs.map((program) => (
                        <SelectItem key={program.id} value={program.id.toString()}>
                          {program.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Select Units</label>
                <div className="border rounded-md p-4 h-64 overflow-y-auto">
                  {units.data
                    .filter((unit) => !unit.semester_id || !unit.is_active)
                    .filter((unit) => !selectedProgram || unit.program_id.toString() === selectedProgram)
                    .map((unit) => (
                      <div key={unit.id} className="flex items-center mb-2">
                        <input
                          type="checkbox"
                          id={`unit-${unit.id}`}
                          value={unit.id}
                          checked={selectedUnits.includes(unit.id)}
                          onChange={() => handleUnitSelection(unit.id)}
                          className="mr-2"
                        />
                        <label htmlFor={`unit-${unit.id}`} className="text-sm">
                          {unit.code} - {unit.name}
                        </label>
                      </div>
                    ))}
                  {units.data.filter((unit) => !unit.semester_id || !unit.is_active).length === 0 && (
                    <p className="text-gray-500 text-center py-4">No available units to assign</p>
                  )}
                </div>
              </div>

              <div className="flex justify-end space-x-2">
                <Button type="button" variant="outline" onClick={() => setIsAssignDialogOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={selectedUnits.length === 0 || !selectedSemester}>
                  Assign Units
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  )
}
