"use client"

import { useState } from "react"
import { Inertia } from "@inertiajs/inertia"
import { usePage } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Pagination } from "@/components/ui/pagination"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select } from "@/components/ui/select"
import { Plus, Search, RefreshCw, ChevronUp, ChevronDown } from "lucide-react"

const ProgramsIndex = () => {
  const { programs, schools, filters, can } = usePage().props
  const [searchTerm, setSearchTerm] = useState(filters.search || "")
  const [schoolFilter, setSchoolFilter] = useState(filters.school_id || "")
  const [sortField, setSortField] = useState(filters.sort_field || "name")
  const [sortDirection, setSortDirection] = useState(filters.sort_direction || "asc")

  const handleSearch = (e) => {
    e.preventDefault()
    applyFilters({ search: searchTerm })
  }

  const handleSchoolFilter = (e) => {
    setSchoolFilter(e.target.value)
    applyFilters({ school_id: e.target.value })
  }

  const handleSort = (field) => {
    const direction = field === sortField && sortDirection === "asc" ? "desc" : "asc"
    setSortField(field)
    setSortDirection(direction)
    applyFilters({ sort_field: field, sort_direction: direction })
  }

  const applyFilters = (newFilters) => {
    Inertia.get(
      "/programs",
      {
        ...filters,
        ...newFilters,
      },
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  const resetFilters = () => {
    setSearchTerm("")
    setSchoolFilter("")
    setSortField("name")
    setSortDirection("asc")
    Inertia.get(
      "/programs",
      {},
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  const handlePageChange = (url) => {
    if (url) {
      Inertia.visit(url)
    }
  }

  const handleDelete = (id) => {
    if (confirm("Are you sure you want to delete this program?")) {
      Inertia.delete(`/programs/${id}`)
    }
  }

  return (
    <AuthenticatedLayout>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-semibold">Programs</h1>
                {can.create && (
                  <Button onClick={() => Inertia.visit("/programs/create")}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Program
                  </Button>
                )}
              </div>

              <div className="mb-6 flex flex-wrap gap-4">
                <form onSubmit={handleSearch} className="flex gap-2">
                  <Input
                    type="text"
                    placeholder="Search programs..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-64"
                  />
                  <Button type="submit" variant="outline">
                    <Search className="h-4 w-4" />
                  </Button>
                </form>

                <div className="flex gap-2 items-center">
                  <Select value={schoolFilter} onChange={handleSchoolFilter} className="w-48">
                    <option value="">All Schools</option>
                    {schools.map((school) => (
                      <option key={school.id} value={school.id}>
                        {school.name}
                      </option>
                    ))}
                  </Select>

                  <Button onClick={resetFilters} variant="outline" size="icon">
                    <RefreshCw className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th
                        scope="col"
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        onClick={() => handleSort("code")}
                      >
                        <div className="flex items-center">
                          Code
                          {sortField === "code" &&
                            (sortDirection === "asc" ? (
                              <ChevronUp className="ml-1 h-4 w-4" />
                            ) : (
                              <ChevronDown className="ml-1 h-4 w-4" />
                            ))}
                        </div>
                      </th>
                      <th
                        scope="col"
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        onClick={() => handleSort("name")}
                      >
                        <div className="flex items-center">
                          Name
                          {sortField === "name" &&
                            (sortDirection === "asc" ? (
                              <ChevronUp className="ml-1 h-4 w-4" />
                            ) : (
                              <ChevronDown className="ml-1 h-4 w-4" />
                            ))}
                        </div>
                      </th>
                      <th
                        scope="col"
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        School
                      </th>
                      <th
                        scope="col"
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        Duration
                      </th>
                      <th
                        scope="col"
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        Status
                      </th>
                      <th
                        scope="col"
                        className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"
                      >
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {programs.data.length > 0 ? (
                      programs.data.map((program) => (
                        <tr key={program.id}>
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {program.code}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{program.name}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{program.school?.name}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {program.duration} {program.duration === 1 ? "year" : "years"}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span
                              className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${program.is_active ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"}`}
                            >
                              {program.is_active ? "Active" : "Inactive"}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => Inertia.visit(`/programs/${program.id}`)}
                              className="text-indigo-600 hover:text-indigo-900 mr-2"
                            >
                              View
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => Inertia.visit(`/programs/${program.id}/edit`)}
                              className="text-yellow-600 hover:text-yellow-900 mr-2"
                            >
                              Edit
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleDelete(program.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Delete
                            </Button>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={6} className="px-6 py-4 text-center text-sm text-gray-500">
                          No programs found.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {programs.links && (
                <div className="mt-4">
                  <Pagination links={programs.links} onPageChange={handlePageChange} />
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ProgramsIndex
