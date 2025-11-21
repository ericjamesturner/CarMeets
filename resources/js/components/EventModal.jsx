import React, { useEffect } from 'react';

const EventModal = ({ event, events, currentIndex, onClose, onNavigate }) => {
    // Handle keyboard navigation
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
                onNavigate(currentIndex - 1);
            } else if (e.key === 'ArrowRight' && currentIndex < events.length - 1) {
                onNavigate(currentIndex + 1);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = 'unset';
        };
    }, [currentIndex, events.length, onClose, onNavigate]);

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

    if (!event) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header with close button */}
                <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div className="flex items-center gap-2">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white truncate">
                            {event.name}
                        </h2>
                        {event.imported_from === 'parkupfront' && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                ParkUpFront
                            </span>
                        )}
                    </div>
                    <button
                        onClick={onClose}
                        className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Scrollable content */}
                <div className="flex-1 overflow-y-auto">
                    {/* Event Image */}
                    {event.image && (
                        <img
                            src={event.image.startsWith('http') ? event.image : `/storage/${event.image}`}
                            alt={event.name}
                            className="w-full max-h-80 object-cover"
                        />
                    )}

                    <div className="p-6">
                        {/* Event Details Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                        {/* Description */}
                        {event.description && (
                            <div className="mt-6">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Description</h3>
                                <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap text-sm">
                                    {event.description}
                                </div>
                            </div>
                        )}

                        {/* Host Info */}
                        <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                <strong>Hosted by:</strong> {event.user?.name || 'Unknown'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Navigation footer */}
                <div className="flex items-center justify-between p-4 border-t border-gray-200 dark:border-gray-700 shrink-0 bg-gray-50 dark:bg-gray-900">
                    <button
                        onClick={() => onNavigate(currentIndex - 1)}
                        disabled={currentIndex === 0}
                        className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            currentIndex === 0
                                ? 'text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                        }`}
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Previous
                    </button>

                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {currentIndex + 1} of {events.length}
                    </span>

                    <button
                        onClick={() => onNavigate(currentIndex + 1)}
                        disabled={currentIndex === events.length - 1}
                        className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            currentIndex === events.length - 1
                                ? 'text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                        }`}
                    >
                        Next
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default EventModal;
