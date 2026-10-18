<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        map: null,
        marker: null,
        lat: $wire.entangle('{{ $latField }}'),
        lng: $wire.entangle('{{ $lngField }}'),
        init() {
            if (!document.getElementById('yandex-maps-js')) {
                let script = document.createElement('script');
                script.id = 'yandex-maps-js';
                script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU';
                document.head.appendChild(script);
            }

            let checkYmaps = setInterval(() => {
                if (window.ymaps && window.ymaps.Map) {
                    clearInterval(checkYmaps);
                    window.ymaps.ready(() => this.initMap());
                }
            }, 100);
        },
        initMap() {
            let initialLat = this.lat || 41.311081;
            let initialLng = this.lng || 69.240562;

            this.map = new window.ymaps.Map($refs.map, {
                center: [initialLat, initialLng],
                zoom: 12,
                controls: ['zoomControl', 'searchControl', 'typeSelector']
            });

            if (this.lat && this.lng) {
                this.marker = new window.ymaps.Placemark([this.lat, this.lng], {}, {
                    preset: 'islands#redDotIcon'
                });
                this.map.geoObjects.add(this.marker);
            }

            // Fix for map in hidden tabs/wizard steps
            setTimeout(() => {
                if (this.map && this.map.container) this.map.container.fitToViewport();
            }, 100);
            
            if (window.ResizeObserver) {
                new ResizeObserver(() => {
                    if (this.map && this.map.container) {
                        this.map.container.fitToViewport();
                    }
                }).observe($refs.map);
            }

            this.map.events.add('click', (e) => {
                let coords = e.get('coords');
                this.lat = coords[0].toFixed(6);
                this.lng = coords[1].toFixed(6);
                
                if (this.marker) {
                    this.marker.geometry.setCoordinates(coords);
                } else {
                    this.marker = new window.ymaps.Placemark(coords, {}, {
                        preset: 'islands#redDotIcon'
                    });
                    this.map.geoObjects.add(this.marker);
                }
            });

            this.$watch('lat', value => {
                if(value && this.lng && this.marker) {
                    this.marker.geometry.setCoordinates([value, this.lng]);
                    this.map.setCenter([value, this.lng]);
                }
            });
            this.$watch('lng', value => {
                if(value && this.lat && this.marker) {
                    this.marker.geometry.setCoordinates([this.lat, value]);
                    this.map.setCenter([this.lat, value]);
                }
            });
        }
    }">
        <div x-ref="map" style="height: 400px; width: 100%; border-radius: 8px; z-index: 1; border: 1px solid #ccc; background: #f3f4f6; overflow: hidden;"></div>
        
        <p class="text-sm text-gray-500 mt-2">Xaritani bosish orqali restoran joylashuvini tanlang (yoki Lat/Lng maydonlarini qo'lda kiriting).</p>
    </div>
</x-dynamic-component>
