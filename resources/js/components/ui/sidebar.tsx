import React from 'react';
import { Link } from '@inertiajs/react';
import RoleAwareComponent from '@/Components/RoleAwareComponent';
import { Home, Calendar, Users, Settings, FileText, BookOpen, Clock, Building, Download, BarChart2, Shield, User, HouseIcon, BookA } from 'lucide-react';
import TimeSlots from '@/Pages/TimeSlots';
import Enrollments from '@/Pages/Enrollments/Index';
import ExamTimetable from '@/Pages/ExamTimetable';
import EnrollmentGroups from '@/Pages/EnrollmentGroups';
import Units from '@/Pages/Units';

export default function Sidebar() {
  return (
    <div className="w-64 bg-blue-800 text-white h-full flex flex-col">
      <div className="p-4 border-b border-gray-700">
        <h1 className="text-xl font-bold">Timetabling System Management</h1>
      </div>
      
      <div className="flex-1 overflow-y-auto py-4">
        <nav className="px-2 space-y-1">
          {/* Dashboard - Available to all */}
          <Link 
            href="/dashboard" 
            className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
          >
            <Home className="mr-3 h-5 w-5" />
            Dashboard
          </Link>
          
          {/* Class Timetables */}
          <Link
            href="/classtimetables"
            className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
          >
            <Calendar className="mr-3 h-5 w-5" />
            Class Timetables
          </Link>
          
          {/* Admin Section */}
          <RoleAwareComponent requiredRoles={['Admin']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Administration
              </p>
              
              <Link 
                href="/users" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Users className="mr-3 h-5 w-5" />
                Users
              </Link>
              
              <Link 
                href="/roles" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Shield className="mr-3 h-5 w-5" />
                Roles & Permissions
              </Link>

              <Link 
                href="/faculties" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Building className="mr-3 h-5 w-5" />
                Faculties
              </Link>

              <Link 
                href="/units" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookA className="mr-3 h-5 w-5" />
                Units
              </Link>

              <Link 
                href="/semesters" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookA className="mr-3 h-5 w-5" />
                Semesters
              </Link>

              <Link 
                href="/enrollments" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookA className="mr-3 h-5 w-5" />
                Enrollments
              </Link>

              <Link 
                href="/classrooms" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <HouseIcon className="mr-3 h-5 w-5" />
                Classrooms
              </Link>

              <Link 
                href="/examrooms" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <HouseIcon className="mr-3 h-5 w-5" />
                Exam rooms
              </Link>

              <Link 
                href="/timeslots" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-5 w-5" />
                Exam Time Slots
              </Link>              

              <Link 
                href="/examtimetable" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-5 w-5" />
                Exam Timetable
              </Link> 
              
              <Link 
                href="/settings" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Settings className="mr-3 h-5 w-5" />
                Settings
              </Link>
            </div>
          </RoleAwareComponent>
          
          {/* Exam Office Section */}
          <RoleAwareComponent requiredRoles={['Exam office']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Exam Office
              </p>
              
              <Link 
                href="/manage-timetable" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                Manage Timetable
              </Link>
              
              <Link 
                href="/classrooms" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Building className="mr-3 h-5 w-5" />
                Classrooms
              </Link>
              
              <Link 
                href="/time-slots" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Clock className="mr-3 h-5 w-5" />
                Time Slots
              </Link>
              
              <Link 
                href="/conflicts" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Shield className="mr-3 h-5 w-5" />
                Resolve Conflicts
              </Link>
            </div>
          </RoleAwareComponent>
          
          {/* Faculty Admin Section */}
          <RoleAwareComponent requiredRoles={['Faculty Admin']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Faculty Admin
              </p>
              
              <Link 
                href="/units" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-5 w-5" />
                Units
              </Link>
              
              <Link 
                href="/enrollments" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Users className="mr-3 h-5 w-5" />
                Enrollments
              </Link>
              
              <Link 
                href="/semesters" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                Semesters
              </Link>
            </div>
          </RoleAwareComponent>
          
          {/* Lecturer Section */}
          <RoleAwareComponent requiredRoles={['Lecturer']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Teaching
              </p>
              
              <Link 
                href="/my-classes" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-5 w-5" />
                My Classes
              </Link>
              
              <Link 
                href="/exam-supervision" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                Exam Supervision
              </Link>
            </div>
          </RoleAwareComponent>
          
          {/* Student Section */}
          <RoleAwareComponent requiredRoles={['Student']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                My Studies
              </p>
              
              <Link 
                href="/my-enrollments" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-5 w-5" />
                My Enrollments
              </Link>
              
              <Link 
                href="/my-exams" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Exams
              </Link>
            </div>
          </RoleAwareComponent>
          
          {/* Reports - Permission-based */}
          <RoleAwareComponent requiredPermissions={['generate-reports']}>
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Reports
              </p>
              
              <Link 
                href="/reports" 
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BarChart2 className="mr-3 h-5 w-5" />
                Generate Reports
              </Link>
            </div>
          </RoleAwareComponent>
        </nav>
      </div>
    </div>
  );
}