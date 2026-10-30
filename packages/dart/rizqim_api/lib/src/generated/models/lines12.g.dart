// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines12 _$Lines12FromJson(Map<String, dynamic> json) => Lines12(
  uuid: json['uuid'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  userName: json['user_name'] as String?,
  role: json['role'] as String?,
  roleLabel: json['role_label'] as String?,
  metrics: Metrics5.fromJson(json['metrics'] as Map<String, dynamic>),
  components: (json['components'] as List<dynamic>)
      .map((e) => Components8.fromJson(e as Map<String, dynamic>))
      .toList(),
  gross: json['gross'] as String,
  advances: json['advances'] as String,
  tipsPaid: json['tips_paid'] as String,
  adjustment: json['adjustment'] as String,
  adjustmentReason: json['adjustment_reason'] as String?,
  payable: json['payable'] as String,
  paid: json['paid'] as bool,
  paidAt: json['paid_at'] as String?,
  paidMethod: json['paid_method'] as String?,
  paidNote: json['paid_note'] as String?,
  cashMovementId: (json['cash_movement_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Lines12ToJson(Lines12 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'user_id': instance.userId,
  'user_name': instance.userName,
  'role': instance.role,
  'role_label': instance.roleLabel,
  'metrics': instance.metrics,
  'components': instance.components,
  'gross': instance.gross,
  'advances': instance.advances,
  'tips_paid': instance.tipsPaid,
  'adjustment': instance.adjustment,
  'adjustment_reason': instance.adjustmentReason,
  'payable': instance.payable,
  'paid': instance.paid,
  'paid_at': instance.paidAt,
  'paid_method': instance.paidMethod,
  'paid_note': instance.paidNote,
  'cash_movement_id': instance.cashMovementId,
};
