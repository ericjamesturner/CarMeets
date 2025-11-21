import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import DayMap from './DayMap';

const TimelineView = ({ groupedEvents, onEventClick }) => {
    const navigate = useNavigate();
    // Generate hour labels from 6 AM to 11 PM
    const hours = Array.from({ length: 18 }, (_, i) => i + 6);

    // Check if mobile
    const [isMobile, setIsMobile] = React.useState(window.innerWidth < 768);

    // Track which day's map modal is open
    const [mapModalDay, setMapModalDay] = React.useState(null);

    React.useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    // Close modal on escape key
    React.useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape') setMapModalDay(null);
        };
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, []);
    
    // Calculate event position and width on timeline
    const getEventPosition = (event) => {
        const start = new Date(event.start_time);
        const end = new Date(event.end_time);
        let startHour = start.getHours() + start.getMinutes() / 60;
        let endHour = end.getHours() + end.getMinutes() / 60;
        
        // Position from 6 AM (6) to midnight (24)
        const dayStart = 6;
        const dayEnd = 24;
        const dayDuration = dayEnd - dayStart;
        
        // Handle events that start before 6 AM
        if (startHour < dayStart) {
            startHour = dayStart;
        }
        
        // Handle events that end after midnight (next day)
        if (endHour < startHour) {
            endHour = dayEnd;
        }
        
        // Cap at end of day
        if (endHour > dayEnd) {
            endHour = dayEnd;
        }
        
        const left = Math.max(0, ((startHour - dayStart) / dayDuration) * 100);
        const width = Math.min(100 - left, ((endHour - startHour) / dayDuration) * 100);
        
        return { left: `${left}%`, width: `${width}%` };
    };
    
    // Get color for event based on index
    const getEventColor = (index) => {
        const colors = [
            'bg-blue-500 dark:bg-blue-600',
            'bg-green-500 dark:bg-green-600',
            'bg-purple-500 dark:bg-purple-600',
            'bg-pink-500 dark:bg-pink-600',
            'bg-yellow-500 dark:bg-yellow-600',
            'bg-indigo-500 dark:bg-indigo-600',
        ];
        return colors[index % colors.length];
    };
    
    const formatTime = (dateString) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    };

    // Handle map marker click to navigate to event
    const handleMapEventClick = (event) => {
        if (onEventClick) onEventClick();
        navigate(`/events/${event.id}`);
    };

    // Mobile vertical timeline
    if (isMobile) {
        return (
            <div className="space-y-6">
                {groupedEvents.map((day) => (
                    <div key={day.date.toISOString()} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                {day.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                            </h2>
                            <button
                                onClick={() => setMapModalDay(day)}
                                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                Map
                            </button>
                        </div>

                        {/* Vertical timeline for mobile */}
                        <div className="relative">
                            <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-300 dark:bg-gray-600"></div>
                            
                            <div className="space-y-4">
                                {day.events.sort((a, b) => new Date(a.start_time) - new Date(b.start_time)).map((event, index) => (
                                    <Link
                                        key={event.id}
                                        to={`/events/${event.id}`}
                                        onClick={onEventClick}
                                        className="relative flex items-start ml-10 group"
                                    >
                                        {/* Timeline dot */}
                                        <div className={`absolute -left-8 w-4 h-4 rounded-full ${getEventColor(index)} ring-4 ring-white dark:ring-gray-800`}></div>
                                        
                                        {/* Event card */}
                                        <div className="flex-1 bg-gray-50 dark:bg-gray-700 rounded-lg p-3 hover:shadow-md transition-shadow">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <h3 className="font-semibold text-gray-900 dark:text-white text-sm">
                                                        {event.name}
                                                    </h3>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                        {formatTime(event.start_time)} - {formatTime(event.end_time)}
                                                    </p>
                                                </div>
                                                {event.cost > 0 && (
                                                    <span className="text-xs font-semibold text-green-600 dark:text-green-400">
                                                        ${parseFloat(event.cost).toFixed(0)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        );
    }
    
    // Desktop horizontal timeline
    return (
        <div className="space-y-8">
            {groupedEvents.map((day) => (
                <div key={day.date.toISOString()} className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-gray-900 dark:text-white">
                            {day.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                        </h2>
                        <button
                            onClick={() => setMapModalDay(day)}
                            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            View Map
                        </button>
                    </div>

                    {/* Timeline Grid */}
                    <div className="relative overflow-x-auto -mx-4 px-4 md:mx-0 md:px-0">
                        {/* Hour markers */}
                        <div className="flex relative h-2 mb-2 min-w-[800px]">
                            {hours.map((hour) => (
                                <div key={hour} className="flex-1 relative">
                                    <div className="absolute left-0 -top-6 text-xs text-gray-500 dark:text-gray-400">
                                        {hour === 12 ? '12pm' : hour < 12 ? `${hour}am` : hour > 12 ? `${hour - 12}pm` : ''}
                                    </div>
                                </div>
                            ))}
                        </div>
                        
                        {/* Grid lines */}
                        <div className="flex relative h-full min-w-[800px]">
                            {hours.map((hour) => (
                                <div key={hour} className="flex-1 border-l border-gray-200 dark:border-gray-700 first:border-l-0"></div>
                            ))}
                            <div className="border-r border-gray-200 dark:border-gray-700"></div>
                        </div>
                        
                        {/* Events */}
                        <div className="relative mt-4 space-y-2 min-w-[800px]">
                            {day.events.map((event, index) => {
                                const position = getEventPosition(event);
                                return (
                                    <Link
                                        key={event.id}
                                        to={`/events/${event.id}`}
                                        onClick={onEventClick}
                                        className="block relative h-16 group"
                                    >
                                        <div
                                            className={`absolute h-full rounded-md ${getEventColor(index)} text-white p-2 overflow-hidden transition-all hover:scale-105 hover:shadow-lg`}
                                            style={position}
                                        >
                                            <div className="text-sm font-semibold truncate">{event.name}</div>
                                            <div className="text-xs opacity-90">
                                                {formatTime(event.start_time)} - {formatTime(event.end_time)}
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>
            ))}

            {/* Map Modal */}
            {mapModalDay && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
                    onClick={() => setMapModalDay(null)}
                >
                    <div
                        className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                {mapModalDay.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                            </h3>
                            <button
                                onClick={() => setMapModalDay(null)}
                                className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="p-4 h-[60vh]">
                            <DayMap events={mapModalDay.events} onEventClick={handleMapEventClick} className="h-full" />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default TimelineView;