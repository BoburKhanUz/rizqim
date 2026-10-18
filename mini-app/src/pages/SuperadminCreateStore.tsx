import React, { useCallback, useState, useEffect, useRef } from 'react';
import { ChevronLeft, Save, MapPin, Building2, User as UserIcon, Navigation } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import type { YandexMap, YandexPlacemark } from '../types/ymaps';
import type { ApiResponse, CreatedStore, CreateStorePayload, District, Locations, Region } from '../types';

// Tashkent
const DEFAULT_CENTER: [number, number] = [41.311081, 69.240562];

// The text inputs of the form (their `name` is the field they edit)
const TEXT_FIELDS = [
  'name', 'phone', 'commission_rate', 'owner_name', 'owner_phone',
  'branch_region_id', 'branch_district_id', 'branch_address', 'branch_phone',
] as const;
type TextField = (typeof TEXT_FIELDS)[number];
const isTextField = (name: string): name is TextField => (TEXT_FIELDS as readonly string[]).includes(name);

const toCoordinate = (value: number) => parseFloat(value.toFixed(6));

export default function SuperadminCreateStore() {
  const navigate = useNavigate();
  const mapRef = useRef<HTMLDivElement>(null);
  const ymapsMapRef = useRef<YandexMap | null>(null);
  const placemarkRef = useRef<YandexPlacemark | null>(null);
  
  const [loading, setLoading] = useState(false);
  const [isLocating, setIsLocating] = useState(false);
  const [regions, setRegions] = useState<Region[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);
  
  const [formData, setFormData] = useState<CreateStorePayload>({
    name: '',
    phone: '',
    commission_rate: 5,
    owner_name: '',
    owner_phone: '',
    branch_region_id: '',
    branch_district_id: '',
    branch_address: '',
    branch_phone: '',
    branch_lat: DEFAULT_CENTER[0],
    branch_lng: DEFAULT_CENTER[1],
    branch_radius: 5,
    branch_open_time: '09:00',
    branch_close_time: '23:00'
  });

  // Helper to reverse geocode and set address
  const fetchAddressFromCoords = useCallback((lat: number, lng: number) => {
    const ymaps = window.ymaps;
    if (ymaps && ymaps.geocode) {
      ymaps.geocode([lat, lng]).then((res) => {
        const firstGeoObject = res.geoObjects.get(0);
        if (firstGeoObject) {
          // getAddressLine() gets the formatted address string
          const address = firstGeoObject.getAddressLine();
          // Often returns Country, City, Street. We can just set it as is.
          setFormData(prev => ({
            ...prev,
            branch_address: address
          }));
        }
      }).catch((err: unknown) => logApiError('Failed to resolve address:', err));
    }
  }, []);

  // Simplified Map Integration (Yandex Maps)
  useEffect(() => {
    const initYmaps = () => {
      const ymaps = window.ymaps;
      if (!ymaps || !mapRef.current) return;
      ymaps.ready(() => {
        if (!mapRef.current) return;
        
        const map = new ymaps.Map(mapRef.current, {
          center: DEFAULT_CENTER,
          zoom: 12,
          controls: ['zoomControl']
        });
        ymapsMapRef.current = map;

        const placemark = new ymaps.Placemark(DEFAULT_CENTER, {}, {
          preset: 'islands#redDotIcon',
          draggable: true
        });
        placemarkRef.current = placemark;

        map.geoObjects.add(placemark);

        // Update coordinates on drag end
        placemark.events.add('dragend', () => {
          const coords = placemark.geometry.getCoordinates();
          const lat = toCoordinate(coords[0]);
          const lng = toCoordinate(coords[1]);
          setFormData(prev => ({
            ...prev,
            branch_lat: lat,
            branch_lng: lng
          }));
          fetchAddressFromCoords(lat, lng);
        });

        // Update coordinates on map click
        map.events.add('click', (e) => {
          const coords = e.get('coords');
          const lat = toCoordinate(coords[0]);
          const lng = toCoordinate(coords[1]);
          placemark.geometry.setCoordinates(coords);
          setFormData(prev => ({
            ...prev,
            branch_lat: lat,
            branch_lng: lng
          }));
          fetchAddressFromCoords(lat, lng);
        });
      });
    };

    if (!document.getElementById('yandex-maps-js')) {
      const script = document.createElement('script');
      script.id = 'yandex-maps-js';
      script.src = 'https://api-maps.yandex.ru/2.1/?lang=uz_UZ&apikey=';
      document.head.appendChild(script);
      script.onload = initYmaps;
    } else if (window.ymaps) {
      initYmaps();
    }

    return () => {
      if (ymapsMapRef.current) ymapsMapRef.current.destroy();
    };
  }, [fetchAddressFromCoords]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    if (!isTextField(name)) return;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleFindLocation = () => {
    hapticFeedback('light');
    if (!navigator.geolocation) {
      alert("Geolokatsiya qo'llab-quvvatlanmaydi");
      return;
    }
    
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setIsLocating(false);
        const { latitude, longitude } = position.coords;
        const lat = toCoordinate(latitude);
        const lng = toCoordinate(longitude);
        
        setFormData(prev => ({
          ...prev,
          branch_lat: lat,
          branch_lng: lng
        }));
        
        if (ymapsMapRef.current && placemarkRef.current) {
          ymapsMapRef.current.setCenter([lat, lng], 15);
          placemarkRef.current.geometry.setCoordinates([lat, lng]);
        }
        
        fetchAddressFromCoords(lat, lng);
        hapticFeedback('success');
      },
      () => {
        setIsLocating(false);
        hapticFeedback('error');
        alert("Lokatsiyani aniqlashda xatolik yuz berdi. Iltimos ruxsat bering.");
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  useEffect(() => {
    const fetchLocations = async () => {
      try {
        const res = await api.get<ApiResponse<Locations>>('/superadmin/locations');
        if (res.data.success) {
          setRegions(res.data.data.regions || []);
          setDistricts(res.data.data.districts || []);
        }
      } catch (err) {
        logApiError('Failed to fetch locations', err);
      }
    };
    fetchLocations();
  }, []);

  // With a single region (or a single district in the chosen region) there is nothing to choose: it is selected.
  // A chosen district that does not belong to the chosen region is dropped.
  const branchRegionId = formData.branch_region_id || (regions.length === 1 ? regions[0].id.toString() : '');
  const availableDistricts = districts.filter(d => d.region_id === Number(branchRegionId));
  const branchDistrictId = availableDistricts.some(d => d.id.toString() === formData.branch_district_id)
    ? formData.branch_district_id
    : (availableDistricts.length === 1 ? availableDistricts[0].id.toString() : '');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');
    
    // Basic validation
    if (!formData.name || !formData.owner_name || !branchRegionId) {
      alert("Iltimos barcha majburiy maydonlarni to'ldiring.");
      return;
    }

    try {
      setLoading(true);
      const res = await api.post<ApiResponse<CreatedStore>>('/superadmin/stores', {
        ...formData,
        branch_region_id: branchRegionId,
        branch_district_id: branchDistrictId,
      });
      if (res.data.success) {
        hapticFeedback('success');
        alert('Restoran muvaffaqiyatli yaratildi!\n\nLink: ' + res.data.data.invite_link);
        navigate('/superadmin/stores');
      }
    } catch (err) {
      logApiError('Failed to create store:', err);
      hapticFeedback('error');
      alert("Xatolik yuz berdi");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-4 pb-24 animate-fade-in">
      <div className="flex items-center gap-3 mb-6">
        <button 
          onClick={() => {
            hapticFeedback('light');
            navigate(-1);
          }}
          className="p-2 bg-secondary rounded-full active:scale-95 transition-transform"
        >
          <ChevronLeft className="w-6 h-6 text-text" />
        </button>
        <div>
          <h1 className="text-xl font-black text-text-h">Yangi Restoran</h1>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        
        {/* Store Details */}
        <div className="bg-secondary p-4 rounded-[24px] border border-hint/10 shadow-sm space-y-4">
          <div className="flex items-center gap-2 mb-2 text-primary">
            <Building2 className="w-5 h-5" />
            <h2 className="font-bold">Restoran Ma'lumotlari</h2>
          </div>
          
          <div>
            <label className="text-xs font-bold text-hint mb-1.5 block">Restoran Nomi *</label>
            <input 
              name="name" 
              value={formData.name} 
              onChange={handleChange} 
              className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-primary outline-none" 
              placeholder="Masalan: Evos" 
              required
            />
          </div>
          
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-bold text-hint mb-1.5 block">Telefon</label>
              <input 
                name="phone" 
                value={formData.phone} 
                onChange={handleChange} 
                className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-primary outline-none" 
                placeholder="+998" 
              />
            </div>
            <div>
              <label className="text-xs font-bold text-hint mb-1.5 block">Komissiya (%)</label>
              <input 
                name="commission_rate" 
                type="number" 
                value={formData.commission_rate} 
                onChange={handleChange} 
                className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-primary outline-none" 
              />
            </div>
          </div>
        </div>

        {/* Owner Details */}
        <div className="bg-secondary p-4 rounded-[24px] border border-hint/10 shadow-sm space-y-4">
          <div className="flex items-center gap-2 mb-2 text-orange-500">
            <UserIcon className="w-5 h-5" />
            <h2 className="font-bold">Owner (Menejer) Tayinlash</h2>
          </div>
          
          <div>
            <label className="text-xs font-bold text-hint mb-1.5 block">F.I.SH. *</label>
            <input 
              name="owner_name" 
              value={formData.owner_name} 
              onChange={handleChange} 
              className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-orange-500 outline-none" 
              placeholder="Masalan: Alisher Valiyev" 
              required
            />
          </div>
          
          <div>
            <label className="text-xs font-bold text-hint mb-1.5 block">Telefon (Owner uchun)</label>
            <input 
              name="owner_phone" 
              value={formData.owner_phone} 
              onChange={handleChange} 
              className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-orange-500 outline-none" 
              placeholder="+998" 
            />
          </div>
          <p className="text-[10px] text-hint leading-relaxed">
            Restoran saqlangach, bot sizga maxsus Telegram havolani taqdim etadi. O'sha havolani Ownerga yuborasiz!
          </p>
        </div>

        {/* Branch / Location */}
        <div className="bg-secondary p-4 rounded-[24px] border border-hint/10 shadow-sm space-y-4">
          <div className="flex items-center gap-2 mb-2 text-green-500">
            <MapPin className="w-5 h-5" />
            <h2 className="font-bold">Asosiy Filial va Manzil</h2>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-bold text-hint mb-1.5 block">Viloyat *</label>
              <select 
                name="branch_region_id" 
                value={branchRegionId} 
                onChange={handleChange}
                disabled={regions.length === 1}
                className={`w-full bg-bg border border-hint/20 rounded-xl px-3 py-3 text-sm focus:border-green-500 outline-none ${regions.length === 1 ? 'opacity-80' : ''}`}
                required
              >
                {regions.length !== 1 && <option value="">Tanlang</option>}
                {regions.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
            <div>
              <label className="text-xs font-bold text-hint mb-1.5 block">Tuman *</label>
              <select 
                name="branch_district_id" 
                value={branchDistrictId} 
                onChange={handleChange}
                disabled={availableDistricts.length === 1 || !branchRegionId}
                className={`w-full bg-bg border border-hint/20 rounded-xl px-3 py-3 text-sm focus:border-green-500 outline-none ${availableDistricts.length === 1 || !branchRegionId ? 'opacity-80' : ''}`}
                required
              >
                {availableDistricts.length !== 1 && <option value="">Tanlang</option>}
                {availableDistricts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
          </div>

          <div>
            <label className="text-xs font-bold text-hint mb-1.5 block">Aniq manzil *</label>
            <input 
              name="branch_address" 
              value={formData.branch_address} 
              onChange={handleChange} 
              className="w-full bg-bg border border-hint/20 rounded-xl px-4 py-3 text-sm focus:border-green-500 outline-none" 
              placeholder="Amir Temur ko'chasi, 15-uy" 
              required
            />
          </div>

          {/* Map Picker */}
          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="text-xs font-bold text-hint block">Xaritadan belgilang *</label>
              <button 
                type="button" 
                onClick={handleFindLocation}
                disabled={isLocating}
                className="text-[10px] bg-green-500/10 text-green-500 px-3 py-1.5 rounded-full font-bold flex items-center gap-1 active:scale-95 transition-transform"
              >
                {isLocating ? (
                  <span className="w-3 h-3 border-2 border-green-500 border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <Navigation className="w-3 h-3" />
                )}
                Joriy joylashuv
              </button>
            </div>
            <div ref={mapRef} className="w-full h-[250px] bg-bg rounded-xl border border-hint/20 overflow-hidden relative z-0">
              {/* Map loads here */}
            </div>
            <div className="flex gap-2 mt-2">
              <input readOnly value={formData.branch_lat} className="flex-1 bg-transparent border-none text-[10px] text-hint font-mono outline-none" />
              <input readOnly value={formData.branch_lng} className="flex-1 bg-transparent border-none text-[10px] text-hint font-mono outline-none" />
            </div>
          </div>

        </div>

        <button 
          type="submit" 
          disabled={loading}
          className="w-full bg-primary text-white font-extrabold text-sm py-4 rounded-2xl active:scale-95 transition-transform shadow-lg shadow-primary/20 flex items-center justify-center gap-2"
        >
          {loading ? (
             <span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          ) : (
            <>
              <Save className="w-5 h-5" />
              <span>Restoranni Saqlash</span>
            </>
          )}
        </button>

      </form>
    </div>
  );
}
