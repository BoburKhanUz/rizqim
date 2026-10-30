// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data133.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data133 _$Data133FromJson(Map<String, dynamic> json) => Data133(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  businessDate: json['business_date'] as String,
  shiftUuid: json['shift_uuid'] as String?,
  rule: json['rule'] as String,
  roles: (json['roles'] as List<dynamic>).map((e) => e as String).toList(),
  roleWeights: Map<String, String>.from(json['role_weights'] as Map),
  poolAmount: json['pool_amount'] as String,
  tipsCount: (json['tips_count'] as num).toInt(),
  distributed: json['distributed'] as String,
  status: json['status'] as String,
  computedAt: json['computed_at'] as String?,
  closedAt: json['closed_at'] as String?,
  closedBy: json['closed_by'] == null
      ? null
      : ClosedBy3.fromJson(json['closed_by'] as Map<String, dynamic>),
  manualParticipants: json['manual_participants'] as bool,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines7.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data133ToJson(Data133 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'business_date': instance.businessDate,
  'shift_uuid': instance.shiftUuid,
  'rule': instance.rule,
  'roles': instance.roles,
  'role_weights': instance.roleWeights,
  'pool_amount': instance.poolAmount,
  'tips_count': instance.tipsCount,
  'distributed': instance.distributed,
  'status': instance.status,
  'computed_at': instance.computedAt,
  'closed_at': instance.closedAt,
  'closed_by': instance.closedBy,
  'manual_participants': instance.manualParticipants,
  'lines': instance.lines,
};
