"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "../../components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"

interface ExamTimetable {
  id: number
  day: string
  date: string
  unit_code: string
  unit_name: string  
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  semester_name: string // Added semester_name
}

interface Enrollment {
  id: number
  unit_name: string
  semester_id: number
  unit_code: string
  student_count: number
  lecturer_id: number | null
  lecturer_name: string | null
}

interface Classroom {
  id: number
  name: string
  capacity: number
  location: string
}

interface Semester {
  id: number
  name: string
}

interface TimeSlot {
  id: number
  day: string
  date: string
  start_time: string
  end_time: string
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedExamTimetables {
  data: ExamTimetable[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

interface FormState {
  id: number
  day: string
  date: string
  enrollment_id: number
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
}

// Helper function to ensure time is in H:i format
const formatTimeToHi = (timeStr: string) => {
  // If the time already has the correct format (H:i), return it
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeStr)) {
    return timeStr
  }

  // If the time has seconds (H:i:s), remove them
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(timeStr)) {
    return timeStr.substring(0, 5)
  }

  // If it's in 12-hour format with AM/PM, convert to 24-hour
  if (timeStr.includes("AM") || timeStr.includes("PM")) {
    const [time, modifier] = timeStr.split(" ")
    let [hours, minutes] = time.split(":").map(Number)

    if (modifier === "PM" && hours < 12) hours += 12
    if (modifier === "AM" && hours === 12) hours = 0

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`
  }

  // If we can't parse it, return as is (validation will catch it)
  return timeStr
}

// Helper function to check for time overlap
const checkTimeOverlap = (exam: ExamTimetable, date: string, startTime: string, endTime: string) => {
  if (exam.date !== date) return false

  return (
    (exam.start_time <= startTime && exam.end_time > startTime) ||
    (exam.start_time < endTime && exam.end_time >= endTime) ||
    (exam.start_time >= startTime && exam.end_time <= endTime)
  )
}

const ExamTimetable = () => {
  const { examTimetables, perPage, search, semesters, can } = usePage().props as unknown as {
    examTimetables: PaginatedExamTimetables;
    perPage: number;
    search: string;
    semesters: Semester[];
    can: {
      create: boolean;
      edit: boolean;
      delete: boolean;
      process: boolean;
      solve_conflicts: boolean;
      download: boolean;
    };
  };

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "">("");
  const [selectedTimetable, setSelectedTimetable] = useState<ExamTimetable | null>(null);
  const [formState, setFormState] = useState<FormState | null>(null);

  const handleOpenModal = (type: "view" | "edit" | "delete", timetable: ExamTimetable) => {
    setModalType(type);
    setSelectedTimetable(timetable);
    setFormState(timetable ? { ...timetable, enrollment_id: 0 } : null);
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setModalType("");
    setSelectedTimetable(null);
    setFormState(null);
  };

  const handleDelete = () => {
    if (!selectedTimetable) return;

    router.delete(`/exam-timetables/${selectedTimetable.id}`, {
      onSuccess: () => {
        setIsModalOpen(false);
        setSelectedTimetable(null);
      },
      onError: () => {
        alert("Failed to delete the exam timetable.");
      },
    });
  };

  const handleDateChange = (date: string) => {
    if (!formState) return;

    // Calculate the day of the week from the selected date
    const dayOfWeek = new Date(date).toLocaleDateString("en-US", { weekday: "long" });

    // Update the form state with the new date and day
    setFormState((prev) => ({
      ...prev!,
      date,
      day: dayOfWeek,
    }));
  };

  const handleProcessTimetable = () => {
    router.post("/process-timetable", {}, {
      onSuccess: () => alert("Timetable processed successfully."),
      onError: () => alert("Failed to process timetable."),
    });
  };

  const handleSolveConflicts = () => {
    router.get("/solve-conflicts", {}, {
      onSuccess: () => alert("Conflicts resolved successfully."),
      onError: () => alert("Failed to resolve conflicts."),
    });
  };

  const handleDownloadTimetable = () => {
    window.open("/download-timetable", "_blank");
  };

  return (
    <AuthenticatedLayout>
      <Head title="Exam Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Exam Timetable</h1>
        
        <div className="flex justify-between items-center mb-4">
          <div className="flex space-x-2">
            {can.create && (
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                + Add Exam
              </Button>
            )}
            
            {can.process && (
              <Button onClick={handleProcessTimetable} className="bg-blue-500 hover:bg-blue-600">
                Process Timetable
              </Button>
            )}
            
            {can.solve_conflicts && (
              <Button onClick={handleSolveConflicts} className="bg-purple-500 hover:bg-purple-600">
                Solve Conflicts
              </Button>
            )}
            
            {can.download && (
              <Button onClick={handleDownloadTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                Download
              </Button>
            )}
          </div>
          
          <form className="flex items-center space-x-2">
            <input
              type="text"
              value={search}
              placeholder="Search exam timetable..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              Search
            </Button>
          </form>
          <div>
            <label className="mr-2">Rows per page:</label>
            <select value={perPage} className="border rounded p-2">
              {[5, 10, 15, 20].map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        </div>

        {examTimetables.data.length > 0 ? (
          <>
            <table className="w-full mt-6 border text-sm text-left">
              <thead className="bg-gray-100 border-b">
                <tr>
                  <th className="px-3 py-2">ID</th>
                  <th className="px-3 py-2">Day</th>
                  <th className="px-3 py-2">Date</th>
                  <th className="px-3 py-2">Unit Code</th> {/* Unit Code column */}
                  <th className="px-3 py-2">Unit Name</th>
                  <th className="px-3 py-2">Semester</th>
                  <th className="px-3 py-2">Venue</th>
                  <th className="px-3 py-2">Time</th>
                  <th className="px-3 py-2">Chief Invigilator</th>
                  <th className="px-3 py-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                {examTimetables.data.map((exam) => (
                  <tr key={exam.id} className="border-b">
                    <td className="px-3 py-2">{exam.id}</td>
                    <td className="px-3 py-2">{exam.day}</td>
                    <td className="px-3 py-2">{exam.date}</td>
                    <td className="px-3 py-2">{exam.unit_code}</td> {/* Display Unit Code */}
                    <td className="px-3 py-2">{exam.unit_name}</td>
                    <td className="px-3 py-2">{exam.semester_name}</td>
                    <td className="px-3 py-2">{exam.venue}</td>
                    <td className="px-3 py-2">{exam.start_time} - {exam.end_time}</td>
                    <td className="px-3 py-2">{exam.chief_invigilator}</td>
                    <td className="px-3 py-2 flex space-x-2">
                      <Button
                        onClick={() => handleOpenModal("view", exam)}
                        className="bg-blue-500 hover:bg-blue-600 text-white"
                      >
                        View
                      </Button>
                      {can.edit && (
                        <Button
                          onClick={() => handleOpenModal("edit", exam)}
                          className="bg-yellow-500 hover:bg-yellow-600 text-white"
                        >
                          Edit
                        </Button>
                      )}
                      {can.delete && (
                        <Button
                          onClick={() => handleOpenModal("delete", exam)}
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
          </>
        ) : (
          <p className="mt-6 text-gray-600">No exam timetables available yet.</p>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div className="bg-white p-6 rounded shadow-md w-96">
              {modalType === "view" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Exam Timetable</h2>
                  <p><strong>Day:</strong> {selectedTimetable.day}</p>
                  <p><strong>Date:</strong> {selectedTimetable.date}</p>
                  <p><strong>Unit Code:</strong> {selectedTimetable.unit_code}</p>
                  <p><strong>Unit Name:</strong> {selectedTimetable.unit_name}</p>
                  <p><strong>Semester:</strong> {selectedTimetable.semester_name}</p>
                  <p><strong>Time:</strong> {selectedTimetable.start_time} - {selectedTimetable.end_time}</p>
                  <p><strong>Venue:</strong> {selectedTimetable.venue}</p>
                  <p><strong>Chief Invigilator:</strong> {selectedTimetable.chief_invigilator}</p>
                  <Button onClick={handleCloseModal} className="mt-4 bg-gray-400 text-white">Close</Button>
                </>
              )}

              {modalType === "edit" && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Edit Exam Timetable</h2>
                  <form>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Day</label>
                    <input
                      type="text"
                      value={formState.day}
                      className="w-full border rounded p-2 mb-3"
                      readOnly
                    />
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input
                      type="date"
                      value={formState.date}
                      onChange={(e) => handleDateChange(e.target.value)}
                      className="w-full border rounded p-2 mb-3"
                    />
                    {/* Add more fields as needed */}
                    <Button type="submit" className="bg-blue-500 hover:bg-blue-600 text-white">Save</Button>
                    <Button onClick={handleCloseModal} className="ml-2 bg-gray-400 text-white">Cancel</Button>
                  </form>
                </>
              )}

              {modalType === "delete" && selectedTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">Delete Exam Timetable</h2>
                  <p>Are you sure you want to delete this timetable?</p>
                  <div className="mt-4 flex justify-end space-x-2">
                    <Button onClick={handleDelete} className="bg-red-500 hover:bg-red-600 text-white">Delete</Button>
                    <Button onClick={handleCloseModal} className="bg-gray-400 text-white">Cancel</Button>
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

export default ExamTimetable;