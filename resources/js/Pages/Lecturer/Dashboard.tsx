import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

const Dashboard = () => {
  return (
    <AuthenticatedLayout>
      <Head title="Lecturer Dashboard" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Lecturer Dashboard</h1>
        <p>Welcome to the Lecturer Dashboard!</p>
      </div>
    </AuthenticatedLayout>
  );
};

export default Dashboard;
