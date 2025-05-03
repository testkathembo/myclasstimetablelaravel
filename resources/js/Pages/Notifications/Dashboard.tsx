import React from 'react';
import { Head } from '@inertiajs/react';

export default function Dashboard({ upcomingExams, recentNotifications, can }) {
    return (
        <div>
            <Head title="Notification Dashboard" />
            <h1>Notification Dashboard</h1>

            <section>
                <h2>Upcoming Exams</h2>
                {upcomingExams.length > 0 ? (
                    <ul>
                        {upcomingExams.map((exam) => (
                            <li key={exam.id}>
                                {exam.date} - {exam.unit.name} ({exam.start_time} - {exam.end_time})
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p>No upcoming exams.</p>
                )}
            </section>

            <section>
                <h2>Recent Notifications</h2>
                {recentNotifications.length > 0 ? (
                    <ul>
                        {recentNotifications.map((log) => (
                            <li key={log.id}>
                                {log.created_at} - {log.notification_type} ({log.success ? 'Success' : 'Failed'})
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p>No recent notifications.</p>
                )}
            </section>
        </div>
    );
}
