import React, { useMemo } from 'react';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Color palette matching TimelineView
const markerColors = [
    '#3b82f6', // blue-500
    '#22c55e', // green-500
    '#a855f7', // purple-500
    '#ec4899', // pink-500
    '#eab308', // yellow-500
    '#6366f1', // indigo-500
];

// Create colored marker icon
const createColoredIcon = (color) => {
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="
            background-color: ${color};
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        "></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12],
    });
};

const DayMap = ({ events, onEventClick, className = "h-48" }) => {
    // Filter events that have valid coordinates, keeping track of original index for color matching
    const eventsWithCoords = useMemo(() => {
        return events
            .map((event, index) => ({ ...event, colorIndex: index }))
            .filter(event =>
                event.latitude &&
                event.longitude &&
                !isNaN(parseFloat(event.latitude)) &&
                !isNaN(parseFloat(event.longitude))
            );
    }, [events]);

    // Calculate center and bounds
    const { center, zoom } = useMemo(() => {
        if (eventsWithCoords.length === 0) {
            // Default to Dallas-Fort Worth area
            return { center: [32.7767, -96.7970], zoom: 10 };
        }

        if (eventsWithCoords.length === 1) {
            return {
                center: [
                    parseFloat(eventsWithCoords[0].latitude),
                    parseFloat(eventsWithCoords[0].longitude)
                ],
                zoom: 13
            };
        }

        // Calculate bounds
        const lats = eventsWithCoords.map(e => parseFloat(e.latitude));
        const lngs = eventsWithCoords.map(e => parseFloat(e.longitude));

        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);

        const centerLat = (minLat + maxLat) / 2;
        const centerLng = (minLng + maxLng) / 2;

        // Calculate zoom based on spread
        const latSpread = maxLat - minLat;
        const lngSpread = maxLng - minLng;
        const maxSpread = Math.max(latSpread, lngSpread);

        let zoom = 12;
        if (maxSpread > 1) zoom = 8;
        else if (maxSpread > 0.5) zoom = 9;
        else if (maxSpread > 0.2) zoom = 10;
        else if (maxSpread > 0.1) zoom = 11;
        else if (maxSpread > 0.05) zoom = 12;
        else zoom = 13;

        return { center: [centerLat, centerLng], zoom };
    }, [eventsWithCoords]);

    const formatTime = (dateString) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    };

    if (eventsWithCoords.length === 0) {
        return (
            <div className={`${className} bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 text-sm`}>
                No location data available for these events
            </div>
        );
    }

    return (
        <div className={`${className} rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700`}>
            <MapContainer
                center={center}
                zoom={zoom}
                className="h-full w-full"
                scrollWheelZoom={false}
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {eventsWithCoords.map((event) => (
                    <Marker
                        key={event.id}
                        position={[parseFloat(event.latitude), parseFloat(event.longitude)]}
                        icon={createColoredIcon(markerColors[event.colorIndex % markerColors.length])}
                    >
                        <Popup>
                            <div className="min-w-[150px]">
                                <h3 className="font-semibold text-sm mb-1">{event.name}</h3>
                                <p className="text-xs text-gray-600 mb-1">
                                    {formatTime(event.start_time)} - {formatTime(event.end_time)}
                                </p>
                                {event.address && (
                                    <p className="text-xs text-gray-500">{event.address}</p>
                                )}
                                {event.city && (
                                    <p className="text-xs text-gray-500">{event.city}, {event.state}</p>
                                )}
                                <button
                                    onClick={() => onEventClick && onEventClick(event)}
                                    className="mt-2 text-xs text-blue-600 hover:text-blue-800 font-medium"
                                >
                                    View Details →
                                </button>
                            </div>
                        </Popup>
                    </Marker>
                ))}
            </MapContainer>
        </div>
    );
};

export default DayMap;
