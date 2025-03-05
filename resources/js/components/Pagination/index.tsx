import React from 'react';

export const Pagination = ({ children, className }: { children: React.ReactNode; className?: string }) => {
    return <nav className={`flex justify-center mt-4 ${className || ''}`}>{children}</nav>;
};

export const PaginationContent = ({ children }: { children: React.ReactNode }) => {
    return <ul className="flex space-x-2">{children}</ul>;
};

export const PaginationItem = ({ children }: { children: React.ReactNode }) => {
    return <li className="px-1">{children}</li>;
};

export const PaginationLink = ({ isActive, onClick, disabled, children }: { isActive: boolean; onClick: () => void; disabled?: boolean; children: React.ReactNode }) => {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className={`px-4 py-2 rounded-md transition ${
                isActive ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
        >
            {children}
        </button>
    );
};

export const PaginationPrevious = ({ onClick, disabled }: { onClick: () => void; disabled: boolean }) => {
    return (
        <PaginationLink onClick={onClick} disabled={disabled} isActive={false}>
            Previous
        </PaginationLink>
    );
};

export const PaginationNext = ({ onClick, disabled }: { onClick: () => void; disabled: boolean }) => {
    return (
        <PaginationLink onClick={onClick} disabled={disabled} isActive={false}>
            Next
        </PaginationLink>
    );
};

export const PaginationEllipsis = () => {
    return <span className="px-4 py-2">...</span>;
};
