import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

const DownloadTimetable = () => {
  const [format, setFormat] = useState<string>("pdf"); // Default format is PDF

  const handleDownload = () => {
    if (!format) {
      alert("Please select a format to download.");
      return;
    }

    router.get("/download-timetable", { format }, {
      onError: () => alert("Failed to download the timetable. Please try again."),
    });
  };

  return (
    <div className="p-4 bg-white rounded-lg shadow-md">
      <h2 className="text-xl font-semibold mb-4">Download Exam Timetable</h2>
      <div className="flex items-center space-x-4">
        <Select onValueChange={(value) => setFormat(value)} defaultValue="pdf">
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Select Format" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="pdf">PDF</SelectItem>
            <SelectItem value="excel">Excel</SelectItem>
            <SelectItem value="word">Word</SelectItem>
            <SelectItem value="csv">CSV</SelectItem>
          </SelectContent>
        </Select>
        <Button onClick={handleDownload} className="bg-blue-500 hover:bg-blue-600 text-white">
          Download
        </Button>
      </div>
    </div>
  );
};

export default DownloadTimetable;
