import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { useAddressStore } from '../store/useAddressStore';
import type { Address } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { Trash2, Bell, Pencil } from 'lucide-react';
import api from '../services/api';
import PhoneVerificationModal from '../components/PhoneVerificationModal';
import AddressLocationField from '../features/address/AddressLocationField';
import { toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue } from '../features/address/types';
import { classifyPhoneChange } from '../lib/phone';
import { getApiFieldError, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import type { Amount, ApiResponse, Order } from '../types';

interface Region {
  id: number;
  name: string;
  is_active: boolean;
}

interface District {
  id: number;
  region_id: number;
  name: string;
  is_active: boolean;
}

// GET /locations
interface LocationsData {
  regions: Region[];
  districts: District[];
}

const EMPTY_LOCATION: AddressLocationValue = { address: '', lat: null, lng: null };

export default function CustomerProfile() {
  const navigate = useNavigate();
  const { user, updateUser, smsVerificationRequired, setSmsVerificationRequired } = useAuthStore();
  const { addresses, deleteAddress, addAddress, updateAddress, selectedAddressId, setSelectedAddressId } = useAddressStore();

  const [addressTitle, setAddressTitle] = useState("");
  // Address text and map pin: the pin starts empty and only ever holds a point the customer picked
  const [location, setLocation] = useState<AddressLocationValue>(EMPTY_LOCATION);
  const patchLocation = useCallback((patch: Partial<AddressLocationValue>) => {
    setLocation((prev) => ({ ...prev, ...patch }));
  }, []);
  const [apartmentValue, setApartmentValue] = useState("");
  const [commentValue, setCommentValue] = useState("");
  const [isAdding, setIsAdding] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState<number | null>(null);

  // Profile Edit State
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [editName, setEditName] = useState(user?.name || "");
  const [editPhone, setEditPhone] = useState(user?.phone || "");
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  // Phone changes go through the SMS flow when the server requires verification
  const [verifyingPhone, setVerifyingPhone] = useState<string | null>(null);
  const [phoneError, setPhoneError] = useState("");

  const [completedOrdersCount, setCompletedOrdersCount] = useState(0);

  // Region & District States
  const [regions, setRegions] = useState<Region[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);
  const [selectedRegionId, setSelectedRegionId] = useState<number | "">("");
  const [selectedDistrictId, setSelectedDistrictId] = useState<number | "">("");

  // Filter active regions and districts. A single choice is simply the value (there is nothing to pick).
  const activeRegions = regions.filter(r => r.is_active);
  const regionId: number | "" = activeRegions.length === 1 ? activeRegions[0].id : selectedRegionId;
  const activeDistsForRegion = districts.filter(
    (dist) => dist.is_active && dist.region_id === Number(regionId)
  );
  const districtId: number | "" = activeDistsForRegion.length === 1 ? activeDistsForRegion[0].id : selectedDistrictId;

  // Fetch fresh user details, locations, and orders count on mount
  useEffect(() => {
    // Fresh user load
    api.get('/user').then(res => {
      if (res.data?.success && res.data.data?.user) {
        updateUser(res.data.data.user);
        setSmsVerificationRequired(!!res.data.data.sms_verification_required);
      }
    }).catch(err => logApiError('Failed to refresh user:', err));

    // Fetch locations
    api.get<ApiResponse<LocationsData>>('/locations').then(res => {
      if (res.data?.success) {
        setRegions(res.data.data.regions || []);
        setDistricts(res.data.data.districts || []);
      }
    }).catch(err => logApiError('Failed to load locations:', err));

    // Fetch order history for badge calculation
    api.get<ApiResponse<Order[]>>('/orders').then(res => {
      if (res.data?.success) {
        const delivered = res.data.data.filter(o => o.status === 'delivered');
        setCompletedOrdersCount(delivered.length);
      }
    }).catch(err => logApiError('Failed to load orders:', err));
  }, [updateUser, setSmsVerificationRequired]);

  const username = user?.telegram_username ? `@${user.telegram_username}` : "";
  const userFullName = user?.name || "Mijoz";
  const initials = user?.name ? user.name.slice(0, 2).toUpperCase() : "MJ";

  // `phoneToSave` is null when the saved phone must stay untouched (it only changes through the SMS flow)
  const saveProfile = async (phoneToSave: string | null) => {
    setIsSavingProfile(true);
    setPhoneError("");
    try {
      const fields = phoneToSave === null
        ? { name: editName.trim() }
        : { name: editName.trim(), phone: phoneToSave };
      const res = await api.post('/user/profile', fields);
      if (res.data?.success) {
        updateUser(fields);
        setIsEditingProfile(false);
        hapticFeedback('success');
      }
    } catch (err) {
      logApiError('Failed to update profile:', err);
      hapticFeedback('error');
      const phoneMessage = getApiFieldError(err, 'phone');
      if (phoneMessage) {
        setPhoneError(phoneMessage);
      } else {
        alert("Profilni saqlashda xatolik yuz berdi");
      }
    } finally {
      setIsSavingProfile(false);
    }
  };

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editName.trim()) return;

    hapticFeedback('medium');
    setPhoneError("");

    // SMS verification is off: save everything directly, as before
    if (!smsVerificationRequired) {
      await saveProfile(editPhone.trim());
      return;
    }

    // SMS verification is on: the server only accepts a new phone that went through the SMS flow
    const change = classifyPhoneChange(editPhone, user?.phone);
    if (change.kind === 'invalid' || change.kind === 'cleared') {
      hapticFeedback('error');
      setPhoneError(change.kind === 'invalid'
        ? "Telefon raqami +998 bilan boshlanib, 9 ta raqamdan iborat bo'lishi kerak."
        : "Tasdiqlangan telefon raqamini o'chirib bo'lmaydi.");
      return;
    }
    if (change.kind === 'changed') {
      setVerifyingPhone(change.fullPhone);
      return;
    }
    await saveProfile(null);
  };

  const resetAddressForm = () => {
    setAddressTitle("");
    setLocation(EMPTY_LOCATION);
    setApartmentValue("");
    setCommentValue("");
    setSelectedRegionId("");
    setSelectedDistrictId("");
    setEditingAddressId(null);
    setIsAdding(false);
  };

  const handleToggleAddressForm = () => {
    hapticFeedback('light');
    if (isAdding) {
      resetAddressForm();
    } else {
      setIsAdding(true);
    }
  };

  // Editing is also how an address saved without a map point gets one: the pin starts empty for it
  const handleEditAddress = (addr: Address) => {
    hapticFeedback('light');
    const point = toLatLng(addr.lat, addr.lng);
    setEditingAddressId(addr.id);
    setAddressTitle(addr.title);
    setLocation({ address: addr.address, lat: point?.lat ?? null, lng: point?.lng ?? null });
    setApartmentValue(addr.apartment ?? "");
    setCommentValue(addr.comment ?? "");
    setSelectedRegionId(addr.region_id ?? "");
    setSelectedDistrictId(addr.district_id ?? "");
    setIsAdding(true);
  };

  const locationPoint = toLatLng(location.lat, location.lng);

  const handleSaveAddressSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // The pin is what the delivery zone and the courier use: no address is saved without one
    if (!addressTitle || !location.address.trim() || !locationPoint) return;

    const rId = regionId ? Number(regionId) : undefined;
    const dId = districtId ? Number(districtId) : undefined;

    hapticFeedback('medium');
    const fields = {
      title: addressTitle,
      address: location.address.trim(),
      apartment: apartmentValue,
      comment: commentValue,
      lat: locationPoint.lat,
      lng: locationPoint.lng,
      region_id: rId,
      district_id: dId,
      region_name: regions.find(r => r.id === rId)?.name,
      district_name: districts.find(d => d.id === dId)?.name,
    };

    if (editingAddressId !== null) {
      updateAddress(editingAddressId, fields);
    } else {
      addAddress({ id: Date.now(), ...fields, is_default: addresses.length === 0 });
    }

    resetAddressForm();
    hapticFeedback('success');
  };

  const handleDeleteAddress = (id: number) => {
    hapticFeedback('medium');
    if (editingAddressId === id) resetAddressForm();
    deleteAddress(id);
    hapticFeedback('success');
  };

  const formatBalance = (val: Amount | undefined) => {
    const num = Math.round(toAmount(val));
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
  };

  return (
    <div className="p-4 space-y-4 pb-28 animate-fade-in bg-slate-50 min-h-screen">
      {verifyingPhone && (
        <PhoneVerificationModal
          initialPhone={verifyingPhone}
          onClose={() => setVerifyingPhone(null)}
          onVerified={(fullPhone) => {
            setVerifyingPhone(null);
            setEditPhone(fullPhone);
            updateUser({ phone: fullPhone }); // the server already saved it when the code was verified
            saveProfile(null); // name
          }}
        />
      )}

      <header className="flex justify-between items-center pb-2">
        <h1 className="text-xl font-black text-slate-800">
          Mening profilim
        </h1>
        <button
          onClick={() => { hapticFeedback('light'); navigate('/notifications'); }}
          className="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-605 hover:bg-slate-50 active:scale-95 transition relative shrink-0 shadow-sm"
        >
          <Bell className="w-4.5 h-4.5" />
          <span className="absolute top-2.5 right-2.5 w-2 h-2 bg-rose-500 rounded-full border border-white"></span>
        </button>
      </header>

      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] flex flex-col gap-4">
        {isEditingProfile ? (
          <form onSubmit={handleUpdateProfile} className="space-y-3 w-full">
            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">Profil ma'lumotlari</h4>
            <div className="space-y-2">
              <div>
                <label className="text-[10px] text-slate-400 font-bold block mb-1">Ism familya:</label>
                <input 
                  type="text"
                  required
                  value={editName}
                  onChange={(e) => setEditName(e.target.value)}
                  className="w-full text-xs bg-slate-550 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
                />
              </div>
              <div>
                <label className="text-[10px] text-slate-400 font-bold block mb-1">Telefon raqam:</label>
                <input 
                  type="text"
                  value={editPhone}
                  onChange={(e) => { setEditPhone(e.target.value); setPhoneError(""); }}
                  placeholder="+998901234567"
                  className="w-full text-xs bg-slate-550 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
                />
                {phoneError ? (
                  <p className="text-[10px] text-rose-500 font-bold mt-1 pl-1">{phoneError}</p>
                ) : smsVerificationRequired && (
                  <p className="text-[10px] text-slate-400 font-semibold mt-1 pl-1">Yangi raqam SMS kod orqali tasdiqlanadi</p>
                )}
              </div>
            </div>
            <div className="flex gap-2 pt-2">
              <button 
                type="button"
                onClick={() => {
                  hapticFeedback('light');
                  setIsEditingProfile(false);
                  setEditName(user?.name || "");
                  setEditPhone(user?.phone || "");
                }}
                className="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-xs py-2.5 rounded-xl transition"
              >
                Bekor qilish
              </button>
              <button 
                type="submit"
                disabled={isSavingProfile}
                className="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 rounded-xl transition disabled:opacity-50"
              >
                {isSavingProfile ? "Saqlanmoqda..." : "Saqlash"}
              </button>
            </div>
          </form>
        ) : (
          <div className="flex items-center justify-between w-full">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-3xl bg-amber-100 text-amber-700 font-black text-2xl flex items-center justify-center border-2 border-amber-300 shadow-sm">
                {initials}
              </div>
              <div>
                <h3 className="text-base font-black text-slate-800">{userFullName}</h3>
                {username && <p className="text-xs text-slate-400 font-semibold">{username}</p>}
                {user?.phone && (
                  <p className="text-[10px] text-slate-500 font-bold mt-0.5">📞 {user.phone}</p>
                )}
                {completedOrdersCount >= 3 ? (
                  <span className="inline-block bg-amber-50 text-amber-700 text-[9px] font-black tracking-wide px-2.5 py-0.5 rounded-full mt-1.5 border border-amber-200 shadow-sm uppercase">
                    Premium Mijoz 👑
                  </span>
                ) : (
                  <span className="inline-block bg-slate-100 text-slate-600 text-[9px] font-black tracking-wide px-2.5 py-0.5 rounded-full mt-1.5 border border-slate-200 shadow-sm uppercase">
                    Faol Mijoz ⚡
                  </span>
                )}
              </div>
            </div>
            <button 
              onClick={() => {
                hapticFeedback('light');
                setIsEditingProfile(true);
              }}
              className="text-[10px] font-black text-amber-600 bg-amber-50 px-3 py-2 rounded-xl border border-amber-100 active:scale-95 transition"
            >
              Tahrirlash ✏️
            </button>
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="bg-white p-4 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)]">
          <span className="text-[9px] text-slate-400 font-black uppercase tracking-wider block">Keshbek Balans</span>
          <h4 className="text-base font-black text-slate-800 mt-1">{formatBalance(user?.balance)} ball</h4>
        </div>
        <div className="bg-white p-4 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)]">
          <span className="text-[9px] text-slate-400 font-black uppercase tracking-wider block">Buyurtmalarim</span>
          <h4 className="text-base font-black text-slate-800 mt-1">{completedOrdersCount} ta buyurtma</h4>
        </div>
      </div>

      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3.5">
        <div className="flex justify-between items-center">
          <h4 className="text-[10px] font-black uppercase tracking-wider text-slate-400">Manzillarim</h4>
          <button 
            onClick={handleToggleAddressForm}
            className="text-[10px] font-black text-amber-600 bg-amber-50 px-2.5 py-1.5 rounded-xl border border-amber-100"
          >
            {isAdding ? "Yopish ✕" : "+ Yangi manzil"}
          </button>
        </div>

        {isAdding && (
          <form onSubmit={handleSaveAddressSubmit} className="bg-slate-50 border border-slate-100 p-4 rounded-2xl space-y-3">
            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">
              {editingAddressId !== null ? "Manzilni tahrirlash" : "Yangi Manzil kiritish"}
            </h4>
            <input 
              type="text"
              required
              value={addressTitle}
              onChange={(e) => setAddressTitle(e.target.value)}
              placeholder="Sarlavha (masalan: Uyim, Ofis)"
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
            />

            {/* Region Selector */}
            <select
              required
              disabled={activeRegions.length === 1}
              value={regionId}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedRegionId(val ? Number(val) : "");
                setSelectedDistrictId("");
              }}
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold disabled:opacity-75 disabled:bg-slate-50 disabled:text-slate-500"
            >
              {activeRegions.length > 1 && <option value="">-- Viloyatni tanlang --</option>}
              {activeRegions.map((reg) => (
                <option key={reg.id} value={reg.id}>{reg.name}</option>
              ))}
            </select>

            {/* District Selector */}
            <select
              required
              disabled={!regionId || activeDistsForRegion.length === 1}
              value={districtId}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedDistrictId(val ? Number(val) : "");
              }}
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold disabled:opacity-75 disabled:bg-slate-50 disabled:text-slate-500"
            >
              {activeDistsForRegion.length > 1 && <option value="">-- Tumanni tanlang --</option>}
              {activeDistsForRegion.map((dist) => (
                <option key={dist.id} value={dist.id}>{dist.name}</option>
              ))}
            </select>

            {/* Where exactly: find yourself, look an address up or drop a pin on the map */}
            <AddressLocationField
              key={editingAddressId ?? 'new'}
              value={location}
              onChange={patchLocation}
              required
            />

            <input 
              type="text"
              value={apartmentValue}
              onChange={(e) => setApartmentValue(e.target.value)}
              placeholder="Xonadon/Ofis raqami"
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
            />
            <input 
              type="text"
              value={commentValue}
              onChange={(e) => setCommentValue(e.target.value)}
              placeholder="Kuryerga eslatma"
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
            />
            <button 
              type="submit"
              disabled={!locationPoint}
              className="w-full bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 rounded-xl transition disabled:opacity-40"
            >
              {editingAddressId !== null ? "O'zgarishlarni saqlash" : "Manzilni saqlash"}
            </button>
            {!locationPoint && (
              <p className="text-[10px] text-center font-bold text-slate-400 -mt-1">Saqlash uchun xaritada joylashuvni belgilang</p>
            )}
          </form>
        )}

        <div className="space-y-2 text-xs">
          {addresses.length === 0 && !isAdding && (
             <p className="text-slate-400 text-xs italic">Sizda hali saqlangan manzillar yo'q</p>
          )}
          {addresses.map(addr => (
            <div 
              key={addr.id}
              onClick={() => {
                hapticFeedback('light');
                setSelectedAddressId(addr.id);
              }}
              className={`p-3 rounded-2xl flex justify-between items-center gap-2 border ${
                selectedAddressId === addr.id ? 'border-amber-500 bg-amber-50/20' : 'border-slate-100 bg-white'
              }`}
            >
              <div className="flex items-center gap-3 min-w-0">
                <span className="text-base">🏠</span>
                <div className="text-left min-w-0">
                  <p className="font-bold text-slate-800">{addr.title}</p>
                  <p className="text-slate-400 text-[10px] mt-0.5">
                    {addr.region_name ? `${addr.region_name}, ` : ''}
                    {addr.district_name ? `${addr.district_name}, ` : ''}
                    {addr.address}
                  </p>
                  {toLatLng(addr.lat, addr.lng) === null && (
                    <p className="text-[9px] text-rose-500 font-black mt-1">📍 Xaritada belgilanmagan: buyurtma berish uchun tahrirlab, joylashuvni belgilang</p>
                  )}
                </div>
              </div>
              
              <div className="flex items-center gap-1.5 shrink-0">
                <button 
                  type="button"
                  aria-label="Manzilni tahrirlash"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleEditAddress(addr);
                  }}
                  className="p-1.5 bg-slate-50 hover:bg-amber-50 text-slate-400 hover:text-amber-600 rounded-lg transition"
                >
                  <Pencil className="w-4 h-4" />
                </button>
                <button 
                  type="button"
                  aria-label="Manzilni o'chirish"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleDeleteAddress(addr.id);
                  }}
                  className="p-1.5 bg-slate-50 hover:bg-red-50 text-slate-400 hover:text-red-500 rounded-lg transition"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3.5">
        <h4 className="text-[10px] font-black uppercase tracking-wider text-slate-400">Do'stlarni taklif qiling 👥</h4>
        <div className="p-4 bg-amber-50/50 border border-amber-200 rounded-2xl text-slate-700 space-y-2">
          <p className="text-[11px] font-semibold leading-relaxed">
            Do'stingizni taklif qiling va uning birinchi muvaffaqiyatli buyurtmasidan so'ng hisobingizga <strong className="text-amber-600">5 000 so'm</strong> keshbek bonusga ega bo'ling!
          </p>
          {user?.id && (
            <div className="bg-white p-2.5 border border-slate-200 rounded-xl flex justify-between items-center text-xs font-bold">
              <span className="text-slate-500 tracking-wider">rizqim_{user.id}</span>
              <button 
                onClick={() => {
                  hapticFeedback('success');
                  const botUsername = import.meta.env.VITE_BOT_USERNAME || 'rizqimbot';
                  const refLink = `https://t.me/${botUsername}?start=rizqim_${user.id}`;
                  const text = encodeURIComponent("Rizqim dan ajoyib taomlarga buyurtma bering va keshbeklarga ega bo'ling!");
                  const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(refLink)}&text=${text}`;
                  
                  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
                    window.Telegram.WebApp.openTelegramLink(shareUrl);
                  } else {
                    window.open(shareUrl, '_blank');
                  }
                }}
                className="text-amber-600 hover:text-amber-700 bg-amber-50 px-2.5 py-1.5 rounded-lg border border-amber-200 transition"
              >
                Ulashish
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="bg-white rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] divide-y divide-slate-50 overflow-hidden text-xs font-medium text-slate-700">
        <div className="p-4 hover:bg-slate-50 cursor-pointer flex justify-between items-center transition">
          <span>Plastik kartalarim (Click/Payme)</span>
          <span className="text-slate-400">▸</span>
        </div>
        <div 
          onClick={() => { hapticFeedback('light'); navigate('/complaints'); }}
          className="p-4 hover:bg-slate-50 cursor-pointer flex justify-between items-center transition"
        >
          <span>Qo'llab-quvvatlash xizmati (Shikoyatlar)</span>
          <span className="text-slate-400">▸</span>
        </div>
        <div className="p-4 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-rose-500 transition" onClick={() => {
          if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
            window.Telegram.WebApp.close();
          }
        }}>
          <span className="font-black">Tizimni yopish (TMA)</span>
          <span>▸</span>
        </div>
      </div>
    </div>
  );
}
