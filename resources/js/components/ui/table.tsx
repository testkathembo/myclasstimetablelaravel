import React from 'react';

export const Table = ({ children }: { children: React.ReactNode }) => (
    <div className="overflow-x-auto">
        <table className="min-w-full border border-gray-200 bg-white rounded-md shadow-sm">
            {children}
        </table>
    </div>
);

export const TableHeader = ({ children }: { children: React.ReactNode }) => (
    <thead className="bg-gray-100">
        {children}
    </thead>
);

export const TableRow = ({ children }: { children: React.ReactNode }) => (
    <tr className="hover:bg-gray-50">{children}</tr>
);

export const TableHead = ({ children }: { children: React.ReactNode }) => (
    <th className="px-4 py-2 text-left text-sm font-semibold text-gray-600 border-b">{children}</th>
);

export const TableBody = ({ children }: { children: React.ReactNode }) => (
    <tbody>{children}</tbody>
);

export const TableCell = ({ children }: { children: React.ReactNode }) => (
    <td className="px-4 py-2 text-sm text-gray-700 border-b">{children}</td>
);
