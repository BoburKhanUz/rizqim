// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos_shift.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PosShift _$PosShiftFromJson(Map<String, dynamic> json) => PosShift(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  deviceId: (json['device_id'] as num?)?.toInt(),
  businessDate: DateTime.parse(json['business_date'] as String),
  status: ShiftStatus.fromJson(json['status'] as String),
  openedBy: json['opened_by'],
  openedAt: DateTime.parse(json['opened_at'] as String),
  openingCash: json['opening_cash'] as String,
  closedBy: json['closed_by'],
  closedAt: json['closed_at'] == null
      ? null
      : DateTime.parse(json['closed_at'] as String),
  expectedCash: json['expected_cash'] as String?,
  countedCash: json['counted_cash'] as String?,
  countedBreakdown: (json['counted_breakdown'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, (e as num).toInt()),
  ),
  difference: json['difference'] as String?,
  zNumber: (json['z_number'] as num?)?.toInt(),
  forceClosed: json['force_closed'] as bool,
  closeNote: json['close_note'] as String?,
  forcedBy: (json['forced_by'] as num?)?.toInt(),
  summary: json['summary'] as Map<String, dynamic>?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device.fromJson(json['device'] as Map<String, dynamic>),
  cashMovements: (json['cash_movements'] as List<dynamic>?)
      ?.map((e) => CashMovement.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$PosShiftToJson(PosShift instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'device_id': instance.deviceId,
  'business_date': instance.businessDate.toIso8601String(),
  'status': instance.status,
  'opened_by': instance.openedBy,
  'opened_at': instance.openedAt.toIso8601String(),
  'opening_cash': instance.openingCash,
  'closed_by': instance.closedBy,
  'closed_at': instance.closedAt?.toIso8601String(),
  'expected_cash': instance.expectedCash,
  'counted_cash': instance.countedCash,
  'counted_breakdown': instance.countedBreakdown,
  'difference': instance.difference,
  'z_number': instance.zNumber,
  'force_closed': instance.forceClosed,
  'close_note': instance.closeNote,
  'forced_by': instance.forcedBy,
  'summary': instance.summary,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'store': instance.store,
  'branch': instance.branch,
  'device': instance.device,
  'cash_movements': instance.cashMovements,
};
