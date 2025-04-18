import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
  LayoutDashboard,
  Users,
  GraduationCap,
  Settings,
  Book,
  Building,
  MapPin,
  Layers,
  Calendar,
} from 'lucide-react';

const Sidebar = () => {
  const { auth } = usePage().props as any;
  const roles: string[] = auth?.user?.roles ?? [];

  const hasRole = (role: string) => roles.includes(role);

  return (
    <div className="bg-blue-800 text-white w-64 h-full flex flex-col">
      <div className="p-4 text-lg font-bold border-b border-blue-700">
        <h2 className="text-lg font-bold">Timetable Management</h2>
      </div>

      <nav className="flex-1 p-4 space-y-2">
        <Link href="/dashboard" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
          <LayoutDashboard className="h-5 w-5" />
          <span>Dashboard</span>
        </Link>

        {/* Admin-only section */}
        {hasRole('Admin') && (
          <>
            <Link href="/users" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Users className="h-5 w-5" />
              <span>Users</span>
            </Link>
            <Link href="/faculties" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Building className="h-5 w-5" />
              <span>Faculties</span>
            </Link>
            <Link href="/units" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Book className="h-5 w-5" />
              <span>Units</span>
            </Link>
            <Link href="/classrooms" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <MapPin className="h-5 w-5" />
              <span>Classrooms</span>
            </Link>
            <Link href="/semesters" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Calendar className="h-5 w-5" />
              <span>Semesters</span>
            </Link>
            <Link href="/enrollments" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <GraduationCap className="h-5 w-5" />
              <span>Enrollments</span>
            </Link>
            <Link href="/timeslots" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Layers className="h-5 w-5" />
              <span>Time Slots</span>
            </Link>
            <Link href="/settings" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
              <Settings className="h-5 w-5" />
              <span>Settings</span>
            </Link>
          </>
        )}

        {/* Admin & Exam Office */}
        {(hasRole('Admin') || hasRole('Exam Office')) && (
          <Link href="/examtimetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <GraduationCap className="h-5 w-5" />
            <span>Exam Timetable</span>
          </Link>
        )}

        {/* Lecturer-specific */}
        {hasRole('Lecturer') && (
          <Link href="/lecturer/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Timetable</span>
          </Link>
        )}

        {/* Student-specific */}
        {hasRole('Student') && (
          <Link href="/student/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Exam Schedule</span>
          </Link>
        )}
      </nav>
    </div>
  );
};

export default Sidebar;
