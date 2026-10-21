import React, { useCallback, useRef, useState, useEffect } from 'react';
import { MapPin, Building2, User as UserIcon } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { createStore, fetchLocations as loadLocations, type CreateStoreInput, type DistrictItem, type LocationItem } from '@rizqim/staff';
import { staffApi } from '../services/staffApi';
import { logApiError } from '../lib/apiError';
import { Card, Field, PageHeader, Section, fieldClass } from '../components/ui';
import AddressLocationField from '../features/address/AddressLocationField';
import { toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue } from '../features/address/types';

// The text inputs of the form (their `name` is the field they edit). The branch's address is part of the location field.
const TEXT_FIELDS = [
  'name', 'phone', 'commission_rate', 'owner_name', 'owner_phone',
  'branch_region_id', 'branch_district_id', 'branch_phone',
] as const;
type TextField = (typeof TEXT_FIELDS)[number];
const isTextField = (name: string): name is TextField => (TEXT_FIELDS as readonly string[]).includes(name);

// The form before it is sent: the branch's address and point come from the location field, whose pin stays empty until
// the superadmin has placed it (a store is routed to by its coordinates, so none are ever made up)
type StoreDraft = Omit<CreateStoreInput, 'branch_address' | 'branch_lat' | 'branch_lng' | 'phone' | 'owner_phone' | 'branch_phone'> & {
  phone: string;
  owner_phone: string;
  branch_phone: string;
};

const EMPTY_LOCATION: AddressLocationValue = { address: '', lat: null, lng: null };

export default function SuperadminCreateStore() {
  const navigate = useNavigate();
  const formRef = useRef<HTMLFormElement>(null);

  const [loading, setLoading] = useState(false);
  const [regions, setRegions] = useState<LocationItem[]>([]);
  const [districts, setDistricts] = useState<DistrictItem[]>([]);

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
        const locations = await loadLocations(staffApi);
        setRegions(locations.regions || []);
        setDistricts(locations.districts || []);
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
      void showAlert("Iltimos barcha majburiy maydonlarni to'ldiring.");
      return;
    }
    // Orders are routed to the branch by its coordinates: it is not created at a place nobody picked
    if (branchPoint === null) {
      notificationOccurred('error');
      setShowPinError(true);
      void showAlert("Restoran joylashuvi xaritada belgilanmagan. Iltimos, xaritada pinni qo'ying yoki \"Joylashuvimni aniqlash\" tugmasini bosing.");
      return;
    }

    try {
      setLoading(true);
      const payload: CreateStoreInput = {
        ...formData,
        branch_region_id: branchRegionId,
        branch_district_id: branchDistrictId,
        branch_address: branchLocation.address.trim(),
        branch_lat: branchPoint.lat,
        branch_lng: branchPoint.lng,
      };
      const created = await createStore(staffApi, payload);
      notificationOccurred('success');
      await showAlert('Restoran muvaffaqiyatli yaratildi!\n\nLink: ' + created.invite_link);
      navigate('/superadmin/stores');
    } catch (err) {
      logApiError('Failed to create store:', err);
      notificationOccurred('error');
      void showAlert("Xatolik yuz berdi");
    } finally {
      setLoading(false);
    }
  };

  // The one action of the screen, always at the bottom. requestSubmit runs the form's own validation first.
  useMainButton({
    text: 'Restoranni saqlash',
    onClick: () => formRef.current?.requestSubmit(),
    progress: loading,
  });

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title="Yangi restoran" />

      <form ref={formRef} onSubmit={handleSubmit} className="flex flex-col gap-5">
        {/* Store details */}
        <Section
          title={
            <span className="inline-flex items-center gap-1.5">
              <Building2 aria-hidden="true" className="size-4 text-brand" />
              Restoran ma'lumotlari
            </span>
          }
          label="Restoran ma'lumotlari"
        >
          <Card className="flex flex-col gap-4">
            <Field label="Restoran nomi *">
              {(props) => (
                <input {...props} name="name" value={formData.name} onChange={handleChange} className={fieldClass} placeholder="Masalan: Evos" required />
              )}
            </Field>

            <div className="grid grid-cols-2 gap-3">
              <Field label="Telefon">
                {(props) => <input {...props} name="phone" type="tel" value={formData.phone} onChange={handleChange} className={fieldClass} placeholder="+998" />}
              </Field>
              <Field label="Komissiya (%)">
                {(props) => <input {...props} name="commission_rate" type="number" inputMode="decimal" value={formData.commission_rate} onChange={handleChange} className={fieldClass} />}
              </Field>
            </div>
          </Card>
        </Section>

        {/* Owner details */}
        <Section
          title={
            <span className="inline-flex items-center gap-1.5">
              <UserIcon aria-hidden="true" className="size-4 text-brand" />
              Restoran egasini tayinlash
            </span>
          }
          label="Restoran egasini tayinlash"
        >
          <Card className="flex flex-col gap-4">
            <Field label="Ism-familiya *">
              {(props) => (
                <input {...props} name="owner_name" value={formData.owner_name} onChange={handleChange} className={fieldClass} placeholder="Masalan: Alisher Valiyev" required />
              )}
            </Field>

            <Field
              label="Egasining telefoni"
              hint="Restoran saqlangach, bot sizga maxsus Telegram havolani beradi. O'sha havolani egasiga yuborasiz."
            >
              {(props) => <input {...props} name="owner_phone" type="tel" value={formData.owner_phone} onChange={handleChange} className={fieldClass} placeholder="+998" />}
            </Field>
          </Card>
        </Section>

        {/* Branch / location */}
        <Section
          title={
            <span className="inline-flex items-center gap-1.5">
              <MapPin aria-hidden="true" className="size-4 text-brand" />
              Asosiy filial va manzil
            </span>
          }
          label="Asosiy filial va manzil"
        >
          <Card className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-3">
              <Field label="Viloyat *">
                {(props) => (
                  <select
                    {...props}
                    name="branch_region_id"
                    value={branchRegionId}
                    onChange={handleChange}
                    disabled={regions.length === 1}
                    className={fieldClass}
                    required
                  >
                    {regions.length !== 1 && <option value="">Tanlang</option>}
                    {regions.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                  </select>
                )}
              </Field>
              <Field label="Tuman *">
                {(props) => (
                  <select
                    {...props}
                    name="branch_district_id"
                    value={branchDistrictId}
                    onChange={handleChange}
                    disabled={availableDistricts.length === 1 || !branchRegionId}
                    className={fieldClass}
                    required
                  >
                    {availableDistricts.length !== 1 && <option value="">Tanlang</option>}
                    {availableDistricts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                )}
              </Field>
            </div>

            {/* Address and map pin: find yourself, look an address up, or drop the pin; the pin is what the branch is routed by */}
            <div className="flex flex-col gap-1.5">
              <p className="text-footnote font-bold text-fg-muted">Aniq manzil va joylashuv (xarita) *</p>
              <AddressLocationField value={branchLocation} onChange={patchBranchLocation} required />
              {branchPoint ? (
                <p className="font-mono text-footnote text-fg-muted">{branchPoint.lat}, {branchPoint.lng}</p>
              ) : showPinError && (
                <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                  Restoran joylashuvini xaritada belgilang: bu joylashuvsiz restoran yaratilmaydi.
                </p>
              )}
            </div>
          </Card>
        </Section>
      </form>
    </div>
  );
}
