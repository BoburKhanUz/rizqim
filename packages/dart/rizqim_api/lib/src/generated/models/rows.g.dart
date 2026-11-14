// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows _$RowsFromJson(Map<String, dynamic> json) => Rows(
  uuid: json['uuid'] as String,
  eventDate: json['event_date'] as String,
  slot: json['slot'] as String,
  eventType: json['event_type'] as String,
  title: json['title'] as String?,
  branchId: (json['branch_id'] as num).toInt(),
  customerName: json['customer_name'] as String,
  customerPhone: json['customer_phone'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  guestsGuaranteed: (json['guests_guaranteed'] as num).toInt(),
  guestsMinimum: (json['guests_minimum'] as num).toInt(),
  guestsActual: (json['guests_actual'] as num?)?.toInt(),
  guests: (json['guests'] as num).toInt(),
  total: json['total'] as String,
  paid: json['paid'] as String,
  refunded: json['refunded'] as String,
  retained: json['retained'] as String,
  outstanding: json['outstanding'] as String,
  overdue: json['overdue'] as String,
  transferPending: json['transfer_pending'] as String,
);

Map<String, dynamic> _$RowsToJson(Rows instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'event_date': instance.eventDate,
  'slot': instance.slot,
  'event_type': instance.eventType,
  'title': instance.title,
  'branch_id': instance.branchId,
  'customer_name': instance.customerName,
  'customer_phone': instance.customerPhone,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'guests_guaranteed': instance.guestsGuaranteed,
  'guests_minimum': instance.guestsMinimum,
  'guests_actual': instance.guestsActual,
  'guests': instance.guests,
  'total': instance.total,
  'paid': instance.paid,
  'refunded': instance.refunded,
  'retained': instance.retained,
  'outstanding': instance.outstanding,
  'overdue': instance.overdue,
  'transfer_pending': instance.transferPending,
};
