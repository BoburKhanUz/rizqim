import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { useAddressStore } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { Trash2, Bell } from 'lucide-react';
import api from '../services/api';

export default function CustomerProfile() {
  const navigate = useNavigate();
  const { user, updateUser } = useAuthStore();
  const { addresses, deleteAddress, addAddress, selectedAddressId, setSelectedAddressId } = useAddressStore();

  const [addressTitle, setAddressTitle] = useState("");
  const [addressValue, setAddressValue] = useState("");
  const [apartmentValue, setApartmentValue] = useState("");
  const [commentValue, setCommentValue] = useState("");
  const [isAdding, setIsAdding] = useState(false);

  // Profile Edit State
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [editName, setEditName] = useState(user?.name || "");
  const [editPhone, setEditPhone] = useState(user?.phone || "");
  const [isSavingProfile, setIsSavingProfile] = useState(false);

  const [completedOrdersCount, setCompletedOrdersCount] = useState(0);

  // Region & District States
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

  const [regions, setRegions] = useState<Region[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);
  const [selectedRegionId, setSelectedRegionId] = useState<number | "">("");
  const [selectedDistrictId, setSelectedDistrictId] = useState<number | "">("");

  // Geolocation states
  const [addressLat, setAddressLat] = useState<number | null>(null);
  const [addressLng, setAddressLng] = useState<number | null>(null);
  const [isLocating, setIsLocating] = useState(false);

  // Filter active regions and districts
  const activeRegions = regions.filter(r => r.is_active);
  const activeDistsForRegion = districts.filter(
    (dist) => dist.is_active && dist.region_id === Number(selectedRegionId)
  );

  // Auto-select single active region
  useEffect(() => {
    const active = regions.filter(r => r.is_active);
    if (active.length === 1) {
      setSelectedRegionId(active[0].id);
    }
  }, [regions]);

  // Auto-select single active district for selected region
  useEffect(() => {
    if (selectedRegionId) {
      const active = districts.filter(d => d.is_active && d.region_id === Number(selectedRegionId));
      if (active.length === 1) {
        setSelectedDistrictId(active[0].id);
      } else {
        setSelectedDistrictId("");
      }
    } else {
      setSelectedDistrictId("");
    }
  }, [selectedRegionId, districts]);

  // Fetch fresh user details, locations, and orders count on mount
  useEffect(() => {
    // Fresh user load
    api.get('/user').then(res => {
      if (res.data?.success && res.data.data?.user) {
        updateUser(res.data.data.user);
      }
    }).catch(err => console.error(err));

    // Fetch locations
    api.get('/locations').then(res => {
      if (res.data?.success) {
        setRegions(res.data.data.regions || []);
        setDistricts(res.data.data.districts || []);
      }
    }).catch(err => console.error(err));

    // Fetch order history for badge calculation
    api.get('/orders').then(res => {
      if (res.data?.success) {
        const delivered = res.data.data.filter((o: any) => o.status === 'delivered' || o.status === 'completed');
        setCompletedOrdersCount(delivered.length);
      }
    }).catch(err => console.error(err));
  }, []);

  const username = user?.telegram_username ? `@${user.telegram_username}` : "";
  const userFullName = user?.name || "Mijoz";
  const initials = user?.name ? user.name.slice(0, 2).toUpperCase() : "MJ";

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editName.trim()) return;

    hapticFeedback('medium');
    setIsSavingProfile(true);
    try {
      const res = await api.post('/user/profile', {
        name: editName.trim(),
        phone: editPhone.trim()
      });
      if (res.data?.success) {
        updateUser({
          name: editName.trim(),
          phone: editPhone.trim()
        });
        setIsEditingProfile(false);
        hapticFeedback('success');
      }
    } catch (err) {
      console.error(err);
      hapticFeedback('error');
      alert("Profilni saqlashda xatolik yuz berdi");
    } finally {
      setIsSavingProfile(false);
    }
  };

  const handleGetLocation = () => {
    if (!navigator.geolocation) {
      alert("Geolokatsiya brauzeringiz tomonidan qo'llab-quvvatlanmaydi.");
      return;
    }
    hapticFeedback('light');
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        setAddressLat(latitude);
        setAddressLng(longitude);
        try {
          const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`);
          const data = await res.json();
          if (data && data.display_name) {
            setAddressValue(data.display_name);
          } else {
            setAddressValue(`📍 Lokatsiya (${latitude.toFixed(5)}, ${longitude.toFixed(5)})`);
          }
          hapticFeedback('success');
        } catch (err) {
          setAddressValue(`📍 Lokatsiya (${latitude.toFixed(5)}, ${longitude.toFixed(5)})`);
          hapticFeedback('warning');
        } finally {
          setIsLocating(false);
        }
      },
      () => {
        setIsLocating(false);
        alert("Lokatsiyani aniqlab bo'lmadi. Ruxsat berilganini tekshiring.");
        hapticFeedback('error');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const handleAddAddressSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!addressTitle || !addressValue) return;

    const rId = selectedRegionId ? Number(selectedRegionId) : undefined;
    const dId = selectedDistrictId ? Number(selectedDistrictId) : undefined;
    const rName = regions.find(r => r.id === rId)?.name;
    const dName = districts.find(d => d.id === dId)?.name;

    hapticFeedback('medium');
    try {
      await addAddress({
        id: Date.now(),
        title: addressTitle,
        address: addressValue,
        apartment: apartmentValue,
        comment: commentValue,
        lat: addressLat || 41.31,
        lng: addressLng || 69.28,
        is_default: addresses.length === 0,
        region_id: rId,
        district_id: dId,
        region_name: rName,
        district_name: dName
      });
      
      setAddressTitle("");
      setAddressValue("");
      setApartmentValue("");
      setCommentValue("");
      if (regions.filter(r => r.is_active).length > 1) {
        setSelectedRegionId("");
      }
      const activeDists = districts.filter(d => d.is_active && d.region_id === Number(selectedRegionId));
      if (activeDists.length > 1) {
        setSelectedDistrictId("");
      }
      setAddressLat(null);
      setAddressLng(null);
      setIsAdding(false);
      hapticFeedback('success');
    } catch (err) {
      console.error(err);
      hapticFeedback('error');
    }
  };

  const handleDeleteAddress = async (id: number) => {
    hapticFeedback('medium');
    try {
      await deleteAddress(id);
      hapticFeedback('success');
    } catch (err) {
      console.error(err);
      hapticFeedback('error');
    }
  };

  const formatBalance = (val: number | string | undefined) => {
    if (val === undefined || val === null) return "0";
    const num = Math.round(Number(val));
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
  };

  return (
    <div className="p-4 space-y-4 pb-28 animate-fade-in bg-slate-50 min-h-screen">
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
                  onChange={(e) => setEditPhone(e.target.value)}
                  placeholder="+998901234567"
                  className="w-full text-xs bg-slate-550 border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
                />
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
            onClick={() => {
              hapticFeedback('light');
              setIsAdding(!isAdding);
            }}
            className="text-[10px] font-black text-amber-600 bg-amber-50 px-2.5 py-1.5 rounded-xl border border-amber-100"
          >
            {isAdding ? "Yopish ✕" : "+ Yangi manzil"}
          </button>
        </div>

        {isAdding && (
          <form onSubmit={handleAddAddressSubmit} className="bg-slate-50 border border-slate-100 p-4 rounded-2xl space-y-3">
            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">Yangi Manzil kiritish</h4>
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
              value={selectedRegionId}
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
              disabled={!selectedRegionId || activeDistsForRegion.length === 1}
              value={selectedDistrictId}
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

            {/* Geolocation Trigger */}
            <button
              type="button"
              onClick={handleGetLocation}
              disabled={isLocating}
              className="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-xs py-2.5 rounded-xl transition flex justify-center items-center gap-1.5 border border-slate-200"
            >
              {isLocating ? (
                <>
                  <div className="w-3.5 h-3.5 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></div>
                  Joylashuv aniqlanmoqda...
                </>
              ) : (
                <>📍 Hozirgi joylashuvni aniqlash</>
              )}
            </button>

            <input 
              type="text"
              required
              value={addressValue}
              onChange={(e) => setAddressValue(e.target.value)}
              placeholder="To'liq manzil ko'chasi"
              className="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-amber-500 font-semibold"
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
              className="w-full bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 rounded-xl transition"
            >
              Manzilni saqlash
            </button>
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
              className={`p-3 rounded-2xl flex justify-between items-center border ${
                selectedAddressId === addr.id ? 'border-amber-500 bg-amber-50/20' : 'border-slate-100 bg-white'
              }`}
            >
              <div className="flex items-center gap-3">
                <span className="text-base">🏠</span>
                <div className="text-left">
                  <p className="font-bold text-slate-800">{addr.title}</p>
                  <p className="text-slate-400 text-[10px] mt-0.5">
                    {addr.region_name ? `${addr.region_name}, ` : ''}
                    {addr.district_name ? `${addr.district_name}, ` : ''}
                    {addr.address}
                  </p>
                </div>
              </div>
              
              <button 
                onClick={(e) => {
                  e.stopPropagation();
                  handleDeleteAddress(addr.id);
                }}
                className="p-1.5 bg-slate-50 hover:bg-red-50 text-slate-400 hover:text-red-500 rounded-lg transition"
              >
                <Trash2 className="w-4 h-4" />
              </button>
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
