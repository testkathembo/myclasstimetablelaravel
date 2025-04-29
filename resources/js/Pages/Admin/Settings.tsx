import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

const Settings = () => {
  return (
    <AuthenticatedLayout>
      <Head title="Settings" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Settings</h1>
        <p>Manage application settings here.</p>
        <div className="mt-4">
          <h2 className="text-lg font-semibold">General Settings</h2>
          <p className="text-sm text-gray-600">Configure general application settings.</p>
          {/* Add your settings form or components here */}
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default Settings;
