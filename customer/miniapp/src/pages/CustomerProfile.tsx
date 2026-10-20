import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { useAddressStore } from '../store/useAddressStore';
import type { Address } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { ChevronRight, Crown, LifeBuoy, MapPin, Pencil, Plus, Share2, Trash2, UserRound, X } from 'lucide-react';
import api from '../services/api';
import PhoneVerificationModal from '../components/PhoneVerificationModal';
import AddressLocationField from '../features/address/AddressLocationField';
import { toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue } from '../features/address/types';
import { classifyPhoneChange } from '../lib/phone';
import { getApiFieldError, logApiError } from '../lib/apiError';
import { formatMoney } from '../lib/format';
import { showAlert, showConfirm } from '../lib/dialogs';
import { NotificationBell } from '../components/catalog/NotificationBell';
import { useCopy } from '../lib/customerCopy';
import { useLanguageStore } from '../store/languageStore';
import { Button, Card, IconButton, PageHeader, Section, cx, fieldClass } from '../components/ui';
import { normalizeUser } from '../lib/user';
import { fetchOrdersPage } from '../hooks/useOrders';
import type { ApiResponse, AuthPayload } from '../types';

const COMPLETED_ORDERS_PAGE_SIZE = 50; // the most the backend serves per page

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
  const c = useCopy();
  const language = useLanguageStore((state) => state.language);
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
  const [hasMoreOrders, setHasMoreOrders] = useState(false); // older finished orders than the page that was counted

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
    api.get<ApiResponse<AuthPayload>>('/user').then(res => {
      if (res.data?.success && res.data.data?.user) {
        // The API sends the balance as a decimal string: the store keeps it as a number
        updateUser(normalizeUser(res.data.data.user));
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

    // Newest finished orders for the badge: one page, so a long history shows as "N+"
    fetchOrdersPage(null, COMPLETED_ORDERS_PAGE_SIZE).then(page => {
      setCompletedOrdersCount(page.orders.filter(o => o.status === 'delivered').length);
      setHasMoreOrders(page.nextCursor !== null);
    }).catch(err => logApiError('Failed to load orders:', err));
  }, [updateUser, setSmsVerificationRequired]);

  const username = user?.telegram_username ? `@${user.telegram_username}` : "";
  const userFullName = user?.name || c('profileGuest');

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
        void showAlert(c('profileSaveError'));
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
      setPhoneError(change.kind === 'invalid' ? c('profilePhoneInvalid') : c('profilePhoneCleared'));
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

  const handleDeleteAddress = async (id: number) => {
    hapticFeedback('medium');
    if (!(await showConfirm(c('addressDeleteConfirm'), { destructive: true, confirmText: c('addressDeleteAction') }))) return;
    if (editingAddressId === id) resetAddressForm();
    deleteAddress(id);
    hapticFeedback('success');
  };


  const initials = user?.name ? user.name.slice(0, 2).toUpperCase() : '';
  const nameFieldId = 'profile-name';
  const phoneFieldId = 'profile-phone';

  return (
    <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
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

      <PageHeader
        title={c('profileTitle')}
        action={<NotificationBell />}
      />

      <Card>
        {isEditingProfile ? (
          <form onSubmit={handleUpdateProfile} className="flex flex-col gap-3">
            <h2 className="text-headline font-bold text-fg">{c('profileInfo')}</h2>
            <div className="flex flex-col gap-1.5">
              <label htmlFor={nameFieldId} className="text-footnote font-bold text-fg-muted">
                {c('profileName')}
              </label>
              <input
                id={nameFieldId}
                type="text"
                required
                autoComplete="name"
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                className={fieldClass}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <label htmlFor={phoneFieldId} className="text-footnote font-bold text-fg-muted">
                {c('profilePhone')}
              </label>
              <input
                id={phoneFieldId}
                type="tel"
                autoComplete="tel"
                value={editPhone}
                onChange={(e) => {
                  setEditPhone(e.target.value);
                  setPhoneError('');
                }}
                placeholder="+998901234567"
                aria-invalid={phoneError ? true : undefined}
                className={fieldClass}
              />
              {phoneError ? (
                <p role="alert" className="text-footnote font-medium text-danger-soft-fg">
                  {phoneError}
                </p>
              ) : (
                smsVerificationRequired && <p className="text-footnote text-fg-muted">{c('profilePhoneHint')}</p>
              )}
            </div>
            <div className="flex gap-2 pt-1 [&>*]:flex-1">
              <Button
                variant="secondary"
                onClick={() => {
                  hapticFeedback('light');
                  setIsEditingProfile(false);
                  setEditName(user?.name || '');
                  setEditPhone(user?.phone || '');
                }}
              >
                {c('cancel')}
              </Button>
              <Button type="submit" loading={isSavingProfile}>
                {isSavingProfile ? c('saving') : c('save')}
              </Button>
            </div>
          </form>
        ) : (
          <div className="flex items-center gap-3">
            <div
              aria-hidden="true"
              className="flex size-16 shrink-0 items-center justify-center rounded-card bg-brand-soft text-title font-bold text-brand-soft-fg"
            >
              {initials || <UserRound className="size-8" />}
            </div>
            <div className="min-w-0 flex-1">
              <h2 className="break-words text-headline font-bold text-fg">{userFullName}</h2>
              {username && <p className="select-text text-footnote text-fg-muted">{username}</p>}
              {user?.phone && <p className="select-text text-footnote text-fg-muted">{user.phone}</p>}
              {completedOrdersCount >= 3 && (
                <span className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-brand-soft px-2.5 py-1 text-caption font-bold text-brand-soft-fg">
                  <Crown aria-hidden="true" className="size-3.5" />
                  {c('profilePremium')}
                </span>
              )}
            </div>
            <IconButton
              icon={Pencil}
              label={c('profileEdit')}
              variant="brand"
              onClick={() => {
                hapticFeedback('light');
                setIsEditingProfile(true);
              }}
            />
          </div>
        )}
      </Card>

      <div className="grid grid-cols-2 gap-3">
        <Card>
          <span className="block text-footnote text-fg-muted">{c('profileCashback')}</span>
          <p className="mt-1 text-headline font-bold text-fg">{formatMoney(user?.balance ?? 0, language)}</p>
        </Card>
        <Card>
          <span className="block text-footnote text-fg-muted">{c('profileOrdersLabel')}</span>
          <p className="mt-1 text-headline font-bold text-fg">
            {c('profileOrdersCount', { n: `${completedOrdersCount}${hasMoreOrders ? '+' : ''}` })}
          </p>
        </Card>
      </div>

      <Card className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-headline font-bold text-fg">{c('addressesTitle')}</h2>
          <Button variant="secondary" icon={isAdding ? X : Plus} onClick={handleToggleAddressForm}>
            {isAdding ? c('cancel') : c('addressNew')}
          </Button>
        </div>

        {isAdding && (
          <form onSubmit={handleSaveAddressSubmit} className="flex flex-col gap-3 rounded-card bg-surface-2 p-4">
            <h3 className="text-body font-bold text-fg">{editingAddressId !== null ? c('addressEditTitle') : c('addressNewTitle')}</h3>
            <input
              type="text"
              required
              aria-label={c('addressTitlePlaceholder')}
              value={addressTitle}
              onChange={(e) => setAddressTitle(e.target.value)}
              placeholder={c('addressTitlePlaceholder')}
              className={fieldClass}
            />

            <select
              required
              aria-label={c('addressRegion')}
              disabled={activeRegions.length === 1}
              value={regionId}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedRegionId(val ? Number(val) : '');
                setSelectedDistrictId('');
              }}
              className={fieldClass}
            >
              {activeRegions.length > 1 && <option value="">{c('addressRegion')}</option>}
              {activeRegions.map((reg) => (
                <option key={reg.id} value={reg.id}>
                  {reg.name}
                </option>
              ))}
            </select>

            <select
              required
              aria-label={c('addressDistrict')}
              disabled={!regionId || activeDistsForRegion.length === 1}
              value={districtId}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedDistrictId(val ? Number(val) : '');
              }}
              className={fieldClass}
            >
              {activeDistsForRegion.length > 1 && <option value="">{c('addressDistrict')}</option>}
              {activeDistsForRegion.map((dist) => (
                <option key={dist.id} value={dist.id}>
                  {dist.name}
                </option>
              ))}
            </select>

            {/* Where exactly: find yourself, look an address up or drop a pin on the map */}
            <AddressLocationField key={editingAddressId ?? 'new'} value={location} onChange={patchLocation} required />

            <input
              type="text"
              aria-label={c('addressApartment')}
              value={apartmentValue}
              onChange={(e) => setApartmentValue(e.target.value)}
              placeholder={c('addressApartment')}
              className={fieldClass}
            />
            <input
              type="text"
              aria-label={c('addressCourierNote')}
              value={commentValue}
              onChange={(e) => setCommentValue(e.target.value)}
              placeholder={c('addressCourierNote')}
              className={fieldClass}
            />
            <Button type="submit" fullWidth disabled={!locationPoint}>
              {editingAddressId !== null ? c('addressSaveChanges') : c('addressSave')}
            </Button>
            {!locationPoint && <p className="-mt-1 text-center text-footnote text-fg-muted">{c('addressPinHint')}</p>}
          </form>
        )}

        {addresses.length === 0 && !isAdding && <p className="text-footnote text-fg-muted">{c('addressNone')}</p>}

        <ul className="flex flex-col gap-2">
          {addresses.map((addr) => {
            const selected = selectedAddressId === addr.id;
            return (
              <li
                key={addr.id}
                className={cx(
                  'flex items-center gap-1 rounded-card border py-1 pl-3 pr-1',
                  selected ? 'border-brand bg-brand-soft' : 'border-line bg-surface',
                )}
              >
                <button
                  type="button"
                  aria-pressed={selected}
                  aria-label={`${c('addressSelect')}: ${addr.title}`}
                  onClick={() => {
                    selectionChanged();
                    setSelectedAddressId(addr.id);
                  }}
                  className="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3 py-1 text-left"
                >
                  <MapPin aria-hidden="true" className={cx('size-5 shrink-0', selected ? 'text-brand-soft-fg' : 'text-brand')} />
                  <span className="min-w-0">
                    <span className="block text-body font-bold text-fg">{addr.title}</span>
                    <span className="block break-words text-footnote text-fg-muted">
                      {addr.region_name ? `${addr.region_name}, ` : ''}
                      {addr.district_name ? `${addr.district_name}, ` : ''}
                      {addr.address}
                    </span>
                    {toLatLng(addr.lat, addr.lng) === null && (
                      <span className="mt-1 block text-footnote font-medium text-danger-soft-fg">{c('addressNoPin')}</span>
                    )}
                  </span>
                </button>
                <IconButton icon={Pencil} label={c('addressEdit')} onClick={() => handleEditAddress(addr)} />
                <IconButton icon={Trash2} label={c('addressDelete')} onClick={() => void handleDeleteAddress(addr.id)} />
              </li>
            );
          })}
        </ul>
      </Card>

      <Section title={c('inviteTitle')}>
        <Card className="flex flex-col gap-3">
          <p className="text-footnote text-fg">{c('inviteText', { amount: formatMoney(5000, language) })}</p>
          {user?.id && (
            <div className="flex items-center justify-between gap-3 rounded-control bg-surface-2 py-1 pl-3 pr-1">
              <span className="select-text text-body font-medium text-fg-muted">rizqim_{user.id}</span>
              <Button
                variant="secondary"
                icon={Share2}
                onClick={() => {
                  hapticFeedback('success');
                  const botUsername = import.meta.env.VITE_BOT_USERNAME || 'rizqimbot';
                  const refLink = `https://t.me/${botUsername}?start=rizqim_${user.id}`;
                  const text = encodeURIComponent(c('inviteMessage'));
                  const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(refLink)}&text=${text}`;

                  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
                    window.Telegram.WebApp.openTelegramLink(shareUrl);
                  } else {
                    window.open(shareUrl, '_blank');
                  }
                }}
              >
                {c('inviteShare')}
              </Button>
            </div>
          )}
        </Card>
      </Section>

      <Card padded={false}>
        <button
          type="button"
          onClick={() => {
            hapticFeedback('light');
            navigate('/complaints');
          }}
          className="flex min-h-14 w-full cursor-pointer items-center gap-3 rounded-card px-4 text-left text-body font-medium text-fg focus-visible:outline-2 focus-visible:outline-brand"
        >
          <LifeBuoy aria-hidden="true" className="size-5 shrink-0 text-brand" />
          <span className="flex-1">{c('supportRow')}</span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </div>
  );
}
