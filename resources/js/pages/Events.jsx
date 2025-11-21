import React, { useState, useEffect, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../services/api';
import TimelineView from '../components/TimelineView';
import EventModal from '../components/EventModal';

const Events = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});
    const [selectedEventIndex, setSelectedEventIndex] = useState(null);
    const scrollPositionRestored = useRef(false);
    
    // Initialize from URL params first, then localStorage
    const [viewMode, setViewMode] = useState(() => {
        return searchParams.get('view') || localStorage.getItem('eventsViewMode') || 'list';
    });
    
    const [filters, setFilters] = useState(() => {
        // Check URL params first
        const urlDateFilter = searchParams.get('filter');
        if (urlDateFilter) {
            return {
                startDate: '',
                endDate: '',
                dateFilter: urlDateFilter,
            };
        }
        
        // Otherwise use saved filters
        const savedFilters = localStorage.getItem('eventsFilters');
        if (savedFilters) {
            try {
                return JSON.parse(savedFilters);
            } catch (e) {
                console.error('Failed to parse saved filters:', e);
            }
        }
        return {
            startDate: '',
            endDate: '',
            dateFilter: 'all',
        };
    });

    useEffect(() => {
        fetchEvents();
    }, [filters]);

    // Save view mode to localStorage and URL when it changes
    useEffect(() => {
        localStorage.setItem('eventsViewMode', viewMode);
        const newParams = new URLSearchParams(searchParams);
        if (viewMode !== 'list') {
            newParams.set('view', viewMode);
        } else {
            newParams.delete('view');
        }
        setSearchParams(newParams, { replace: true });
    }, [viewMode]);

    // Save filters to localStorage and URL when they change
    useEffect(() => {
        localStorage.setItem('eventsFilters', JSON.stringify(filters));
        const newParams = new URLSearchParams(searchParams);
        if (filters.dateFilter !== 'all') {
            newParams.set('filter', filters.dateFilter);
        } else {
            newParams.delete('filter');
        }
        setSearchParams(newParams, { replace: true });
    }, [filters]);

    // Restore scroll position when events are loaded
    useEffect(() => {
        if (!loading && events.length > 0 && !scrollPositionRestored.current) {
            const savedScrollPosition = sessionStorage.getItem('eventsScrollPosition');
            if (savedScrollPosition) {
                window.scrollTo(0, parseInt(savedScrollPosition));
                sessionStorage.removeItem('eventsScrollPosition');
            }
            scrollPositionRestored.current = true;
        }
    }, [loading, events]);

    // Handle event click to open modal
    const handleEventClick = (eventIndex) => {
        setSelectedEventIndex(eventIndex);
    };

    // Handle modal close
    const handleModalClose = () => {
        setSelectedEventIndex(null);
    };

    // Handle modal navigation
    const handleModalNavigate = (newIndex) => {
        setSelectedEventIndex(newIndex);
    };

    const fetchEvents = async (page = null) => {
        try {
            setLoading(true);
            
            // Use saved page or provided page or default to 1
            const savedPage = sessionStorage.getItem('eventsCurrentPage');
            const currentPage = page || (savedPage ? parseInt(savedPage) : 1);
            
            // Build query params
            const params = new URLSearchParams();
            params.append('page', currentPage);
            params.append('per_page', '100'); // Request more events per page
            
            if (filters.dateFilter === 'custom' && filters.startDate) {
                params.append('start_date', filters.startDate);
                if (filters.endDate) {
                    params.append('end_date', filters.endDate);
                }
            } else if (filters.dateFilter !== 'all') {
                params.append('date_filter', filters.dateFilter);
            }
            
            const response = await api.get(`/events?${params.toString()}`);
            setEvents(response.data.data);
            setPagination({
                currentPage: response.data.current_page,
                lastPage: response.data.last_page,
                total: response.data.total,
            });
            
            // Save current page
            sessionStorage.setItem('eventsCurrentPage', response.data.current_page.toString());
        } catch (error) {
            console.error('Error fetching events:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleDateFilterChange = (filterType) => {
        setFilters({
            ...filters,
            dateFilter: filterType,
            startDate: '',
            endDate: '',
        });
        // Reset to page 1 when filter changes
        sessionStorage.removeItem('eventsCurrentPage');
    };

    const handleCustomDateChange = (field, value) => {
        setFilters({
            ...filters,
            dateFilter: 'custom',
            [field]: value,
        });
        // Reset to page 1 when filter changes
        sessionStorage.removeItem('eventsCurrentPage');
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

    // Get smart date filter options based on current day
    const getDateFilterOptions = () => {
        const today = new Date();
        const dayOfWeek = today.getDay(); // 0 = Sunday, 6 = Saturday
        const options = [];

        // Always show these
        options.push({ value: 'all', label: 'All Events' });
        options.push({ value: 'today', label: 'Today' });
        options.push({ value: 'tomorrow', label: 'Tomorrow' });
        options.push({ value: 'this-week', label: 'This Week' });

        // Smart weekend options based on current day
        if (dayOfWeek === 0) { // Sunday
            options.push({ value: 'today', label: 'Today (Sun)' });
            options.push({ value: 'fri-sat-sun', label: 'Next Weekend' });
        } else if (dayOfWeek === 1 || dayOfWeek === 2) { // Monday or Tuesday
            options.push({ value: 'fri-sat-sun', label: 'This Weekend' });
            options.push({ value: 'sat-sun', label: 'Sat-Sun' });
        } else if (dayOfWeek === 3 || dayOfWeek === 4) { // Wednesday or Thursday
            options.push({ value: 'fri-sat-sun', label: 'This Weekend' });
            options.push({ value: 'sat-sun', label: 'This Weekend (Sat-Sun)' });
        } else if (dayOfWeek === 5) { // Friday
            options.push({ value: 'fri-sat-sun', label: 'This Weekend (Fri-Sun)' });
            options.push({ value: 'sat-sun', label: 'Sat-Sun' });
        } else if (dayOfWeek === 6) { // Saturday
            options.push({ value: 'sat-sun', label: 'This Weekend (Sat-Sun)' });
            options.push({ value: 'tomorrow', label: 'Tomorrow (Sun)' });
        }

        options.push({ value: 'this-month', label: 'This Month' });
        options.push({ value: 'custom', label: 'Custom Range' });

        // Remove duplicates by value
        const seen = new Set();
        return options.filter(option => {
            if (seen.has(option.value)) return false;
            seen.add(option.value);
            return true;
        });
    };

    const dateFilterOptions = getDateFilterOptions();

    // Group events by day
    const groupEventsByDay = (events) => {
        const grouped = {};
        events.forEach(event => {
            const date = new Date(event.start_time);
            const dateKey = date.toDateString();
            if (!grouped[dateKey]) {
                grouped[dateKey] = {
                    date: date,
                    events: []
                };
            }
            grouped[dateKey].events.push(event);
        });
        return Object.values(grouped).sort((a, b) => a.date - b.date);
    };

    const groupedEvents = groupEventsByDay(events);

    // Format day header
    const formatDayHeader = (date) => {
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        } else if (date.toDateString() === tomorrow.toDateString()) {
            return 'Tomorrow';
        } else {
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined
            });
        }
    };

    if (loading && events.length === 0) {
        return (
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="text-center text-gray-600 dark:text-gray-400">Loading events...</div>
            </div>
        );
    }

    return (
        <div className={`${viewMode === 'timeline' ? 'max-w-6xl' : 'max-w-3xl'} mx-auto px-4 sm:px-6 lg:px-8 py-8`}>
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Upcoming Car Events</h1>
                <p className="mt-2 text-lg text-gray-600 dark:text-gray-400">Discover car meets and events in your area</p>
                
                {/* View Mode Toggle */}
                <div className="mt-4 flex gap-2">
                    <button
                        onClick={() => setViewMode('list')}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            viewMode === 'list'
                                ? 'bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            List View
                        </span>
                    </button>
                    <button
                        onClick={() => setViewMode('timeline')}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            viewMode === 'timeline'
                                ? 'bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Timeline View
                        </span>
                    </button>
                </div>
            </div>

            {/* Date Filters */}
            <div className="mb-8 bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Filter by Date</h3>
                
                {/* Mobile dropdown */}
                <div className="md:hidden">
                    <select
                        value={filters.dateFilter}
                        onChange={(e) => handleDateFilterChange(e.target.value)}
                        className="w-full px-4 py-2 rounded-md text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        {dateFilterOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                
                {/* Desktop buttons */}
                <div className="hidden md:flex flex-wrap gap-2">
                    {dateFilterOptions.map((option) => (
                        <button
                            key={option.value}
                            onClick={() => handleDateFilterChange(option.value)}
                            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                                filters.dateFilter === option.value
                                    ? 'bg-indigo-600 dark:bg-indigo-500 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                {filters.dateFilter === 'custom' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Start Date
                            </label>
                            <input
                                type="date"
                                value={filters.startDate}
                                onChange={(e) => handleCustomDateChange('startDate', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                End Date
                            </label>
                            <input
                                type="date"
                                value={filters.endDate}
                                onChange={(e) => handleCustomDateChange('endDate', e.target.value)}
                                min={filters.startDate}
                                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            />
                        </div>
                    </div>
                )}
            </div>

            {loading ? (
                <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 dark:border-indigo-400"></div>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">Loading events...</p>
                </div>
            ) : events.length === 0 ? (
                <div className="text-center py-12">
                    <p className="text-xl text-gray-500 dark:text-gray-400">No events found for the selected date range.</p>
                    <p className="mt-2 text-gray-400 dark:text-gray-500">Try selecting a different date range.</p>
                </div>
            ) : viewMode === 'timeline' ? (
                <TimelineView groupedEvents={groupedEvents} events={events} onEventClick={handleEventClick} />
            ) : (
                <>
                    <div className="space-y-12">
                        {groupedEvents.map((dayGroup) => {
                            return (
                            <div key={dayGroup.date.toISOString()}>
                                {/* Day Header */}
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6 sticky top-16 bg-gray-50 dark:bg-gray-900 py-3 -mx-4 px-4 z-10 border-b border-gray-200 dark:border-gray-700">
                                    {formatDayHeader(dayGroup.date)}
                                    <span className="ml-3 text-sm font-normal text-gray-500 dark:text-gray-400">
                                        {dayGroup.events.length} event{dayGroup.events.length !== 1 ? 's' : ''}
                                    </span>
                                </h2>

                                {/* Events for this day */}
                                <div className="space-y-8">
                                    {dayGroup.events.map((event) => {
                                        const globalIndex = events.findIndex(e => e.id === event.id);
                                        return (
                                        <div
                                            key={event.id}
                                            onClick={() => handleEventClick(globalIndex)}
                                            className="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-shadow overflow-hidden cursor-pointer"
                                        >
                                {event.image ? (
                                    <img
                                        src={event.image.startsWith('http') ? event.image : `/storage/${event.image}`}
                                        alt={event.name}
                                        className="w-full"
                                    />
                                ) : (
                                    <div className="w-full h-64 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <span className="text-gray-400 dark:text-gray-500">No image</span>
                                    </div>
                                )}
                                <div className="p-6 md:p-8">
                                    <div className="flex items-start justify-between mb-4">
                                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {event.name}
                                        </h2>
                                        {event.imported_from === 'parkupfront' && (
                                            <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                ParkUpFront
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-600 dark:text-gray-400">
                                        <div className="space-y-2">
                                            <p className="flex items-center">
                                                <svg className="w-5 h-5 mr-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>{formatDate(event.start_time)}</span>
                                            </p>
                                            <p className="flex items-center">
                                                <svg className="w-5 h-5 mr-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span>Hosted by {event.user.name}</span>
                                            </p>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="flex items-center">
                                                <svg className="w-5 h-5 mr-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{event.city}, {event.state}</span>
                                            </p>
                                            <p className="flex items-center">
                                                <svg className="w-5 h-5 mr-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span className="font-semibold">
                                                    {event.cost > 0 ? `$${parseFloat(event.cost).toFixed(2)}` : 'FREE'}
                                                </span>
                                                {!event.unlimited_spots && event.spots && (
                                                    <span className="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                                        • {event.spots} spots available
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    {event.description && (
                                        <p className="mt-4 text-gray-700 dark:text-gray-300 line-clamp-3">
                                            {event.description}
                                        </p>
                                    )}
                                </div>
                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                            );
                        })}
                    </div>

                    {pagination.lastPage > 1 && (
                        <div className="mt-8 flex justify-center space-x-2">
                            {pagination.currentPage > 1 && (
                                <button
                                    onClick={() => fetchEvents(pagination.currentPage - 1)}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    Previous
                                </button>
                            )}
                            <span className="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                Page {pagination.currentPage} of {pagination.lastPage}
                            </span>
                            {pagination.currentPage < pagination.lastPage && (
                                <button
                                    onClick={() => fetchEvents(pagination.currentPage + 1)}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    Next
                                </button>
                            )}
                        </div>
                    )}
                </>
            )}

            {/* Event Detail Modal */}
            {selectedEventIndex !== null && events[selectedEventIndex] && (
                <EventModal
                    event={events[selectedEventIndex]}
                    events={events}
                    currentIndex={selectedEventIndex}
                    onClose={handleModalClose}
                    onNavigate={handleModalNavigate}
                />
            )}
        </div>
    );
};

export default Events;