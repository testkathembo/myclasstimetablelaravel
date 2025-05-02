import React from "react";

export const Card = ({ children }: { children: React.ReactNode }) => (
  <div className="bg-white border rounded-lg shadow-sm">{children}</div>
);

export const CardHeader = ({ children }: { children: React.ReactNode }) => (
  <div className="p-4 border-b">{children}</div>
);

export const CardTitle = ({ children }: { children: React.ReactNode }) => (
  <h3 className="text-lg font-medium">{children}</h3>
);

export const CardDescription = ({ children }: { children: React.ReactNode }) => (
  <p className="text-sm text-gray-500">{children}</p>
);

export const CardContent = ({ children }: { children: React.ReactNode }) => (
  <div className="p-4">{children}</div>
);

export const CardFooter = ({ children }: { children: React.ReactNode }) => (
  <div className="p-4 border-t">{children}</div>
);
