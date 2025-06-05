import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Dashboard() {
    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
                {/* Header Section */}
                <div className="bg-white shadow-sm border-b border-slate-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
                                <p className="text-slate-600 mt-1">Welcome to the admin dashboard!</p>
                            </div>
                            <div className="flex items-center space-x-4">
                                <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg">
                                    <span className="font-semibold">admin001</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Quick Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Total Users</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">1,247</p>
                                </div>
                                <div className="bg-blue-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                                    </svg>
                                </div>
                            </div>
                            <div className="mt-4">
                                <span className="text-green-600 text-sm font-medium">+12.5%</span>
                                <span className="text-slate-500 text-sm ml-1">from last month</span>
                            </div>
                        </div>

                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Active Enrollments</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">856</p>
                                </div>
                                <div className="bg-emerald-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                            </div>
                            <div className="mt-4">
                                <span className="text-green-600 text-sm font-medium">+8.2%</span>
                                <span className="text-slate-500 text-sm ml-1">from last week</span>
                            </div>
                        </div>

                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Active Classes</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">42</p>
                                </div>
                                <div className="bg-purple-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                            </div>
                            <div className="mt-4">
                                <span className="text-green-600 text-sm font-medium">+5.7%</span>
                                <span className="text-slate-500 text-sm ml-1">from last month</span>
                            </div>
                        </div>

                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Exam Sessions</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">18</p>
                                </div>
                                <div className="bg-amber-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                </div>
                            </div>
                            <div className="mt-4">
                                <span className="text-red-600 text-sm font-medium">-2.1%</span>
                                <span className="text-slate-500 text-sm ml-1">from last week</span>
                            </div>
                        </div>
                    </div>

                    {/* Main Content Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Administration Section */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-2xl font-bold text-slate-800">Administration</h2>
                                    <div className="bg-slate-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <Link href="/users" className="group block p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-300 border border-blue-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-blue-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Users</h3>
                                                <p className="text-slate-600 text-sm">Manage system users</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/roles" className="group block p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all duration-300 border border-purple-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-purple-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Roles & Permissions</h3>
                                                <p className="text-slate-600 text-sm">Manage access control</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/settings" className="group block p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl hover:from-emerald-100 hover:to-emerald-200 transition-all duration-300 border border-emerald-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-emerald-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Settings</h3>
                                                <p className="text-slate-600 text-sm">System configuration</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/schools" className="group block p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl hover:from-amber-100 hover:to-amber-200 transition-all duration-300 border border-amber-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-amber-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Schools</h3>
                                                <p className="text-slate-600 text-sm">Manage academic schools</p>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>

                            {/* Schools Section */}
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 mt-8">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-2xl font-bold text-slate-800">Schools</h2>
                                    <div className="bg-slate-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <div className="space-y-4">
                                    <Link href="/schools/sces" className="group flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all duration-300 border border-indigo-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-indigo-500 p-2 rounded-lg">
                                                <span className="text-white font-bold text-sm">SCES</span>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">School of Computing & Engineering Sciences</h3>
                                                <p className="text-slate-600 text-sm">Computer Science, Engineering, IT Programs</p>
                                            </div>
                                        </div>
                                        <svg className="w-5 h-5 text-indigo-600 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>

                                    <Link href="/schools/bcom" className="group flex items-center justify-between p-4 bg-gradient-to-r from-teal-50 to-teal-100 rounded-xl hover:from-teal-100 hover:to-teal-200 transition-all duration-300 border border-teal-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-teal-500 p-2 rounded-lg">
                                                <span className="text-white font-bold text-sm">BCOM</span>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Business & Commerce</h3>
                                                <p className="text-slate-600 text-sm">Business Administration, Commerce Programs</p>
                                            </div>
                                        </div>
                                        <svg className="w-5 h-5 text-teal-600 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>

                                    <Link href="/schools/law" className="group flex items-center justify-between p-4 bg-gradient-to-r from-rose-50 to-rose-100 rounded-xl hover:from-rose-100 hover:to-rose-200 transition-all duration-300 border border-rose-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-rose-500 p-2 rounded-lg">
                                                <span className="text-white font-bold text-sm">LAW</span>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">School of Law</h3>
                                                <p className="text-slate-600 text-sm">Legal Studies, Jurisprudence Programs</p>
                                            </div>
                                        </div>
                                        <svg className="w-5 h-5 text-rose-600 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>

                                    <Link href="/schools/humanities" className="group flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl hover:from-orange-100 hover:to-orange-200 transition-all duration-300 border border-orange-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-orange-500 p-2 rounded-lg">
                                                <span className="text-white font-bold text-sm">HUM</span>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Humanities</h3>
                                                <p className="text-slate-600 text-sm">Liberal Arts, Social Sciences Programs</p>
                                            </div>
                                        </div>
                                        <svg className="w-5 h-5 text-orange-600 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions Sidebar */}
                        <div className="space-y-6">
                            {/* Student Portal */}
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-xl font-bold text-slate-800">Student Portal</h3>
                                    <div className="bg-green-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <Link href="/enroll" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">Enroll in Units</span>
                                    </Link>
                                    <Link href="/my-enrollments" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">My Enrollments</span>
                                    </Link>
                                    <Link href="/my-timetable" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">My Timetable</span>
                                    </Link>
                                    <Link href="/my-exams" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">My Exams</span>
                                    </Link>
                                </div>
                            </div>

                            {/* Lecturer Portal */}
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-xl font-bold text-slate-800">Lecturer Portal</h3>
                                    <div className="bg-purple-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <Link href="/my-classes" className="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors duration-200">
                                        <span className="text-purple-700 font-medium">My Classes</span>
                                    </Link>
                                    <Link href="/my-students" className="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors duration-200">
                                        <span className="text-purple-700 font-medium">My Students</span>
                                    </Link>
                                    <Link href="/my-exam-schedule" className="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors duration-200">
                                        <span className="text-purple-700 font-medium">My Exam Schedule</span>
                                    </Link>
                                </div>
                            </div>

                            {/* System Status */}
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-xl font-bold text-slate-800">System Status</h3>
                                    <div className="bg-green-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <div className="flex items-center space-x-2">
                                            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span className="text-green-700 font-medium">Database</span>
                                        </div>
                                        <span className="text-green-600 text-sm">Online</span>
                                    </div>
                                    <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <div className="flex items-center space-x-2">
                                            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span className="text-green-700 font-medium">API Services</span>
                                        </div>
                                        <span className="text-green-600 text-sm">Healthy</span>
                                    </div>
                                    <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                        <div className="flex items-center space-x-2">
                                            <div className="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                            <span className="text-yellow-700 font-medium">Backup System</span>
                                        </div>
                                        <span className="text-yellow-600 text-sm">Running</span>
                                    </div>
                                    <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <div className="flex items-center space-x-2">
                                            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span className="text-green-700 font-medium">Mail Service</span>
                                        </div>
                                        <span className="text-green-600 text-sm">Active</span>
                                    </div>
                                </div>
                            </div>

                            {/* Recent Activity */}
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-xl font-bold text-slate-800">Recent Activity</h3>
                                    <div className="bg-indigo-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    <div className="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                                        <div className="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                                        <div className="flex-1">
                                            <p className="text-slate-800 text-sm font-medium">New user registered</p>
                                            <p className="text-slate-600 text-xs">Student STU2024001 enrolled</p>
                                            <p className="text-slate-500 text-xs">2 minutes ago</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start space-x-3 p-3 bg-emerald-50 rounded-lg">
                                        <div className="w-2 h-2 bg-emerald-500 rounded-full mt-2"></div>
                                        <div className="flex-1">
                                            <p className="text-slate-800 text-sm font-medium">Timetable updated</p>
                                            <p className="text-slate-600 text-xs">BBIT 3.1 schedule modified</p>
                                            <p className="text-slate-500 text-xs">5 minutes ago</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start space-x-3 p-3 bg-purple-50 rounded-lg">
                                        <div className="w-2 h-2 bg-purple-500 rounded-full mt-2"></div>
                                        <div className="flex-1">
                                            <p className="text-slate-800 text-sm font-medium">Exam scheduled</p>
                                            <p className="text-slate-600 text-xs">Database Systems midterm</p>
                                            <p className="text-slate-500 text-xs">10 minutes ago</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start space-x-3 p-3 bg-amber-50 rounded-lg">
                                        <div className="w-2 h-2 bg-amber-500 rounded-full mt-2"></div>
                                        <div className="flex-1">
                                            <p className="text-slate-800 text-sm font-medium">System maintenance</p>
                                            <p className="text-slate-600 text-xs">Backup completed successfully</p>
                                            <p className="text-slate-500 text-xs">1 hour ago</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-4 pt-4 border-t border-slate-200">
                                    <Link href="/activity-log" className="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors duration-200">
                                        View all activity →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Quick Actions Bar */}
                    <div className="mt-8 bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                        <h3 className="text-xl font-bold text-slate-800 mb-4">Quick Actions</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Link href="/enrollments/bulk" className="flex items-center justify-center p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span className="font-semibold">Bulk Enrollment</span>
                            </Link>
                            
                            <Link href="/timetables/generate" className="flex items-center justify-center p-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl hover:from-emerald-600 hover:to-emerald-700 transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span className="font-semibold">Generate Timetable</span>
                            </Link>
                            
                            <Link href="/exams/schedule" className="flex items-center justify-center p-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:from-purple-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <span className="font-semibold">Schedule Exams</span>
                            </Link>
                            
                            <Link href="/reports" className="flex items-center justify-center p-4 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl hover:from-amber-600 hover:to-amber-700 transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span className="font-semibold">View Reports</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}