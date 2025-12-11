import L from 'leaflet';
import 'leaflet.markercluster';

// Fix Leaflet's default icon paths for Vite
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
});

const STATUS_COLORS = {
    green: { bg: '#16a34a', text: '#ffffff' },
    amber: { bg: '#d97706', text: '#ffffff' },
    red: { bg: '#dc2626', text: '#ffffff' },
    zinc: { bg: '#71717a', text: '#ffffff' },
    blue: { bg: '#2563eb', text: '#ffffff' },
};

function createMarkerHtml(listing) {
    const colors = STATUS_COLORS[listing.statusColor] || STATUS_COLORS.blue;

    return `
        <div class="listing-marker" style="background-color: ${colors.bg}; color: ${colors.text};">
            <span class="listing-marker__code">${listing.typeCode}</span>
            <span class="listing-marker__price">${listing.priceShort}</span>
        </div>
    `;
}

function createPopupHtml(listing) {
    const beds = listing.beds ? `${listing.beds} bed` : '';
    const baths = listing.baths ? `${listing.baths} bath` : '';
    const sqft = listing.sqft ? `${listing.sqft.toLocaleString()} sqft` : '';
    const stats = [beds, baths, sqft].filter(Boolean).join(' | ');

    const thumbnailHtml = listing.thumbnail
        ? `<div class="map-popup__thumbnail">
               <img src="${listing.thumbnail}" alt="${listing.address || 'Property'}" loading="lazy" />
           </div>`
        : `<div class="map-popup__thumbnail map-popup__thumbnail--placeholder">
               <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                   <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
               </svg>
           </div>`;

    return `
        <div class="map-popup">
            ${thumbnailHtml}
            <div class="map-popup__content">
                <div class="map-popup__price">${listing.priceFormatted}</div>
                <div class="map-popup__status map-popup__status--${listing.statusColor}">${listing.status || 'Unknown'}</div>
                ${listing.listedAt ? `<div class="map-popup__date">Listed: ${listing.listedAt}</div>` : ''}
                ${listing.mlsNumber ? `<div class="map-popup__mls">MLS: ${listing.mlsNumber}</div>` : ''}
                ${listing.propertyType ? `<div class="map-popup__type">${listing.propertyType}</div>` : ''}
                <div class="map-popup__address">${listing.address || 'Address unavailable'}${listing.city ? `, ${listing.city}` : ''}</div>
                ${stats ? `<div class="map-popup__stats">${stats}</div>` : ''}
                <a href="${listing.url}" class="map-popup__link">View Details</a>
            </div>
        </div>
    `;
}

function createClusterIcon(cluster) {
    const count = cluster.getChildCount();
    let size = 'small';

    if (count >= 100) {
        size = 'large';
    } else if (count >= 10) {
        size = 'medium';
    }

    return L.divIcon({
        html: `<div class="marker-cluster marker-cluster--${size}"><span>${count}</span></div>`,
        className: 'marker-cluster-wrapper',
        iconSize: L.point(40, 40),
    });
}

export default function listingMap(config = {}) {
    return {
        map: null,
        markers: null,
        streetLayer: null,
        satelliteLayer: null,
        currentLayer: 'street',
        listings: config.listings || [],
        apiEndpoint: config.apiEndpoint || null,
        maptilerKey: config.maptilerKey || '',
        initialLat: config.initialLat || null,
        initialLng: config.initialLng || null,
        initialZoom: config.initialZoom || null,

        init() {
            this.$nextTick(() => {
                this.initMap();

                // Preserve initial view if lat/lng/zoom were provided
                const preserveView = !!(this.initialLat && this.initialLng);

                if (this.listings && this.listings.length > 0) {
                    this.addMarkers(this.listings, preserveView);
                } else if (this.apiEndpoint) {
                    this.fetchAndAddMarkers({}, preserveView);
                }

                // Listen for Livewire filter updates
                if (window.Livewire) {
                    Livewire.on('filters-updated', (data) => {
                        this.refreshMarkers(data.params || data[0]?.params || {});
                    });
                }
            });
        },

        initMap() {
            const container = this.$refs.mapContainer;
            if (!container || this.map) return;

            // Use initial position if provided, otherwise default to Toronto
            const center = (this.initialLat && this.initialLng)
                ? [parseFloat(this.initialLat), parseFloat(this.initialLng)]
                : [43.6532, -79.3832]; // Toronto
            const zoom = this.initialZoom ? parseInt(this.initialZoom) : 10;

            this.map = L.map(container, {
                center: center,
                zoom: zoom,
                zoomControl: true,
            });

            // Street layer (MapTiler Streets)
            this.streetLayer = L.tileLayer(
                `https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${this.maptilerKey}`,
                {
                    attribution: '&copy; <a href="https://www.maptiler.com/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19,
                }
            );

            // Satellite layer (MapTiler Satellite)
            this.satelliteLayer = L.tileLayer(
                `https://api.maptiler.com/maps/satellite/{z}/{x}/{y}.jpg?key=${this.maptilerKey}`,
                {
                    attribution: '&copy; <a href="https://www.maptiler.com/">MapTiler</a>',
                    maxZoom: 19,
                }
            );

            // Add default layer
            this.streetLayer.addTo(this.map);

            // Initialize marker cluster group
            this.markers = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                iconCreateFunction: createClusterIcon,
            });

            this.map.addLayer(this.markers);
        },

        toggleLayer() {
            if (this.currentLayer === 'street') {
                this.map.removeLayer(this.streetLayer);
                this.map.addLayer(this.satelliteLayer);
                this.currentLayer = 'satellite';
            } else {
                this.map.removeLayer(this.satelliteLayer);
                this.map.addLayer(this.streetLayer);
                this.currentLayer = 'street';
            }
        },

        addMarkers(listings, preserveView = false) {
            if (!this.markers) return;

            this.markers.clearLayers();

            const bounds = [];

            listings.forEach((listing) => {
                if (!listing.lat || !listing.lng) return;

                const marker = L.marker([listing.lat, listing.lng], {
                    icon: L.divIcon({
                        html: createMarkerHtml(listing),
                        className: 'listing-marker-wrapper',
                        iconSize: [80, 28],
                        iconAnchor: [40, 28],
                    }),
                });

                marker.bindPopup(createPopupHtml(listing), {
                    maxWidth: 300,
                    className: 'listing-popup',
                });

                this.markers.addLayer(marker);
                bounds.push([listing.lat, listing.lng]);
            });

            // Fit map to bounds if we have markers, unless preserveView is true
            if (bounds.length > 0 && !preserveView) {
                if (bounds.length === 1) {
                    this.map.setView(bounds[0], 15);
                } else {
                    this.map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        },

        async fetchAndAddMarkers(params = {}, preserveView = false) {
            if (!this.apiEndpoint) return;

            try {
                const url = new URL(this.apiEndpoint, window.location.origin);
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== '' && value !== null && value !== undefined) {
                        url.searchParams.set(key, value);
                    }
                });

                const response = await fetch(url.toString());
                const data = await response.json();

                if (data.listings) {
                    this.addMarkers(data.listings, preserveView);
                }
            } catch (error) {
                console.error('Failed to fetch map listings:', error);
            }
        },

        refreshMarkers(params = {}) {
            this.fetchAndAddMarkers(params);
        },

        destroy() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },
    };
}

// Make available globally for Alpine
window.listingMap = listingMap;
