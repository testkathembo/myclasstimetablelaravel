import { useState } from "react";
import { Link } from "@inertiajs/react";
import { Home, Users, Settings, ChevronDown, LayoutDashboard, GraduationCap, Briefcase } from "lucide-react"; // Icons
import { Button } from "@/components/ui/button";

const Sidebar = () => {
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);

  return (
    <div className="h-full w-64 bg-blue-900 text-white shadow-lg flex flex-col p-2"> {/* Reduced padding */}
      
      {/* Navigation Menu */}
      <nav className="flex-1">
        <ul className="space-y-1"> {/* Reduced spacing */}
          
          {/* Dashboard */}
          <li className="flex items-start">
            <Link href="/dashboard" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <LayoutDashboard className="h-5 w-5 mr-2" />
                <span>Dashboard</span>
              </Button>
            </Link>
          </li>

          {/* Users Dropdown */}
          <li className="flex items-start">
            <button
              onClick={() => setUserDropdownOpen(!userDropdownOpen)}
              className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md"
            >
              <Users className="h-5 w-5 mr-2" />
              <span>Users</span>
              <ChevronDown className={`h-5 w-5 ml-auto transition-transform ${userDropdownOpen ? "rotate-180" : ""}`} />
            </button>
          </li>
            
          {/* Dropdown Content */}
          {userDropdownOpen && (
            <ul className="ml-4 mt-1 space-y-1">
              <li className="flex items-start">
                <Link href="/users/admin" className="w-full">
                  <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                    <Briefcase className="h-5 w-5 mr-2" />
                    <span>Admin</span>
                  </Button>
                </Link>
              </li>
              <li className="flex items-start">
                <Link href="/users/lecturer" className="w-full">
                  <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                    <Users className="h-5 w-5 mr-2" />
                    <span>Lecturer</span>
                  </Button>
                </Link>
              </li>
              <li className="flex items-start">
                <Link href="/users/student" className="w-full">
                  <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                    <GraduationCap className="h-5 w-5 mr-2" />
                    <span>Student</span>
                  </Button>
                </Link>
              </li>
            </ul>
          )}

          {/* Other Menu Items */}
          <li className="flex items-start">
            <Link href="/facilities" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Facilities</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/units" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <GraduationCap className="h-5 w-5 mr-2" />
                <span>Units</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/classrooms" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Classrooms</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/semesters" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Semesters</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/enrollment" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Enrollment Group</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/timeslots" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Timeslots</span>
              </Button>
            </Link>
          </li>
          <li className="flex items-start">
            <Link href="/timetable" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Users className="h-5 w-5 mr-2" />
                <span>Timetable</span>
              </Button>
            </Link>
          </li>
          
          {/* Settings */}
          <li className="flex items-start">
            <Link href="/settings" className="w-full">
              <Button variant="ghost" className="w-full flex items-center justify-start text-white hover:bg-blue-700 px-2 py-2 rounded-md">
                <Settings className="h-5 w-5 mr-2" />
                <span>Settings</span>
              </Button>
            </Link>
          </li>
        </ul>
      </nav>

    </div>
  );
};

export default Sidebar;
