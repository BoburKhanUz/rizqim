// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  from: json['from'] as String,
  to: json['to'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  events: (json['events'] as num).toInt(),
  cancelled: (json['cancelled'] as num).toInt(),
  byStatus: (json['by_status'] as List<dynamic>)
      .map((e) => ByStatus.fromJson(e as Map<String, dynamic>))
      .toList(),
  guests: (json['guests'] as num).toInt(),
  total: json['total'] as String,
  paid: json['paid'] as String,
  outstanding: json['outstanding'] as String,
  overdue: Overdue.fromJson(json['overdue'] as Map<String, dynamic>),
  retained: json['retained'] as String,
  refunded: json['refunded'] as String,
  transferPending: json['transfer_pending'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'from': instance.from,
  'to': instance.to,
  'branch_id': instance.branchId,
  'events': instance.events,
  'cancelled': instance.cancelled,
  'by_status': instance.byStatus,
  'guests': instance.guests,
  'total': instance.total,
  'paid': instance.paid,
  'outstanding': instance.outstanding,
  'overdue': instance.overdue,
  'retained': instance.retained,
  'refunded': instance.refunded,
  'transfer_pending': instance.transferPending,
  'rows': instance.rows,
};
