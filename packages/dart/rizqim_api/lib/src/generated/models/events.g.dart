// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'events.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Events _$EventsFromJson(Map<String, dynamic> json) => Events(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  eventDate: json['event_date'] as String,
  slot: json['slot'] as String,
  slotLabel: json['slot_label'] as String,
  startTime: json['start_time'] as String,
  endTime: json['end_time'] as String,
  startsAt: json['starts_at'] as String,
  endsAt: json['ends_at'] as String,
  hallIds: (json['hall_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls2.fromJson(e as Map<String, dynamic>))
      .toList(),
  eventType: json['event_type'] as String,
  eventTypeLabel: json['event_type_label'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  title: json['title'] as String?,
  customerName: json['customer_name'] as String,
  customerPhone: json['customer_phone'] as String?,
  guestsGuaranteed: (json['guests_guaranteed'] as num).toInt(),
  guestsMinimum: (json['guests_minimum'] as num).toInt(),
  guestsActual: (json['guests_actual'] as num?)?.toInt(),
  guestsBilled: (json['guests_billed'] as num).toInt(),
  packageId: (json['package_id'] as num?)?.toInt(),
  packageName: json['package_name'] as String?,
  total: json['total'] as String,
  paidTotal: json['paid_total'] as String,
  outstanding: json['outstanding'] as String,
  holdExpiresAt: json['hold_expires_at'] as String?,
  frozen: json['frozen'] as bool,
  contractNo: json['contract_no'] as String?,
  version: (json['version'] as num).toInt(),
  overdue: json['overdue'] as bool,
);

Map<String, dynamic> _$EventsToJson(Events instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'event_date': instance.eventDate,
  'slot': instance.slot,
  'slot_label': instance.slotLabel,
  'start_time': instance.startTime,
  'end_time': instance.endTime,
  'starts_at': instance.startsAt,
  'ends_at': instance.endsAt,
  'hall_ids': instance.hallIds,
  'halls': instance.halls,
  'event_type': instance.eventType,
  'event_type_label': instance.eventTypeLabel,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'title': instance.title,
  'customer_name': instance.customerName,
  'customer_phone': instance.customerPhone,
  'guests_guaranteed': instance.guestsGuaranteed,
  'guests_minimum': instance.guestsMinimum,
  'guests_actual': instance.guestsActual,
  'guests_billed': instance.guestsBilled,
  'package_id': instance.packageId,
  'package_name': instance.packageName,
  'total': instance.total,
  'paid_total': instance.paidTotal,
  'outstanding': instance.outstanding,
  'hold_expires_at': instance.holdExpiresAt,
  'frozen': instance.frozen,
  'contract_no': instance.contractNo,
  'version': instance.version,
  'overdue': instance.overdue,
};
