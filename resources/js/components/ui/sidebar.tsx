import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import RoleAwareComponent from '@/Components/RoleAwareComponent';
import { Home, Users, Building, Clock, Calendar, ClipboardList, Layers, ClipboardCheck, Clipboard, ChevronDown, ChevronUp, Settings, BookOpen, House } from 'lucide-react';

export default function Sidebar() {
  const [openSchool, setOpenSchool] = useState<string | null>(null);
  const [openProgram, setOpenProgram] = useState<string | null>(null);

  const toggleSchool = (school: string) => {
    setOpenSchool(openSchool === school ? null : school);
  };

  const toggleProgram = (program: string) => {
    setOpenProgram(openProgram === program ? null : program);
  };

  const schools = [
    {
      name: 'SCES',
      programs: [
        {
          name: 'BSICS',
          link: '/schools/sces/bsics',
          components: [
            { name: 'Classrooms', icon: <Building className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/classrooms' },
            { name: 'Class Timetables', icon: <Calendar className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/classtimetables' },
            { name: 'Exam Rooms', icon: <House className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/examrooms' },
            { name: 'Exam Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/examtimeslots' },
            { name: 'Exam Timetable', icon: <ClipboardCheck className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/examtimetable' },
            { name: 'Enrollments', icon: <ClipboardList className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/enrollments' },
            { name: 'Units', icon: <BookOpen className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/units' },
            { name: 'Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bsics/timeslots' },
          ],
        },
        {
          name: 'BBIT',
          link: '/schools/sces/bbit',
          components: [
            { name: 'Classrooms', icon: <Building className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/classrooms' },
            { name: 'Class Timetables', icon: <Calendar className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/classtimetables' },
            { name: 'Exam Rooms', icon: <House className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/examrooms' },
            { name: 'Exam Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/examtimeslots' },
            { name: 'Exam Timetable', icon: <ClipboardCheck className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/examtimetable' },
            { name: 'Enrollments', icon: <ClipboardList className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/enrollments' },
            { name: 'Units', icon: <BookOpen className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/units' },
            { name: 'Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bbit/timeslots' },
          ],
        },
        {
          name: 'BSEEE',
          link: '/schools/sces/bseee',
          components: [
            { name: 'Classrooms', icon: <Building className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/classrooms' },
            { name: 'Class Timetables', icon: <Calendar className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/classtimetables' },
            { name: 'Exam Rooms', icon: <House className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/examrooms' },
            { name: 'Exam Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/examtimeslots' },
            { name: 'Exam Timetable', icon: <ClipboardCheck className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/examtimetable' },
            { name: 'Enrollments', icon: <ClipboardList className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/enrollments' },
            { name: 'Units', icon: <BookOpen className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/units' },
            { name: 'Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bseee/timeslots' },
          ],
        },
        {
          name: 'BSCNCS',
          link: '/schools/sces/bscncs',
          components: [
            { name: 'Classrooms', icon: <Building className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/classrooms' },
            { name: 'Class Timetables', icon: <Calendar className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/classtimetables' },
            { name: 'Exam Rooms', icon: <House className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/examrooms' },
            { name: 'Exam Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/examtimeslots' },
            { name: 'Exam Timetable', icon: <ClipboardCheck className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/examtimetable' },
            { name: 'Enrollments', icon: <ClipboardList className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/enrollments' },
            { name: 'Units', icon: <BookOpen className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/units' },
            { name: 'Time Slots', icon: <Clock className="mr-3 h-5 w-5" />, link: '/schools/sces/bscncs/timeslots' },
          ],
        },
      ],
    },
    {
      name: 'BCOM',
      programs: [],
    },
    {
      name: 'LAW',
      programs: [],
    },
    {
      name: 'TOURISM',
      programs: [],
    },
    {
      name: 'HUMANITIES',
      programs: [],
    },
  ];

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
                <Layers className="mr-3 h-5 w-5" />
                Roles & Permissions
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

          {/* Schools Section */}
          <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Schools</p>
          {schools.map((school) => (
            <div key={school.name} className="mt-1">
              <button
                onClick={() => toggleSchool(school.name)}
                className="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <div className="flex items-center">
                  <Building className="mr-3 h-5 w-5" />
                  {school.name}
                </div>
                {openSchool === school.name ? (
                  <ChevronUp className="h-5 w-5" />
                ) : (
                  <ChevronDown className="h-5 w-5" />
                )}
              </button>
              {openSchool === school.name && (
                <div className="ml-8 mt-1 space-y-1">
                  {school.programs.map((program) => (
                    <div key={program.name}>
                      <button
                        onClick={() => toggleProgram(program.name)}
                        className="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                      >
                        <div className="flex items-center">
                          <Clipboard className="mr-3 h-5 w-5" />
                          {program.name}
                        </div>
                        {openProgram === program.name ? (
                          <ChevronUp className="h-5 w-5" />
                        ) : (
                          <ChevronDown className="h-5 w-5" />
                        )}
                      </button>
                      {openProgram === program.name && (
                        <div className="ml-8 mt-1 space-y-1">
                          {program.components.map((component) => (
                            <Link
                              key={component.name}
                              href={component.link}
                              className="block px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                            >
                              {component.icon}
                              {component.name}
                            </Link>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </nav>
      </div>
    </div>
  );
}