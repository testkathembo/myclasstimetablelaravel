import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface School {
    id: number;
    name: string;
    code: string;
}

interface Program {
    id: number;
    name: string;
    code: string;
    school_id: number;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSchools {
    data: School[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

interface PaginatedPrograms {
    data: Program[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

// Mock programs data - replace with actual data from your backend
const mockPrograms: Program[] = [
    { id: 1, name: 'Bachelor of Science in Information Technology', code: 'BSIT', school_id: 1 },
    { id: 2, name: 'Bachelor of Business Information Technology', code: 'BBIT', school_id: 1 },
    { id: 3, name: 'Bachelor of Science in Computer Science', code: 'BSCS', school_id: 1 },
    { id: 4, name: 'Bachelor of Arts in Communication', code: 'BACOM', school_id: 5 },
    { id: 5, name: 'Bachelor of Arts in English Literature', code: 'BAENG', school_id: 5 },
    { id: 6, name: 'Bachelor of Tourism Management', code: 'BTM', school_id: 4 },
    { id: 7, name: 'Bachelor of Hotel Management', code: 'BHM', school_id: 4 },
    { id: 8, name: 'Bachelor of Business Administration', code: 'BBA', school_id: 2 },
    { id: 9, name: 'Master of Business Administration', code: 'MBA', school_id: 2 },
    { id: 10, name: 'Bachelor of Laws', code: 'LLB', school_id: 3 },
    { id: 11, name: 'Master of Laws', code: 'LLM', school_id: 3 },
];

// Program-specific pages
const programPages = [
    { id: 'classrooms', name: 'Classrooms', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { id: 'class-timetables', name: 'Class Timetables', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'exam-rooms', name: 'Exam Rooms', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { id: 'exam-time-slots', name: 'Exam Time Slots', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'exam-timetable', name: 'Exam Timetable', icon: 'M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0V6a2 2 0 012-2h4a2 2 0 012 2v1m-6 0h8m-9 2v8a2 2 0 002 2h8a2 2 0 002-2v-8M5 9h14l-1 12H6L5 9z' },
    { id: 'manage-enrollments', name: 'Manage Enrollments', icon: 'M12 4.354a4 4 0 110 6.292 4 4 0 010-6.292zM15 8a3 3 0 11-6 0 3 3 0 016 0z' },
];

const Schools = () => {
    const { schools, perPage, search } = usePage().props as { schools: PaginatedSchools; perPage: number; search: string };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentSchool, setCurrentSchool] = useState<School | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);
    
    // Navigation state
    const [currentView, setCurrentView] = useState<'schools' | 'programs' | 'program-pages'>('schools');
    const [selectedSchool, setSelectedSchool] = useState<School | null>(null);
    const [selectedProgram, setSelectedProgram] = useState<Program | null>(null);
    const [filteredPrograms, setFilteredPrograms] = useState<Program[]>([]);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', school: School | null = null) => {
        setModalType(type);
        setCurrentSchool(
            type === 'create'
                ? { id: 0, name: '', code: '' }
                : school
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentSchool(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            if (currentSchool) {
                router.post('/schools', currentSchool, {
                    onSuccess: () => {
                        alert('School created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating school:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentSchool) {
            router.put(`/schools/${currentSchool.id}`, currentSchool, {
                onSuccess: () => {
                    alert('School updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating school:', errors);
                },
            });
        } else if (modalType === 'delete' && currentSchool) {
            router.delete(`/schools/${currentSchool.id}`, {
                onSuccess: () => {
                    alert('School deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting school:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/schools', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/schools', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    // Navigation functions
    const handleSchoolCodeClick = (school: School) => {
        setSelectedSchool(school);
        const programs = mockPrograms.filter(program => program.school_id === school.id);
        setFilteredPrograms(programs);
        setCurrentView('programs');
    };

    const handleProgramClick = (program: Program) => {
        setSelectedProgram(program);
        setCurrentView('program-pages');
    };

    const handleProgramPageClick = (pageId: string) => {
        // Navigate to the specific program page
        // You can implement routing logic here based on your Laravel routes
        const routeMap: { [key: string]: string } = {
            'classrooms': '/classrooms',
            'class-timetables': '/class-timetables',
            'exam-rooms': '/exam-rooms',
            'exam-time-slots': '/exam-time-slots',
            'exam-timetable': '/exam-timetable',
            'manage-enrollments': '/manage-enrollments',
        };

        const route = routeMap[pageId];
        if (route) {
            router.get(`${route}?school=${selectedSchool?.id}&program=${selectedProgram?.id}`);
        }
    };

    const handleBackToSchools = () => {
        setCurrentView('schools');
        setSelectedSchool(null);
        setSelectedProgram(null);
        setFilteredPrograms([]);
    };

    const handleBackToPrograms = () => {
        setCurrentView('programs');
        setSelectedProgram(null);
    };

    // Breadcrumb component
    const Breadcrumbs = () => (
        <div className="mb-6 flex items-center justify-between">
            <div className="flex items-center space-x-2 text-sm">
                <button
                    onClick={handleBackToSchools}
                    className="text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200"
                >
                    Schools
                </button>
                {currentView !== 'schools' && (
                    <>
                        <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                        <button
                            onClick={handleBackToPrograms}
                            className="text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200"
                        >
                            {selectedSchool?.name} Programs
                        </button>
                    </>
                )}
                {currentView === 'program-pages' && (
                    <>
                        <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                        <span className="text-gray-600 font-medium">{selectedProgram?.name}</span>
                    </>
                )}
            </div>
            
            {/* Back Button */}
            {currentView !== 'schools' && (
                <button
                    onClick={currentView === 'programs' ? handleBackToSchools : handleBackToPrograms}
                    className="group flex items-center space-x-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl font-semibold transition-all duration-300 hover:from-gray-600 hover:to-gray-700 hover:shadow-lg hover:shadow-gray-500/30 hover:-translate-y-1 transform"
                >
                    <svg className="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>
                        {currentView === 'programs' ? 'Back to Schools' : `Back to ${selectedSchool?.name} Programs`}
                    </span>
                </button>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Schools" />
            
            {/* Main Container with Enhanced Background */}
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    {/* Breadcrumbs */}
                    <Breadcrumbs />
                    
                    {/* Page Header */}
                    <div className="mb-8">
                        <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                            {currentView === 'schools' && 'Schools Management'}
                            {currentView === 'programs' && `${selectedSchool?.name} Programs`}
                            {currentView === 'program-pages' && `${selectedProgram?.name} Management`}
                        </h1>
                        <p className="text-gray-600 text-lg">
                            {currentView === 'schools' && 'Manage and organize educational institutions'}
                            {currentView === 'programs' && `Programs available in ${selectedSchool?.name}`}
                            {currentView === 'program-pages' && `Manage ${selectedProgram?.name} resources and data`}
                        </p>
                    </div>

                    {/* Schools View */}
                    {currentView === 'schools' && (
                        <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 overflow-hidden">
                            
                            {/* Enhanced Controls Section */}
                            <div className="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100">
                                <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                                    
                                    {/* Add Button with Icon */}
                                    <button
                                        onClick={() => handleOpenModal('create')}
                                        className="group relative bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 hover:from-green-600 hover:to-emerald-600 hover:shadow-lg hover:shadow-green-500/30 hover:-translate-y-1 transform"
                                    >
                                        <span className="flex items-center gap-2">
                                            <svg className="w-5 h-5 transition-transform duration-300 group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add School
                                        </span>
                                    </button>

                                    {/* Enhanced Search Form */}
                                    <form onSubmit={handleSearch} className="flex items-center space-x-3">
                                        <div className="relative">
                                            <input
                                                type="text"
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                placeholder="Search schools..."
                                                className="w-80 pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm transition-all duration-300 hover:shadow-md"
                                            />
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <button
                                            type="submit"
                                            className="bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 hover:from-blue-600 hover:to-indigo-600 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-1 transform"
                                        >
                                            Search
                                        </button>
                                    </form>

                                    {/* Enhanced Items Per Page Selector */}
                                    <div className="flex items-center space-x-3">
                                        <label htmlFor="perPage" className="text-sm font-semibold text-gray-700">
                                            Items per page:
                                        </label>
                                        <select
                                            id="perPage"
                                            value={itemsPerPage}
                                            onChange={handlePerPageChange}
                                            className="border border-gray-200 rounded-xl px-4 py-2 bg-white/90 backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 hover:shadow-md"
                                        >
                                            <option value={5}>5</option>
                                            <option value={10}>10</option>
                                            <option value={15}>15</option>
                                            <option value={20}>20</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Enhanced Table Section */}
                            <div className="overflow-hidden">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full">
                                        <thead>
                                            <tr className="bg-gradient-to-r from-gray-50 to-blue-50 border-b border-gray-200">
                                                <th className="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <div className="flex items-center space-x-1">
                                                        <span>ID</span>
                                                        <div className="w-1 h-1 bg-blue-500 rounded-full"></div>
                                                    </div>
                                                </th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <div className="flex items-center space-x-1">
                                                        <span>Name</span>
                                                        <div className="w-1 h-1 bg-blue-500 rounded-full"></div>
                                                    </div>
                                                </th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <div className="flex items-center space-x-1">
                                                        <span>Code</span>
                                                        <div className="w-1 h-1 bg-blue-500 rounded-full"></div>
                                                    </div>
                                                </th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <div className="flex items-center space-x-1">
                                                        <span>Actions</span>
                                                        <div className="w-1 h-1 bg-blue-500 rounded-full"></div>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-100">
                                            {schools.data.map((school) => (
                                                <tr key={school.id} className="group hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-indigo-50/50 transition-all duration-300">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <div className="flex items-center space-x-3">
                                                            <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg">
                                                                {school.id}
                                                            </div>
                                                            <span className="text-gray-500 font-mono">#{school.id}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-semibold text-gray-900">{school.name}</div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <button
                                                            onClick={() => handleSchoolCodeClick(school)}
                                                            className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 border border-purple-200 shadow-sm hover:from-purple-200 hover:to-pink-200 hover:shadow-md transition-all duration-300 cursor-pointer"
                                                        >
                                                            {school.code}
                                                            <svg className="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div className="flex items-center space-x-3">
                                                            <button
                                                                onClick={() => handleOpenModal('edit', school)}
                                                                className="group relative bg-gradient-to-r from-amber-400 to-orange-400 text-white px-4 py-2 rounded-lg font-semibold transition-all duration-300 hover:from-amber-500 hover:to-orange-500 hover:shadow-lg hover:shadow-amber-500/30 hover:-translate-y-1 transform"
                                                            >
                                                                <span className="flex items-center gap-1">
                                                                    <svg className="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                    Edit
                                                                </span>
                                                            </button>
                                                            <button
                                                                onClick={() => handleOpenModal('delete', school)}
                                                                className="group relative bg-gradient-to-r from-red-500 to-rose-500 text-white px-4 py-2 rounded-lg font-semibold transition-all duration-300 hover:from-red-600 hover:to-rose-600 hover:shadow-lg hover:shadow-red-500/30 hover:-translate-y-1 transform"
                                                            >
                                                                <span className="flex items-center gap-1">
                                                                    <svg className="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                    Delete
                                                                </span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Enhanced Footer Section */}
                            <div className="px-6 py-4 bg-gradient-to-r from-gray-50 to-blue-50 border-t border-gray-200">
                                <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
                                    <div className="text-sm text-gray-600 flex items-center space-x-2">
                                        <div className="w-2 h-2 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full animate-pulse"></div>
                                        <span className="font-medium">
                                            Showing {schools.data.length} of {schools.total} schools
                                        </span>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        {schools.links.map((link, index) => (
                                            <button
                                                key={index}
                                                onClick={() => handlePageChange(link.url)}
                                                className={`px-4 py-2 rounded-xl font-semibold transition-all duration-300 ${
                                                    link.active
                                                        ? 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white shadow-lg shadow-blue-500/30 transform scale-105'
                                                        : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200 hover:border-gray-300 hover:shadow-md hover:-translate-y-1 transform'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Programs View */}
                    {currentView === 'programs' && (
                        <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 overflow-hidden">
                            {/* Enhanced Header with Back Button */}
                            <div className="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100">
                                <div className="flex items-center justify-between mb-4">
                                    <div className="flex items-center space-x-4">
                                        <button
                                            onClick={handleBackToSchools}
                                            className="group flex items-center space-x-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl font-semibold transition-all duration-300 hover:from-gray-600 hover:to-gray-700 hover:shadow-lg hover:shadow-gray-500/30 hover:-translate-y-1 transform"
                                        >
                                            <svg className="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                            </svg>
                                            <span>Back to Schools</span>
                                        </button>
                                        <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <span className="text-white font-bold text-lg">{selectedSchool?.code}</span>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <h3 className="text-lg font-bold text-gray-900">{selectedSchool?.name}</h3>
                                        <p className="text-sm text-gray-600">Available Programs</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="p-8">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {filteredPrograms.map((program) => (
                                        <div
                                            key={program.id}
                                            onClick={() => handleProgramClick(program)}
                                            className="group bg-gradient-to-br from-white to-blue-50 rounded-xl p-6 border border-blue-100 hover:border-blue-300 shadow-lg hover:shadow-xl transition-all duration-300 cursor-pointer hover:-translate-y-2"
                                        >
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                                    <span className="text-white font-bold text-lg">{program.code[0]}</span>
                                                </div>
                                                <svg className="w-6 h-6 text-blue-500 group-hover:text-blue-600 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                            <h3 className="font-bold text-gray-900 text-lg mb-2 group-hover:text-blue-700 transition-colors duration-300">
                                                {program.name}
                                            </h3>
                                            <div className="flex items-center justify-between">
                                                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 border border-purple-200">
                                                    {program.code}
                                                </span>
                                                <span className="text-sm text-gray-500 font-medium">
                                                    Click to explore
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {filteredPrograms.length === 0 && (
                                    <div className="text-center py-12">
                                        <div className="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                            <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <h3 className="text-xl font-semibold text-gray-900 mb-2">No Programs Found</h3>
                                        <p className="text-gray-600">This school doesn't have any programs configured yet.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Program Pages View */}
                    {currentView === 'program-pages' && (
                        <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 overflow-hidden">
                            {/* Enhanced Header with Back Button */}
                            <div className="p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-indigo-100">
                                <div className="flex items-center justify-between mb-4">
                                    <div className="flex items-center space-x-4">
                                        <button
                                            onClick={handleBackToPrograms}
                                            className="group flex items-center space-x-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl font-semibold transition-all duration-300 hover:from-gray-600 hover:to-gray-700 hover:shadow-lg hover:shadow-gray-500/30 hover:-translate-y-1 transform"
                                        >
                                            <svg className="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                            </svg>
                                            <span>Back to {selectedSchool?.name} Programs</span>
                                        </button>
                                        <div className="flex items-center space-x-3">
                                            <div className="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                                <span className="text-white font-bold text-lg">{selectedProgram?.code?.[0]}</span>
                                            </div>
                                            <div>
                                                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 border border-indigo-200">
                                                    {selectedProgram?.code}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <h3 className="text-lg font-bold text-gray-900">{selectedProgram?.name}</h3>
                                        <p className="text-sm text-gray-600">Management Pages</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="p-8">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {programPages.map((page) => (
                                        <div
                                            key={page.id}
                                            onClick={() => handleProgramPageClick(page.id)}
                                            className="group bg-gradient-to-br from-white to-indigo-50 rounded-xl p-6 border border-indigo-100 hover:border-indigo-300 shadow-lg hover:shadow-xl transition-all duration-300 cursor-pointer hover:-translate-y-2"
                                        >
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                                    <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={page.icon} />
                                                    </svg>
                                                </div>
                                                <svg className="w-6 h-6 text-indigo-500 group-hover:text-indigo-600 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </div>
                                            <h3 className="font-bold text-gray-900 text-lg mb-2 group-hover:text-indigo-700 transition-colors duration-300">
                                                {page.name}
                                            </h3>
                                            <p className="text-sm text-gray-600 mb-4">
                                                Manage {page.name.toLowerCase()} for {selectedProgram?.code}
                                            </p>
                                            <div className="flex items-center justify-between">
                                                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 border border-indigo-200">
                                                    {selectedProgram?.code}
                                                </span>
                                                <span className="text-sm text-gray-500 font-medium">
                                                    Access now
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Enhanced Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl border border-white/20 max-w-lg w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100">
                        
                        {/* Modal Header */}
                        <div className="p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <h2 className="text-2xl font-bold text-gray-900 flex items-center space-x-3">
                                {modalType === 'create' && (
                                    <>
                                        <div className="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full flex items-center justify-center shadow-lg">
                                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                        </div>
                                        <span>Add New School</span>
                                    </>
                                )}
                                {modalType === 'edit' && (
                                    <>
                                        <div className="w-10 h-10 bg-gradient-to-r from-amber-500 to-orange-500 rounded-full flex items-center justify-center shadow-lg">
                                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </div>
                                        <span>Edit School</span>
                                    </>
                                )}
                                {modalType === 'delete' && (
                                    <>
                                        <div className="w-10 h-10 bg-gradient-to-r from-red-500 to-rose-500 rounded-full flex items-center justify-center shadow-lg">
                                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </div>
                                        <span>Delete School</span>
                                    </>
                                )}
                            </h2>
                        </div>
                        
                        {/* Modal Content */}
                        <div className="p-6">
                            {modalType !== 'delete' ? (
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-3">
                                            School Name
                                        </label>
                                        <input
                                            type="text"
                                            value={currentSchool?.name || ''}
                                            onChange={(e) =>
                                                setCurrentSchool((prev) => ({ ...prev!, name: e.target.value }))
                                            }
                                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 focus:bg-white transition-all duration-300"
                                            placeholder="Enter school name"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-3">
                                            School Code
                                        </label>
                                        <input
                                            type="text"
                                            value={currentSchool?.code || ''}
                                            onChange={(e) =>
                                                setCurrentSchool((prev) => ({ ...prev!, code: e.target.value }))
                                            }
                                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 focus:bg-white transition-all duration-300"
                                            placeholder="Enter school code"
                                            required
                                        />
                                    </div>
                                    <div className="flex space-x-4 pt-6">
                                        <button
                                            type="submit"
                                            className="flex-1 bg-gradient-to-r from-blue-500 to-indigo-500 text-white py-3 px-6 rounded-xl font-bold transition-all duration-300 hover:from-blue-600 hover:to-indigo-600 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-1 transform"
                                        >
                                            {modalType === 'create' ? 'Create School' : 'Update School'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="flex-1 bg-gray-100 text-gray-700 py-3 px-6 rounded-xl font-bold transition-all duration-300 hover:bg-gray-200 hover:shadow-md hover:-translate-y-1 transform"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-center">
                                    <div className="w-20 h-20 bg-gradient-to-r from-red-100 to-rose-100 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-red-200">
                                        <svg className="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-3">Confirm Deletion</h3>
                                    <p className="text-gray-600 mb-8 text-lg">
                                        Are you sure you want to delete "<span className="font-bold text-gray-900">{currentSchool?.name}</span>"? 
                                        <br />
                                        <span className="text-sm text-red-600">This action cannot be undone.</span>
                                    </p>
                                    <div className="flex space-x-4">
                                        <button
                                            onClick={handleSubmit}
                                            className="flex-1 bg-gradient-to-r from-red-500 to-rose-500 text-white py-3 px-6 rounded-xl font-bold transition-all duration-300 hover:from-red-600 hover:to-rose-600 hover:shadow-lg hover:shadow-red-500/30 hover:-translate-y-1 transform"
                                        >
                                            Delete School
                                        </button>
                                        <button
                                            onClick={handleCloseModal}
                                            className="flex-1 bg-gray-100 text-gray-700 py-3 px-6 rounded-xl font-bold transition-all duration-300 hover:bg-gray-200 hover:shadow-md hover:-translate-y-1 transform"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default Schools;