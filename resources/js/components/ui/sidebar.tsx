import React from 'react';
import { Link } from '@inertiajs/react';
import { LayoutDashboard, Users, GraduationCap, Settings, Book, Building, MapPin, Layers, Calendar } from 'lucide-react';

const Sidebar = () => {
    return (
        <div className="bg-blue-800 text-white w-64 h-full flex flex-col">
            <div className="p-4 text-lg font-bold border-b border-blue-700">
                MyClassTimetable
            </div>
            <nav className="flex-1 p-4 space-y-2">
                <Link href="/dashboard" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
                    <LayoutDashboard className="h-5 w-5" />
                    <span>Dashboard</span>
                </Link>
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
                <Link href="/groups" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
                    <Layers className="h-5 w-5" />
                    <span>Groups</span>
                </Link>
                <Link href="/semesters" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
                    <Calendar className="h-5 w-5" />
                    <span>Semesters</span>
                </Link>
                <Link href="/timetable" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
                    <GraduationCap className="h-5 w-5" />
                    <span>Timetable</span>
                </Link>
                <Link href="/settings" className="flex items-center space-x-2 hover:bg-blue-700 p-2 rounded">
                    <Settings className="h-5 w-5" />
                    <span>Settings</span>
                </Link>
            </nav>
        </div>
    );
};

export default Sidebar;
