"use client";

import React, { useState, FormEvent } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { AlertCircle } from "lucide-react";

interface ClassTimetable {
  id: number;
  day: string;
  date: string;
  unit_name: string;
  unit_code: string;
  venue: string;
  location: string;
  status: string;
  start_time: string;
  end_time: string;
}

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedClassTimetables {
  data: ClassTimetable[];
  links: PaginationLinks[];
  total: number;
  per_page: number;
  current_page: number;
}

const ClassTimetable = ({
  classTimetables,
  perPage,
  search,
  can,
}: {
  classTimetables: PaginatedClassTimetables;
  perPage: number;
  search: string;
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
    download: boolean;
  };
}) => {
  const [searchValue, setSearchValue] = useState(search || ""); // Ensure searchValue is never null
  const [rowsPerPage, setRowsPerPage] = useState(perPage || 10); // Ensure rowsPerPage is never null
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "">("");
  const [selectedTimetable, setSelectedTimetable] = useState<ClassTimetable | null>(null);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchValue(e.target.value);
  };

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault();
    router.get("/classtimetables", { search: searchValue, perPage: rowsPerPage });
  };

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value);
    setRowsPerPage(newPerPage);
    router.get("/classtimetables", { search: searchValue, perPage: newPerPage });
  };

  const handleDownloadTimetable = () => {
    window.open("/classtimetables/download", "_blank");
  };

  const handleOpenModal = (type: "view" | "edit" | "delete" | "create", timetable: ClassTimetable | null) => {
    setModalType(type);
    setSelectedTimetable(timetable);
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setModalType("");
    setSelectedTimetable(null);
  };

  const handleDelete = () => {
    if (!selectedTimetable) return;

    router.delete(`/classtimetables/${selectedTimetable.id}`, {
      onSuccess: () => {
        setIsModalOpen(false);
        setSelectedTimetable(null);
      },
      onError: () => {
        alert("Failed to delete the class timetable.");
      },
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Class Timetable</h1>

        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                + Add Class
              </Button>
            )}
            {can.download && (
              <Button onClick={handleDownloadTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue} // Ensure value is never null
              onChange={handleSearchChange}
              placeholder="Search class timetable..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              Search
            </Button>
          </form>

          <div>
            <label className="mr-2">Rows per page:</label>
            <select
              value={rowsPerPage} // Ensure value is never null
              onChange={handlePerPageChange}
              className="border rounded p-2"
            >
              {[5, 10, 15, 20].map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        </div>

        {classTimetables?.data?.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full mt-6 border text-sm text-left">
                <thead className="bg-gray-100 border-b">
                  <tr>
                    <th className="px-3 py-2">ID</th>
                    <th className="px-3 py-2">Day</th>
                    <th className="px-3 py-2">Date</th>
                    <th className="px-3 py-2">Unit Code</th>
                    <th className="px-3 py-2">Unit Name</th>
                    <th className="px-3 py-2">Venue</th>
                    <th className="px-3 py-2">Location</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Time</th>
                    <th className="px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {classTimetables.data.map((classtimetable) => (
                    <tr key={classtimetable.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2">{classtimetable.id}</td>
                      <td className="px-3 py-2">{classtimetable.day}</td>
                      <td className="px-3 py-2">{classtimetable.date}</td>
                      <td className="px-3 py-2">{classtimetable.unit_code}</td>
                      <td className="px-3 py-2">{classtimetable.unit_name}</td>
                      <td className="px-3 py-2">{classtimetable.venue}</td>
                      <td className="px-3 py-2">{classtimetable.location}</td>
                      <td className="px-3 py-2">{classtimetable.status}</td>
                      <td className="px-3 py-2">
                        {classtimetable.start_time} - {classtimetable.end_time}
                      </td>
                      <td className="px-3 py-2 flex space-x-2">
                        <Button
                          onClick={() => handleOpenModal("view", classtimetable)}
                          className="bg-blue-500 hover:bg-blue-600 text-white"
                        >
                          View
                        </Button>
                        {can.edit && (
                          <Button
                            onClick={() => handleOpenModal("edit", classtimetable)}
                            className="bg-yellow-500 hover:bg-yellow-600 text-white"
                          >
                            Edit
                          </Button>
                        )}
                        {can.delete && (
                          <Button
                            onClick={() => handleOpenModal("delete", classtimetable)}
                            className="bg-red-500 hover:bg-red-600 text-white"
                          >
                            Delete
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {classTimetables.links && classTimetables.links.length > 3 && (
              <div className="flex justify-center mt-4">
                <nav className="flex items-center">
                  {classTimetables.links.map((link, index) => (
                    <button
                      key={index}
                      onClick={() => {
                        if (link.url) {
                          router.visit(link.url);
                        }
                      }}
                      className={`px-3 py-1 mx-1 border rounded ${
                        link.active ? "bg-blue-500 text-white" : "bg-white text-gray-700"
                      } ${!link.url ? "opacity-50 cursor-not-allowed" : "hover:bg-gray-100"}`}
                      disabled={!link.url}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </nav>
              </div>
            )}
          </>
        ) : (
          <p className="mt-6 text-gray-600">No class timetables available yet.</p>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded shadow-md w-[500px] max-h-[90vh] overflow-y-auto">
              {modalType === "view" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Class Timetable</h2>
                  <div className="space-y-2">
                    <p>
                      <strong>Day:</strong> {selectedTimetable.day}
                    </p>
                    <p>
                      <strong>Date:</strong> {selectedTimetable.date}
                    </p>
                    <p>
                      <strong>Unit Code:</strong> {selectedTimetable.unit_code}
                    </p>
                    <p>
                      <strong>Unit Name:</strong> {selectedTimetable.unit_name}
                    </p>
                    <p>
                      <strong>Time:</strong> {selectedTimetable.start_time} - {selectedTimetable.end_time}
                    </p>
                    <p>
                      <strong>Venue:</strong> {selectedTimetable.venue}
                    </p>
                    <p>
                      <strong>Location:</strong> {selectedTimetable.location}
                    </p>
                    <p>
                      <strong>Status:</strong> {selectedTimetable.status}
                    </p>
                  </div>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">
                    Close
                  </Button>
                </>
              )}

              {modalType === "delete" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Delete Class Timetable</h2>
                  <p>Are you sure you want to delete this timetable?</p>
                  <div className="mt-4 flex justify-end space-x-2">
                    <Button onClick={handleDelete} className="bg-red-500 hover:bg-red-600 text-white">
                      Delete
                    </Button>
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white">
                      Cancel
                    </Button>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
};

export default ClassTimetable;
