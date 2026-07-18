/** Compact map preview + expandable modal for address / checkout forms */

function fieldName(prefix, name) {
    return prefix ? `${prefix}[${name}]` : name;
}

function setField(root, prefix, name, value) {
    if (value === undefined || value === null || value === '') return;

    const selector = `[name="${fieldName(prefix, name)}"]`;
    const scope = root.closest('form') || document;
    const input = scope.querySelector(selector) || root.querySelector(selector);

    if (input) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function parseOsmAddress(data, root, prefix) {
    if (!data?.address) return;
    const a = data.address;
    const line = [
        a.house_number,
        a.road || a.pedestrian || a.footway,
        a.neighbourhood || a.suburb || a.quarter,
    ].filter(Boolean).join(', ');

    setField(root, prefix, 'address_line1', line || data.display_name?.split(',')[0] || '');
    setField(root, prefix, 'city', a.city || a.town || a.municipality || a.county || 'Dhaka');
    setField(root, prefix, 'district', a.state_district || a.state || a.region || 'Dhaka');
    setField(root, prefix, 'thana', a.suburb || a.neighbourhood || a.city_district || '');
    setField(root, prefix, 'postal_code', a.postcode || '');
}

function parseGoogleComponents(result, root, prefix) {
    const parts = result.address_components || [];
    const get = (type) => parts.find((c) => c.types.includes(type))?.long_name || '';

    const line = [
        get('street_number'),
        get('route'),
        get('sublocality') || get('sublocality_level_1'),
    ].filter(Boolean).join(', ');

    setField(root, prefix, 'address_line1', line || result.formatted_address?.split(',')[0] || '');
    setField(root, prefix, 'city', get('locality') || get('administrative_area_level_2') || 'Dhaka');
    setField(root, prefix, 'district', get('administrative_area_level_1') || 'Dhaka');
    setField(root, prefix, 'thana', get('sublocality') || get('neighborhood') || '');
    setField(root, prefix, 'postal_code', get('postal_code') || '');
}

async function reverseGeocodeOsm(lat, lng, root, prefix) {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=en`;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        parseOsmAddress(data, root, prefix);
    } catch (e) {
        console.warn('Reverse geocode failed', e);
    }
}

function reverseGeocodeGoogle(lat, lng, root, prefix) {
    if (!window.google?.maps) return;
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ location: { lat, lng } }, (results, status) => {
        if (status === 'OK' && results?.[0]) {
            parseGoogleComponents(results[0], root, prefix);
        }
    });
}

function loadLeaflet() {
    return new Promise((resolve) => {
        if (typeof L !== 'undefined') {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        script.crossOrigin = '';
        script.onload = () => resolve();
        document.head.appendChild(script);
    });
}

function loadGoogle(key) {
    return new Promise((resolve) => {
        if (window.google?.maps) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places`;
        script.async = true;
        script.onload = () => resolve();
        document.head.appendChild(script);
    });
}

function readCoords(root) {
    const lat = parseFloat(root.querySelector('[data-map-lat]')?.value);
    const lng = parseFloat(root.querySelector('[data-map-lng]')?.value);
    return {
        lat: Number.isFinite(lat) ? lat : null,
        lng: Number.isFinite(lng) ? lng : null,
    };
}

function updateCoordsDisplay(root, lat, lng) {
    const el = root.querySelector('[data-map-coords]');
    if (el && Number.isFinite(lat) && Number.isFinite(lng)) {
        el.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        el.classList.remove('hidden');
    }
}

function isElementVisible(el) {
    if (!el) return false;
    return el.offsetParent !== null || el.getBoundingClientRect().width > 0;
}

function createMapPicker(root, config) {
    if (!root || !config.enabled) return;

    const state = {
        previewMap: null,
        previewMarker: null,
        modalMap: null,
        modalMarker: null,
        modalInited: false,
        useGoogle: config.provider === 'google' && config.googleKey,
    };

    function getCoords() {
        const stored = readCoords(root);
        return {
            lat: stored.lat ?? config.lat,
            lng: stored.lng ?? config.lng,
        };
    }

    function writeCoords(lat, lng, geocode = true) {
        const latInput = root.querySelector('[data-map-lat]');
        const lngInput = root.querySelector('[data-map-lng]');
        if (latInput) latInput.value = Number(lat).toFixed(7);
        if (lngInput) lngInput.value = Number(lng).toFixed(7);
        updateCoordsDisplay(root, lat, lng);

        if (geocode) {
            if (state.useGoogle) {
                reverseGeocodeGoogle(lat, lng, root, config.prefix);
            } else {
                reverseGeocodeOsm(lat, lng, root, config.prefix);
            }
        }
    }

    function syncPreviewMarker(lat, lng) {
        if (state.previewMap && state.previewMarker) {
            if (state.useGoogle) {
                state.previewMarker.setPosition({ lat, lng });
                state.previewMap.setCenter({ lat, lng });
            } else {
                state.previewMarker.setLatLng([lat, lng]);
                state.previewMap.setView([lat, lng], state.previewMap.getZoom());
            }
        }
    }

    function syncModalMarker(lat, lng) {
        if (state.modalMap && state.modalMarker) {
            if (state.useGoogle) {
                state.modalMarker.setPosition({ lat, lng });
                state.modalMap.setCenter({ lat, lng });
            } else {
                state.modalMarker.setLatLng([lat, lng]);
                state.modalMap.setView([lat, lng], state.modalMap.getZoom());
            }
        }
    }

    function bindLeafletMap(el, { draggable = true, zoom = config.zoom, onMove }) {
        const { lat, lng } = getCoords();
        const map = L.map(el, {
            scrollWheelZoom: draggable,
            dragging: draggable,
            zoomControl: draggable,
            tap: draggable,
        }).setView([lat, lng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const marker = L.marker([lat, lng], { draggable }).addTo(map);

        if (draggable) {
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                onMove(pos.lat, pos.lng);
            });
            map.on('click', (e) => {
                marker.setLatLng(e.latlng);
                onMove(e.latlng.lat, e.latlng.lng);
            });
        }

        setTimeout(() => map.invalidateSize(), 200);
        return { map, marker };
    }

    function bindGoogleMap(el, { draggable = true, zoom = config.zoom, onMove }) {
        const { lat, lng } = getCoords();
        const center = { lat, lng };

        const map = new google.maps.Map(el, {
            center,
            zoom,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: draggable,
            gestureHandling: draggable ? 'greedy' : 'none',
            zoomControl: draggable,
        });

        const marker = new google.maps.Marker({ position: center, map, draggable });

        if (draggable) {
            marker.addListener('dragend', () => {
                const pos = marker.getPosition();
                onMove(pos.lat(), pos.lng());
            });
            map.addListener('click', (e) => {
                marker.setPosition(e.latLng);
                onMove(e.latLng.lat(), e.latLng.lng());
            });
        }

        return { map, marker };
    }

    function initPreview() {
        const el = root.querySelector('[data-map-preview]');
        if (!el || state.previewMap || !isElementVisible(el)) return;

        const onMove = (lat, lng) => {
            writeCoords(lat, lng);
            syncModalMarker(lat, lng);
        };

        if (state.useGoogle) {
            const { map, marker } = bindGoogleMap(el, { draggable: false, zoom: Math.max(config.zoom - 1, 10), onMove });
            state.previewMap = map;
            state.previewMarker = marker;
        } else {
            const { map, marker } = bindLeafletMap(el, { draggable: false, zoom: Math.max(config.zoom - 1, 10), onMove });
            state.previewMap = map;
            state.previewMarker = marker;
        }
    }

    function initModal() {
        const el = root.querySelector('[data-map-modal-canvas]');
        if (!el || state.modalInited) return;

        const onMove = (lat, lng) => {
            writeCoords(lat, lng, true);
            syncPreviewMarker(lat, lng);
        };

        if (state.useGoogle) {
            const { map, marker } = bindGoogleMap(el, { draggable: true, zoom: config.zoom + 1, onMove });
            state.modalMap = map;
            state.modalMarker = marker;
        } else {
            const { map, marker } = bindLeafletMap(el, { draggable: true, zoom: config.zoom + 1, onMove });
            state.modalMap = map;
            state.modalMarker = marker;
        }

        state.modalInited = true;
    }

    function openModal() {
        initPreview();
        const modal = root.querySelector('[data-map-modal]');
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('map-picker-modal-open');
        initModal();
        const { lat, lng } = getCoords();
        syncModalMarker(lat, lng);
        setTimeout(() => {
            if (state.modalMap?.invalidateSize) {
                state.modalMap.invalidateSize();
            }
            if (state.useGoogle && state.modalMap) {
                google.maps.event.trigger(state.modalMap, 'resize');
                state.modalMap.setCenter({ lat, lng });
            }
        }, 250);
    }

    function closeModal() {
        const modal = root.querySelector('[data-map-modal]');
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('map-picker-modal-open');
        const { lat, lng } = getCoords();
        syncPreviewMarker(lat, lng);
        setTimeout(() => {
            if (state.previewMap?.invalidateSize) {
                state.previewMap.invalidateSize();
            }
        }, 200);
    }

    root.querySelector('[data-map-expand]')?.addEventListener('click', openModal);
    root.querySelectorAll('[data-map-close]').forEach((btn) => btn.addEventListener('click', closeModal));
    root.querySelector('[data-map-done]')?.addEventListener('click', closeModal);

    root.querySelector('[data-map-modal]')?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    root._mapPickerResize = () => {
        initPreview();
        setTimeout(() => {
            state.previewMap?.invalidateSize?.();
            if (state.useGoogle && state.previewMap) {
                google.maps.event.trigger(state.previewMap, 'resize');
            }
            if (!root.querySelector('[data-map-modal]')?.hidden) {
                state.modalMap?.invalidateSize?.();
                if (state.useGoogle && state.modalMap) {
                    google.maps.event.trigger(state.modalMap, 'resize');
                }
            }
        }, 200);
    };

    const bootMaps = async () => {
        if (state.useGoogle) {
            await loadGoogle(config.googleKey);
        } else {
            await loadLeaflet();
        }
        if (isElementVisible(root.querySelector('[data-map-preview]'))) {
            initPreview();
        }
        const { lat, lng } = getCoords();
        updateCoordsDisplay(root, lat, lng);
    };

    bootMaps().catch((e) => console.warn('Map picker init failed', e));
}

export function initMapAddressPickers() {
    document.querySelectorAll('[data-map-picker-root]').forEach((root) => {
        if (root.dataset.mapInited) return;
        let config;
        try {
            config = JSON.parse(root.dataset.mapConfig || '{}');
        } catch {
            return;
        }
        root.dataset.mapInited = '1';
        createMapPicker(root, config);
    });
}

export function registerMapAddressPicker() {
    document.addEventListener('DOMContentLoaded', initMapAddressPickers);

    window.addEventListener('map-picker-resize', () => {
        document.querySelectorAll('[data-map-picker-root]').forEach((root) => {
            root._mapPickerResize?.();
        });
    });
}
