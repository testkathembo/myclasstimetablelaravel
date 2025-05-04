import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react'; // Use `router` instead of `Inertia`

export default function Index({ classTimetables, semesters, units, lecturers }) {
    const [showModal, setShowModal] = useState(false);
    const [modalType, setModalType] = useState(''); // 'create', 'edit', or 'view'
    const [formData, setFormData] = useState({
        id: null,
        semester_id: '',
        unit_id: '',
        day: '',
        date: '',
        start_time: '',
        end_time: '',
        venue: '',
        location: '',
        status: 'physical',
        lecturer_id: '',
    });

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData({ ...formData, [name]: value });
    };

    const openModal = (type, timetable = null) => {
        setModalType(type);
        if (timetable) {
            setFormData({
                id: timetable.id,
                semester_id: timetable.semester_id || '',
                unit_id: timetable.unit_id || '',
                day: timetable.day || '',
                date: timetable.date || '',
                start_time: timetable.start_time || '',
                end_time: timetable.end_time || '',
                venue: timetable.venue || '',
                location: timetable.location || '',
                status: timetable.status || 'physical',
                lecturer_id: timetable.lecturer_id || '',
            });
        } else {
            setFormData({
                id: null,
                semester_id: '',
                unit_id: '',
                day: '',
                date: '',
                start_time: '',
                end_time: '',
                venue: '',
                location: '',
                status: 'physical',
                lecturer_id: '',
            });
        }
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setFormData({
            id: null,
            semester_id: '',
            unit_id: '',
            day: '',
            date: '',
            start_time: '',
            end_time: '',
            venue: '',
            location: '',
            status: 'physical',
            lecturer_id: '',
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (modalType === 'create') {
            router.post('/classtimetables', formData, {
                onSuccess: () => closeModal(),
            });
        } else if (modalType === 'edit') {
            router.put(`/classtimetables/${formData.id}`, formData, {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this timetable?')) {
            router.delete(`/classtimetables/${id}`);
        }
    };

    return (
        <div>
            <h1>Class Timetables</h1>
            <button onClick={() => openModal('create')} className="btn btn-primary">Create New</button>
            <table>
                <thead>
                    <tr>
                        <th>Semester</th>
                        <th>Unit</th>
                        <th>Day</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {classTimetables.data.map((timetable) => (
                        <tr key={timetable.id}>
                            <td>{timetable.semester?.name}</td>
                            <td>{timetable.unit?.name}</td>
                            <td>{timetable.day}</td>
                            <td>{timetable.date}</td>
                            <td>{timetable.start_time} - {timetable.end_time}</td>
                            <td>{timetable.venue}</td>
                            <td>
                                <button onClick={() => openModal('view', timetable)} className="btn btn-sm btn-info">View</button>
                                <button onClick={() => openModal('edit', timetable)} className="btn btn-sm btn-warning">Edit</button>
                                <button onClick={() => handleDelete(timetable.id)} className="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {showModal && (
                <div className="modal">
                    <div className="modal-content">
                        <h2>{modalType === 'create' ? 'Create Timetable' : modalType === 'edit' ? 'Edit Timetable' : 'View Timetable'}</h2>
                        {modalType !== 'view' ? (
                            <form onSubmit={handleSubmit}>
                                <div>
                                    <label>Semester</label>
                                    <select name="semester_id" value={formData.semester_id} onChange={handleInputChange}>
                                        <option value="">Select Semester</option>
                                        {semesters.map((semester) => (
                                            <option key={semester.id} value={semester.id}>{semester.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label>Unit</label>
                                    <select name="unit_id" value={formData.unit_id} onChange={handleInputChange}>
                                        <option value="">Select Unit</option>
                                        {units.map((unit) => (
                                            <option key={unit.id} value={unit.id}>{unit.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label>Day</label>
                                    <input type="text" name="day" value={formData.day} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>Date</label>
                                    <input type="date" name="date" value={formData.date} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>Start Time</label>
                                    <input type="time" name="start_time" value={formData.start_time} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>End Time</label>
                                    <input type="time" name="end_time" value={formData.end_time} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>Venue</label>
                                    <input type="text" name="venue" value={formData.venue} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>Location</label>
                                    <input type="text" name="location" value={formData.location} onChange={handleInputChange} />
                                </div>
                                <div>
                                    <label>Status</label>
                                    <select name="status" value={formData.status} onChange={handleInputChange}>
                                        <option value="physical">Physical</option>
                                        <option value="online">Online</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Lecturer</label>
                                    <select name="lecturer_id" value={formData.lecturer_id} onChange={handleInputChange}>
                                        <option value="">Select Lecturer</option>
                                        {lecturers.map((lecturer) => (
                                            <option key={lecturer.id} value={lecturer.id}>{lecturer.first_name} {lecturer.last_name}</option>
                                        ))}
                                    </select>
                                </div>
                                <button type="submit" className="btn btn-success">Save</button>
                                <button type="button" onClick={closeModal} className="btn btn-secondary">Cancel</button>
                            </form>
                        ) : (
                            <div>
                                <p><strong>Semester:</strong> {formData.semester_id}</p>
                                <p><strong>Unit:</strong> {formData.unit_id}</p>
                                <p><strong>Day:</strong> {formData.day}</p>
                                <p><strong>Date:</strong> {formData.date}</p>
                                <p><strong>Time:</strong> {formData.start_time} - {formData.end_time}</p>
                                <p><strong>Venue:</strong> {formData.venue}</p>
                                <p><strong>Location:</strong> {formData.location}</p>
                                <p><strong>Status:</strong> {formData.status}</p>
                                <p><strong>Lecturer:</strong> {formData.lecturer_id}</p>
                                <button onClick={closeModal} className="btn btn-secondary">Close</button>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
