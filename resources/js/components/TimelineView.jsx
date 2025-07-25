import React from 'react';
import { Link } from 'react-router-dom';

const TimelineView = ({ groupedEvents, onEventClick }) => {
    // Generate hour labels from 6 AM to 11 PM
    const hours = Array.from({ length: 18 }, (_, i) => i + 6);
    
    // Check if mobile
    const [isMobile, setIsMobile] = React.useState(window.innerWidth < 768);
    
    React.useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
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

    // Mobile vertical timeline
    if (isMobile) {
        return (
            <div className="space-y-6">
                {groupedEvents.map((day) => (
                    <div key={day.date.toISOString()} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            {day.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                        </h2>
                        
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
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-4">
                        {day.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                    </h2>
                    
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
        </div>
    );
};

export default TimelineView;