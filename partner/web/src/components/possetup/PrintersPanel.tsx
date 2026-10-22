'use client';

import { useState, type FormEvent } from 'react';
import { Pencil, Plus, Printer as PrinterIcon, Trash2 } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, IconButton } from '@rizqim/ui';
import {
  PAPER_WIDTHS,
  PRINTER_CONNECTIONS,
  PRINTER_PURPOSES,
  useCreatePrinter,
  useDeletePrinter,
  useKitchenStations,
  usePrinters,
  useStoreDevices,
  useUpdatePrinter,
  type PaperWidth,
  type Printer,
  type PrinterConnection,
  type PrinterPurpose,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Check } from '../management/fields';
import { FormError, Notice } from '../management/Notice';
import { Pill } from '../management/Pill';
import { Gate } from '../pos/Gate';
import { SelectField, TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';

// Printers of a branch: a receipt printer at the till, a kitchen printer per station, a label printer. A LAN or Bluetooth
// printer is reached by its address; a USB or built-in one belongs to a POS terminal (it prints from that terminal).
export function PrintersPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const printers = usePrinters(branchId);
  const stations = useKitchenStations(branchId);
  const devices = useStoreDevices();
  const remove = useDeletePrinter();
  const [dialog, setDialog] = useState<{ printer: Printer | null } | null>(null);
  const [deleting, setDeleting] = useState<Printer | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const stationName = (id: number | null) => (id === null ? '—' : ((stations.data?.stations ?? []).find((station) => station.id === id)?.name ?? `#${id}`));
  const terminals = (devices.data ?? []).filter((device) => device.type === 'pos' && (device.branch_id === null || device.branch_id === branchId));
  const deviceName = (uuid: string | null) => (uuid === null ? '—' : (terminals.find((device) => device.uuid === uuid)?.name ?? uuid.slice(0, 8)));
  const where = (printer: Printer) => (printer.connection === 'lan' ? `${printer.address ?? '—'}:${printer.port ?? 9100}` : printer.connection === 'bluetooth' ? (printer.address ?? '—') : deviceName(printer.device_id));

  return (
    <Gate query={printers} count={2}>
      {(data) => (
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-headline font-bold text-fg">{t('pos_printers_title')}</h2>
              <p className="text-footnote text-fg-muted">{t('pos_printers_intro')}</p>
            </div>
            <Button icon={Plus} onClick={() => setDialog({ printer: null })}>
              {t('pos_printers_add')}
            </Button>
          </div>
          <Notice message={notice} />
          {data.length === 0 ? (
            <EmptyState icon={PrinterIcon} title={t('pos_printers_empty')} text={t('pos_printers_emptyText')} />
          ) : (
            <DataTable caption={t('pos_printers_title')}>
              <thead>
                <tr>
                  <Th>{t('pos_printer_name')}</Th>
                  <Th>{t('pos_printer_purpose')}</Th>
                  <Th>{t('pos_printer_connection')}</Th>
                  <Th>{t('pos_printer_where')}</Th>
                  <Th numeric>{t('pos_printer_paper')}</Th>
                  <Th>{t('pos_printer_station')}</Th>
                  <Th>{t('pos_station_active')}</Th>
                  <Th>{t('mgmt_actions')}</Th>
                </tr>
              </thead>
              <tbody>
                {data.map((printer) => (
                  <tr key={printer.id}>
                    <Td className="font-bold">{printer.name}</Td>
                    <Td>{labelFor(t, 'pos_purpose_', printer.purpose)}</Td>
                    <Td>{labelFor(t, 'pos_conn_', printer.connection)}</Td>
                    <Td className="font-mono text-footnote">{where(printer)}</Td>
                    <Td numeric>{printer.paper_width} mm</Td>
                    <Td>{stationName(printer.station_id)}</Td>
                    <Td>
                      <Pill tone={printer.is_active ? 'success' : 'neutral'}>{printer.is_active ? t('floor_yes') : t('floor_no')}</Pill>
                    </Td>
                    <Td>
                      <div className="flex gap-1">
                        <IconButton icon={Pencil} label={t('pos_printer_editAria', { name: printer.name })} onClick={() => setDialog({ printer })} />
                        <IconButton icon={Trash2} label={t('pos_printer_deleteAria', { name: printer.name })} onClick={() => setDeleting(printer)} />
                      </div>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
          )}

          {dialog && (
            <PrinterDialog
              branchId={branchId}
              printer={dialog.printer}
              stations={(stations.data?.stations ?? []).map((station) => ({ id: station.id, name: station.name }))}
              terminals={terminals.map((device) => ({ uuid: device.uuid, name: device.name }))}
              onClose={() => setDialog(null)}
              onSaved={(saved) => {
                setNotice(dialog.printer ? t('pos_printer_saved', { name: saved.name }) : t('pos_printer_created', { name: saved.name }));
                setDialog(null);
              }}
            />
          )}
          {deleting && (
            <ConfirmDialog
              title={t('pos_printer_deleteTitle', { name: deleting.name })}
              text={t('pos_printer_deleteText')}
              confirmLabel={t('mgmt_delete')}
              busy={remove.isPending}
              error={deleteError}
              onClose={() => {
                setDeleting(null);
                setDeleteError(null);
              }}
              onConfirm={() =>
                remove.mutate(deleting.id, {
                  onSuccess: () => {
                    setNotice(t('pos_printer_deleted', { name: deleting.name }));
                    setDeleting(null);
                  },
                  onError: (error) => setDeleteError(errorText(error, t)),
                })
              }
            />
          )}
        </Card>
      )}
    </Gate>
  );
}

const FIELDS = ['name', 'purpose', 'connection', 'address', 'port', 'paper_width', 'station_id', 'device_id', 'encoding', 'print_as_image', 'is_active', 'branch_id'];

function PrinterDialog({
  branchId,
  printer,
  stations,
  terminals,
  onClose,
  onSaved,
}: {
  branchId: number;
  printer: Printer | null;
  stations: Array<{ id: number; name: string }>;
  terminals: Array<{ uuid: string; name: string }>;
  onClose: () => void;
  onSaved: (printer: Printer) => void;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(printer?.name ?? '');
  const [purpose, setPurpose] = useState<PrinterPurpose>((printer?.purpose as PrinterPurpose | undefined) ?? 'receipt');
  const [connection, setConnection] = useState<PrinterConnection>((printer?.connection as PrinterConnection | undefined) ?? 'lan');
  const [address, setAddress] = useState(printer?.address ?? '');
  const [port, setPort] = useState(printer?.port ? String(printer.port) : '');
  const [paper, setPaper] = useState<string>(String(printer?.paper_width ?? 80));
  const [stationId, setStationId] = useState(printer?.station_id ? String(printer.station_id) : '');
  const [deviceId, setDeviceId] = useState(printer?.device_id ?? '');
  const [encoding, setEncoding] = useState(printer?.encoding ?? '');
  const [asImage, setAsImage] = useState(printer?.print_as_image ?? false);
  const [active, setActive] = useState(printer?.is_active ?? true);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreatePrinter();
  const update = useUpdatePrinter();
  const busy = create.isPending || update.isPending;

  const bound = connection === 'usb' || connection === 'builtin';
  const networked = connection === 'lan' || connection === 'bluetooth';

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('pos_err_name');
    if (networked && address.trim() === '') problems.address = t('pos_err_address');
    if (bound && deviceId === '') problems.device_id = t('pos_err_device');
    const portNumber = port.trim() === '' ? null : Number(port);
    if (connection === 'lan' && portNumber !== null && (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535)) problems.port = t('pos_err_port');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    // Only the fields the connection uses are sent: the server clears the others (an address stays on a USB printer otherwise)
    const fields = {
      name: name.trim(),
      purpose,
      connection,
      address: networked ? address.trim() : null,
      port: connection === 'lan' ? portNumber : null,
      device_id: bound ? deviceId : null,
      paper_width: Number(paper) as PaperWidth,
      station_id: purpose === 'receipt' || stationId === '' ? null : Number(stationId),
      encoding: encoding.trim() === '' ? null : encoding.trim(),
      print_as_image: asImage,
      is_active: active,
    };
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t)) };
    if (printer) update.mutate({ id: printer.id, patch: fields }, options);
    else create.mutate({ branch_id: branchId, ...fields }, options);
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={printer ? t('pos_printer_editTitle') : t('pos_printer_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('pos_printer_name')} value={name} onChange={setName} maxLength={100} autoFocus error={error('name')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label={t('pos_printer_purpose')} value={purpose} onChange={setPurpose} options={PRINTER_PURPOSES.map((value) => ({ value, label: labelFor(t, 'pos_purpose_', value) }))} error={error('purpose')} />
          <SelectField label={t('pos_printer_connection')} value={connection} onChange={setConnection} options={PRINTER_CONNECTIONS.map((value) => ({ value, label: labelFor(t, 'pos_conn_', value) }))} error={error('connection')} />
        </div>
        {networked && (
          <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
            <TextField label={t('pos_printer_address')} hint={connection === 'lan' ? t('pos_printer_addressLan') : t('pos_printer_addressBt')} value={address} onChange={setAddress} error={error('address')} />
            {connection === 'lan' && <TextField label={t('pos_printer_port')} hint="9100" value={port} inputMode="numeric" onChange={setPort} error={error('port')} />}
          </div>
        )}
        {bound && (
          <SelectField
            label={t('pos_printer_terminal')}
            hint={t('pos_printer_terminalHint')}
            value={deviceId}
            onChange={setDeviceId}
            options={[{ value: '', label: t('pos_printer_chooseTerminal') }, ...terminals.map((device) => ({ value: device.uuid, label: device.name }))]}
            error={error('device_id')}
          />
        )}
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label={t('pos_printer_paper')} value={paper} onChange={setPaper} options={PAPER_WIDTHS.map((value) => ({ value: String(value), label: `${value} mm` }))} error={error('paper_width')} />
          {purpose !== 'receipt' && (
            <SelectField label={t('pos_printer_station')} value={stationId} onChange={setStationId} options={[{ value: '', label: t('pos_printer_noStation') }, ...stations.map((station) => ({ value: String(station.id), label: station.name }))]} error={error('station_id')} />
          )}
        </div>
        <TextField label={t('pos_printer_encoding')} hint={t('pos_printer_encodingHint')} value={encoding} onChange={setEncoding} placeholder="cp866" error={error('encoding')} />
        <Check checked={asImage} onChange={setAsImage} label={t('pos_printer_image')} hint={t('pos_printer_imageHint')} />
        <Check checked={active} onChange={setActive} label={t('pos_printer_activeLabel')} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
