// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data202.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data202 _$Data202FromJson(Map<String, dynamic> json) => Data202(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  device: json['device'] == null
      ? null
      : Device16.fromJson(json['device'] as Map<String, dynamic>),
  businessDate: json['business_date'] as String,
  openedAt: json['opened_at'] as String,
  openedBy: json['opened_by'] == null
      ? null
      : OpenedBy5.fromJson(json['opened_by'] as Map<String, dynamic>),
  openingCash: json['opening_cash'] as String,
  closedAt: json['closed_at'] as String?,
  closedBy: json['closed_by'] == null
      ? null
      : ClosedBy5.fromJson(json['closed_by'] as Map<String, dynamic>),
  expectedCash: json['expected_cash'] as String?,
  countedCash: json['counted_cash'] as String?,
  countedBreakdown: (json['counted_breakdown'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, (e as num).toInt()),
  ),
  difference: json['difference'] as String?,
  zNumber: (json['z_number'] as num?)?.toInt(),
  forceClosed: json['force_closed'] as bool,
  closeNote: json['close_note'] as String?,
  summary: json['summary'] == null
      ? null
      : Summary3.fromJson(json['summary'] as Map<String, dynamic>),
  bankTerminal: json['bank_terminal'] == null
      ? null
      : BankTerminal.fromJson(json['bank_terminal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data202ToJson(Data202 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'branch_id': instance.branchId,
  'device': instance.device,
  'business_date': instance.businessDate,
  'opened_at': instance.openedAt,
  'opened_by': instance.openedBy,
  'opening_cash': instance.openingCash,
  'closed_at': instance.closedAt,
  'closed_by': instance.closedBy,
  'expected_cash': instance.expectedCash,
  'counted_cash': instance.countedCash,
  'counted_breakdown': instance.countedBreakdown,
  'difference': instance.difference,
  'z_number': instance.zNumber,
  'force_closed': instance.forceClosed,
  'close_note': instance.closeNote,
  'summary': instance.summary,
  'bank_terminal': instance.bankTerminal,
};
