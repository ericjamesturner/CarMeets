import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../services/api';

const EventDetail = () => {
    const { id } = useParams();
    const [event, setEvent] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchEvent();
    }, [id]);

    const fetchEvent = async () => {
        try {
            setLoading(true);
            const response = await api.get(`/events/${id}`);
            setEvent(response.data);
        } catch (error) {
            console.error('Error fetching event:', error);
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getDuration = (start, end) => {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const hours = Math.round((endDate - startDate) / (1000 * 60 * 60));
        return `${hours} hour${hours !== 1 ? 's' : ''}`;
    };

    if (loading) {
        return (
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="text-center text-gray-600 dark:text-gray-400">Loading event details...</div>
            </div>
        );
    }

    if (!event) {
        return (
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="text-center">
                    <p className="text-xl text-gray-500 dark:text-gray-400">Event not found.</p>
                    <Link to="/events" className="mt-4 inline-block text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300">
                        Back to events
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <Link to="/events" className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 mb-6 inline-block">
                ← Back to all events
            </Link>

            <div className="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
                {event.image && (
                    <img
                        src={event.image.startsWith('http') ? event.image : `/storage/${event.image}`}
                        alt={event.name}
                        className="w-full"
                    />
                )}
                
                <div className="px-6 py-8">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-4">{event.name}</h1>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Event Details</h3>
                            <dl className="space-y-3">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Start</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {formatDate(event.start_time)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">End</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {formatDate(event.end_time)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {getDuration(event.start_time, event.end_time)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Cost</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {event.cost > 0 ? (
                                            <span className="text-green-600 dark:text-green-400 font-semibold">
                                                ${parseFloat(event.cost).toFixed(2)}
                                            </span>
                                        ) : (
                                            <span className="text-green-600 dark:text-green-400 font-semibold">FREE</span>
                                        )}
                                    </dd>
                                </div>
                                {!event.unlimited_spots && event.spots && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Available Spots</dt>
                                        <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{event.spots}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                        
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Location</h3>
                            <address className="not-italic text-sm text-gray-900 dark:text-gray-100 space-y-1">
                                <p>{event.address}</p>
                                <p>{event.city}, {event.state} {event.zip}</p>
                            </address>
                            {event.latitude && event.longitude && (
                                <a
                                    href={`https://maps.google.com/?q=${event.latitude},${event.longitude}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-3 inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300"
                                >
                                    View on Google Maps →
                                </a>
                            )}
                        </div>
                    </div>
                    
                    {event.description && (
                        <div className="mt-8">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Description</h3>
                            <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                {event.description}
                            </div>
                        </div>
                    )}
                    
                    <div className="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            <strong>Hosted by:</strong> {event.user.name}
                        </p>
                        {event.imported_from === 'parkupfront' && event.external_id && (
                            <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                <strong>ParkUpFront ID:</strong> {event.external_id}
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default EventDetail;