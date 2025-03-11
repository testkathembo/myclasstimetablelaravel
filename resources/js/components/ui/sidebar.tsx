import { useState } from "react";
import { Link } from "@inertiajs/react";
import { Home, Users, Settings, ChevronDown, LayoutDashboard, GraduationCap, Briefcase } from "lucide-react"; // Icons
import { Button } from "@/components/ui/button";

const Sidebar = () => {
  const { auth } = usePage().props as { auth: { user: any } };

  const hasRole = (role: string) => auth?.user?.roles?.includes(role);

  return (
    <div className="sidebar"> {/* Reduced padding */}
      
      {/* Navigation Menu */}
      <nav className="flex-1">
        {hasRole('SuperAdmin') && (
          <Link href="/admin/dashboard">Admin Dashboard</Link>>
        )}        
        {hasRole('SchoolAdmin') && (
          <Link href="/admin/dashbaord">School Admin Dashboard</Link>
        )}
        {hasRole('ExamOffice') && (
          <Link href="/examoffice/dashboard">Exam Office Dashboard</Link>
        )}
        {hasRole('Lecturer') && (
          <Link href="/lecturer/dashboard">Lecturer Dashboard</Link>
        )}
        {hasRole('Student') && (
          <Link href="/student/dashboard">Student Dashboard</Link>
        )}
        <ul>
        </ul>
      </nav>

    </div>
  );
};

export default Sidebar;
