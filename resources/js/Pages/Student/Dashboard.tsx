import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

const Dashboard = ({ stats }: { stats: any }) => {
  return (
    <AuthenticatedLayout>
      <Head title="Student Dashboard" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Student Dashboard</h1>
        <p>Welcome to the Student Dashboard!</p>
        <div>
          <h2 className="text-lg font-semibold">Statistics</h2>
          <ul>
            <li>Enrolled Units: {stats.enrolledUnits}</li>
            <li>Upcoming Exams: {stats.upcomingExams.length}</li>
          </ul>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default Dashboard;
