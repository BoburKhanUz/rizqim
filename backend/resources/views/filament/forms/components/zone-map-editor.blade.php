{{--
    P1-24: delivery zone editor. Leaflet + Leaflet.draw (pinned cdnjs versions, loaded on first use) edit a GeoJSON
    Polygon / MultiPolygon; the same JSON string is in the textarea below, so a zone can also be pasted in as GeoJSON.
    The field state is one JSON string (DeliveryZoneResource turns it into the cleaned geometry on save).
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            branches: @js($branches ?? []),
            raw: '',
            notice: null,
            map: null,
            group: null,
            pushing: false,
            style: { color: '#4f46e5', weight: 2, fillOpacity: 0.2 },

            async init() {
                this.raw = this.state ?? '';
                try {
                    await this.loadAssets();
                } catch (e) {
                    this.notice = 'Xarita yuklanmadi. GeoJSON maydonidan foydalaning.';
                    return;
                }
                this.build();
            },

            loadAssets() {
                const assets = [
                    ['link', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', 'sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=='],
                    ['link', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css', 'sha512-gc3xjCmIy673V6MyOAZhIW93xhM9ei1I+gLbmFjUHIjocENRsLX/QUE1htk5q1XV2D/iie/VQ8DXI6Vu8bexvQ=='],
                    ['script', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', 'sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g=='],
                    ['script', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js', 'sha512-ozq8xQKq6urvuU6jNgkfqAmT7jKN2XumbrX1JiB3TnF7tI48DPI4Gy1GXKD/V3EExgAs1V+pRO7vwtS1LHg0Gw=='],
                ];
                const load = ([tag, url, integrity]) => {
                    window.__rizqimMapAssets = window.__rizqimMapAssets || {};
                    return window.__rizqimMapAssets[url] ??= new Promise((resolve, reject) => {
                        const el = document.createElement(tag);
                        if (tag === 'link') { el.rel = 'stylesheet'; el.href = url; } else { el.src = url; }
                        el.integrity = integrity;
                        el.crossOrigin = 'anonymous';
                        el.onload = resolve;
                        el.onerror = reject;
                        document.head.appendChild(el);
                    });
                };
                // Leaflet first: Leaflet.draw extends it
                return Promise.all([load(assets[0]), load(assets[1]), load(assets[2])]).then(() => load(assets[3]));
            },

            build() {
                const first = this.branches[0];
                this.map = L.map(this.$refs.map).setView(first ? [first.lat, first.lng] : [41.3111, 69.2406], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(this.map);

                this.branches.forEach(b => L.circleMarker([b.lat, b.lng], { radius: 7, color: '#dc2626', fillOpacity: 0.9 })
                    .bindTooltip(b.name).addTo(this.map));

                this.group = new L.FeatureGroup().addTo(this.map);
                this.map.addControl(new L.Control.Draw({
                    edit: { featureGroup: this.group },
                    draw: {
                        polygon: { allowIntersection: false, showArea: false, shapeOptions: this.style },
                        rectangle: { shapeOptions: this.style },
                        polyline: false, circle: false, marker: false, circlemarker: false,
                    },
                }));

                this.map.on(L.Draw.Event.CREATED, e => { this.group.addLayer(e.layer); this.push(); });
                this.map.on(L.Draw.Event.EDITED, () => this.push());
                this.map.on(L.Draw.Event.DELETED, () => this.push());

                this.load(this.state, true);
                this.$watch('state', value => { if (!this.pushing) { this.raw = value ?? ''; this.load(value, false); } });

                // Visible only once its tab/section is open
                if (window.ResizeObserver) {
                    new ResizeObserver(() => this.map && this.map.invalidateSize()).observe(this.$refs.map);
                }
            },

            // GeoJSON (geometry, Feature or FeatureCollection) -> editable layers
            load(value, fit) {
                this.group.clearLayers();
                this.notice = null;
                if (!value) return;
                let json;
                try { json = JSON.parse(value); } catch (e) { this.notice = 'GeoJSON o\'qilmadi: JSON formati noto\'g\'ri.'; return; }
                const geometries = [];
                const collect = node => {
                    if (!node) return;
                    if (node.type === 'FeatureCollection') (node.features || []).forEach(collect);
                    else if (node.type === 'Feature') collect(node.geometry);
                    else if (node.type === 'Polygon') geometries.push([node.coordinates]);
                    else if (node.type === 'MultiPolygon') geometries.push(node.coordinates);
                };
                collect(json);
                geometries.flat(1).forEach(rings => {
                    const latlngs = rings.map(ring => ring.map(([lng, lat]) => [lat, lng]));
                    this.group.addLayer(L.polygon(latlngs, this.style));
                });
                if (fit && this.group.getLayers().length) this.map.fitBounds(this.group.getBounds(), { padding: [20, 20] });
            },

            // Layers -> one JSON string (a Polygon, or a MultiPolygon for several shapes)
            serialize() {
                const polygons = [];
                this.group.eachLayer(layer => {
                    const g = layer.toGeoJSON().geometry;
                    (g.type === 'Polygon' ? [g.coordinates] : g.coordinates).forEach(p => polygons.push(p));
                });
                if (!polygons.length) return '';
                return JSON.stringify(polygons.length === 1
                    ? { type: 'Polygon', coordinates: polygons[0] }
                    : { type: 'MultiPolygon', coordinates: polygons });
            },

            push() {
                this.pushing = true;
                this.state = this.serialize();
                this.raw = this.state;
                this.notice = null;
                this.$nextTick(() => { this.pushing = false; });
            },

            // The GeoJSON box was edited by hand
            applyRaw() {
                this.state = this.raw;
                if (this.map) this.load(this.raw, true);
            },
        }"
        class="space-y-3"
    >
        <div wire:ignore>
            <div x-ref="map" style="height: 480px; width: 100%; border-radius: 8px; z-index: 0; border: 1px solid #ccc; background: #f3f4f6;"></div>
        </div>

        <p x-show="notice" x-text="notice" class="text-sm text-danger-600"></p>

        <details class="text-sm">
            <summary class="cursor-pointer text-gray-600 dark:text-gray-300">GeoJSON (qo'lda kiritish yoki nusxa olish)</summary>
            <textarea
                x-model="raw"
                x-on:change="applyRaw()"
                rows="6"
                style="width: 100%;"
                spellcheck="false"
                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white p-2 font-mono text-xs text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white"
                placeholder='{"type":"Polygon","coordinates":[[[69.24,41.31],[69.25,41.31],[69.25,41.32],[69.24,41.31]]]}'
            ></textarea>
            <p class="mt-1 text-xs text-gray-500">Koordinatalar tartibi: [uzunlik (lng), kenglik (lat)]. Polygon yoki MultiPolygon; ko'pi bilan 500 nuqta.</p>
        </details>
    </div>
</x-dynamic-component>
