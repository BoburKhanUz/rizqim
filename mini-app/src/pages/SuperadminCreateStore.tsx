import React, { useCallback, useState, useEffect } from 'react';
import { ChevronLeft, Save, MapPin, Building2, User as UserIcon } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import AddressLocationField from '../features/address/AddressLocationField';
import { toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue } from '../features/address/types';
import type { ApiResponse, CreatedStore, CreateStorePayload, District, Locations, Region } from '../types';

// The text inputs of the form (their `name` is the field they edit). The branch's address is part of the location field.
const TEXT_FIELDS = [
  'name', 'phone', 'commission_rate', 'owner_name', 'owner_phone',
  'branch_region_id', 'branch_district_id', 'branch_phone',
] as const;
type TextField = (typeof TEXT_FIELDS)[number];
const isTextField = (name: string): name is TextField => (TEXT_FIELDS as readonly string[]).includes(name);

// The form before it is sent: the branch's address and point come from the location field, whose pin stays empty until
// the superadmin has placed it (a store is routed to by its coordinates, so none are ever made up)
type StoreDraft = Omit<CreateStorePayload, 'branch_address' | 'branch_lat' | 'branch_lng'>;

const EMPTY_LOCATION: AddressLocationValue = { address: '', lat: null, lng: null };

export default function SuperadminCreateStore() {
  const navigate = useNavigate();

  const [loading, setLoading] = useState(false);
  const [regions, setRegions] = useState<Region[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);

  const [formData, setFormData] = useState<StoreDraft>({
    name: '',
    phone: '',
    commission_rate: 5,
    owner_name: '',
    owner_phone: '',
    branch_region_id: '',
    branch_district_id: '',
    branch_phone: '',
    branch_radius: 5,
    branch_open_time: '09:00',
    branch_close_time: '23:00'
  });

  const [branchLocation, setBranchLocation] = useState<AddressLocationValue>(EMPTY_LOCATION);
  const patchBranchLocation = useCallback((patch: Partial<AddressLocationValue>) => {
    setBranchLocation((prev) => ({ ...prev, ...patch }));
  }, []);
  // Set by a submit without a pin; it goes away by itself once there is one
  const [showPinError, setShowPinError] = useState(false);
  const branchPoint = toLatLng(branchLocation.lat, branchLocation.lng);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    if (!isTextField(name)) return;
    setFormData((prev) => ({ ...prev, [name]: value }));
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
    if (!formData.name || !formData.owner_name || !branchRegionId || branchLocation.address.trim() === '') {
      alert("Iltimos barcha majburiy maydonlarni to'ldiring.");
      return;
    }
    // Orders are routed to the branch by its coordinates: it is not created at a place nobody picked
    if (branchPoint === null) {
      hapticFeedback('error');
      setShowPinError(true);
      alert("Restoran joylashuvi xaritada belgilanmagan. Iltimos, xaritada pinni qo'ying yoki \"Joylashuvimni aniqlash\" tugmasini bosing.");
      return;
    }

    try {
      setLoading(true);
      const payload: CreateStorePayload = {
        ...formData,
        branch_region_id: branchRegionId,
        branch_district_id: branchDistrictId,
        branch_address: branchLocation.address.trim(),
        branch_lat: branchPoint.lat,
        branch_lng: branchPoint.lng,
      };
      const res = await api.post<ApiResponse<CreatedStore>>('/superadmin/stores', payload);
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

          {/* Address and map pin: find yourself, look an address up, or drop the pin; the pin is what the branch is routed by */}
          <div>
            <label className="text-xs font-bold text-hint mb-1.5 block">Aniq manzil va joylashuv (xarita) *</label>
            <AddressLocationField value={branchLocation} onChange={patchBranchLocation} required />
            {branchPoint ? (
              <p className="mt-2 text-[10px] text-hint font-mono">{branchPoint.lat}, {branchPoint.lng}</p>
            ) : showPinError && (
              <p role="alert" className="mt-2 text-[11px] font-bold text-rose-500">
                Restoran joylashuvini xaritada belgilang: bu joylashuvsiz restoran yaratilmaydi.
              </p>
            )}
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
