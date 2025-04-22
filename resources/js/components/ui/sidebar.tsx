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
  FileSpreadsheet,
  BookOpen,
  DownloadCloud,
  Puzzle,
  Briefcase,
} from 'lucide-react';

const Sidebar = () => {
  const { auth } = usePage().props as any;
  const roles: string[] = auth?.user?.roles?.map((r: any) => r.name) ?? [];
  const permissions: string[] = auth?.user?.permissions ?? [];
  
  const hasRole = (role: string) => roles.includes(role);
  const can = (permission: string) => permissions.includes(permission);
  
  // Special case for Admin
  const isAdmin = hasRole('Admin');
  
  return (
    <div className="bg-blue-800 text-white w-64 h-full flex flex-col">
      <div className="p-4 text-lg font-bold border-b border-blue-700">
        <h2 className="text-lg font-bold">Timetable Management</h2>
      </div>
      
           
      <nav className="flex-1 p-4 space-y-2">
        {/* Dashboard - available to all authenticated users */}
        {(isAdmin || can('view-dashboard')) && (
          <Link href="/dashboard" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <LayoutDashboard className="h-5 w-5" />
            <span>Dashboard</span>
          </Link>
        )}
        
        {/* User Management */}
        {(isAdmin || can('manage-users')) && (
          <Link href="/users" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Users className="h-5 w-5" />
            <span>Users</span>
          </Link>
        )}
        
        {/* Roles & Permissions Management */}
        {/* Roles & Permissions Management */}
        {(isAdmin || can('manage-roles')) && (
          <Link href="/roles" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
             <Users className="h-5 w-5 rotate-12" /> 
                <span>Roles</span>
          </Link>
        )}
        
        {/* Faculty Management */}
        {(isAdmin || can('manage-faculties')) && (
          <Link href="/faculties" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Building className="h-5 w-5" />
            <span>Faculties</span>
          </Link>
        )}
        
        {/* Units Management */}
        {(isAdmin || can('manage-units') || can('manage-faculty-units') || can('view-units')) && (
          <Link 
            href={can('manage-units') ? "/units" : can('manage-faculty-units') ? "/faculty/units" : "/units"} 
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <Book className="h-5 w-5" />
            <span>{can('manage-faculty-units') ? "Faculty Units" : "Units"}</span>
          </Link>
        )}
        
        {/* Classroom Management */}
        {(isAdmin || can('manage-classrooms')) && (
          <Link href="/classrooms" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <MapPin className="h-5 w-5" />
            <span>Classrooms</span>
          </Link>
        )}
        
        {/* Semester Management */}
        {(isAdmin || can('manage-semesters') || can('manage-faculty-semesters')) && (
          <Link 
            href={can('manage-semesters') ? "/semesters" : "/faculty/semesters"} 
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <Calendar className="h-5 w-5" />
            <span>{can('manage-faculty-semesters') ? "Faculty Semesters" : "Semesters"}</span>
          </Link>
        )}
        
        {/* Enrollment Management */}
        {(isAdmin || can('manage-enrollments') || can('manage-faculty-enrollments')) && (
          <Link 
            href={can('manage-enrollments') ? "/enrollments" : "/faculty/enrollments"} 
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <GraduationCap className="h-5 w-5" />
            <span>{can('manage-faculty-enrollments') ? "Faculty Enrollments" : "Enrollments"}</span>
          </Link>
        )}
        
        {/* Time Slots */}
        {(isAdmin || can('manage-time-slots')) && (
          <Link href="/timeslots" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Layers className="h-5 w-5" />
            <span>Time Slots</span>
          </Link>
        )}
        
        {/* Exam Timetable Management */}
        {(isAdmin || can('create-timetable') || can('view-timetable')) && (
          <Link href="/examtimetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <FileSpreadsheet className="h-5 w-5" />
            <span>Exam Timetable</span>
          </Link>
        )}
        
        {/* Process Timetable */}
        {(isAdmin || can('process-timetable')) && (
          <Link href="/process-timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Puzzle className="h-5 w-5" />
            <span>Process Timetable</span>
          </Link>
        )}
        
        {/* Solve Conflicts */}
        {(isAdmin || can('solve-conflicts')) && (
          <Link href="/solve-conflicts" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Briefcase className="h-5 w-5" />
            <span>Solve Conflicts</span>
          </Link>
        )}
        
        {/* Lecturer View Own Timetable */}
        {(hasRole('Lecturer') && can('view-own-timetable')) && (
          <Link href="/lecturer/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Timetable</span>
          </Link>
        )}
        
        {/* Lecturer View Own Units */}
        {(hasRole('Lecturer') && can('view-own-units')) && (
          <Link href="/lecturer/units" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <BookOpen className="h-5 w-5" />
            <span>My Units</span>
          </Link>
        )}
        
        {/* Student View Own Timetable */}
        {(hasRole('Student') && can('view-own-timetable')) && (
          <Link href="/student/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Calendar className="h-5 w-5" />
            <span>My Exam Schedule</span>
          </Link>
        )}
        
        {/* Student View Own Units */}
        {(hasRole('Student') && can('view-own-units')) && (
          <Link href="/student/units" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <BookOpen className="h-5 w-5" />
            <span>My Units</span>
          </Link>
        )}
        
        {/* Download Timetable Options */}
        {(isAdmin || can('download-timetable') || can('download-own-timetable') || can('download-faculty-timetable')) && (
          <Link 
            href={
              can('download-timetable') 
                ? "/download-timetable" 
                : can('download-faculty-timetable') 
                  ? "/faculty/timetable/download" 
                  : hasRole('Lecturer') 
                    ? "/lecturer/timetable/download" 
                    : "/student/timetable/download"
            } 
            className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded"
          >
            <DownloadCloud className="h-5 w-5" />
            <span>Download Timetable</span>
          </Link>
        )}
        
        {/* Settings */}
        {(isAdmin || can('manage-settings')) && (
          <Link href="/settings" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
            <Settings className="h-5 w-5" />
            <span>Settings</span>
          </Link>
        )}
      </nav>
    </div>
  );
};

export default Sidebar;