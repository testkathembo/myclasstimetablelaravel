import { useState } from "react";
import { Link, usePage } from "@inertiajs/react"; // Import usePage
import { Home, Users, Settings, ChevronDown, LayoutDashboard, GraduationCap, Briefcase } from "lucide-react"; // Icons
import { Button } from "@/components/ui/button";

const Sidebar = () => {
  const { auth } = usePage().props as { auth: { user: any } };

  const hasRole = (role: string) => auth?.user?.roles?.includes(role);

  return (
    <div className="Sidebar"> {/* Reduced padding */}
      
      {/* Navigation Menu */}
      <nav className="flex-1">
        <ul>
          <li>
            {hasRole('SuperAdmin') && (
              <Link href="/admin/dashboard">Admin Dashboard</Link>
            )}
          </li>
          <li>
            {hasRole('SchoolAdmin') && (
              <Link href="/schooladmin/dashboard">School Admin Dashboard</Link>
            )}
          </li>
          <li>
            {hasRole('ExamOffice') && (
              <Link href="/examoffice/dashboard">Exam Office Dashboard</Link>
            )}
          </li>
          <li>
            {hasRole('Lecturer') && (
              <Link href="/lecturer/dashboard">Lecturer Dashboard</Link>
            )}
          </li>
          <li>
            {hasRole('Student') && (
              <Link href="/student/dashboard">Student Dashboard</Link>
            )}
          </li>
        </ul>
      </nav>

    </div>
  );
};

export default Sidebar;
