// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows2 _$Rows2FromJson(Map<String, dynamic> json) => Rows2(
  branchId: (json['branch_id'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  hallName: json['hall_name'] as String?,
  serviceChargePercent: json['service_charge_percent'] as String,
  serviceChargeChannels: (json['service_charge_channels'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
  rules: Rules2.fromJson(json['rules'] as Map<String, dynamic>),
  notice: json['notice'] as String?,
  noticePreview: json['notice_preview'] as String?,
);

Map<String, dynamic> _$Rows2ToJson(Rows2 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'hall_name': instance.hallName,
  'service_charge_percent': instance.serviceChargePercent,
  'service_charge_channels': instance.serviceChargeChannels,
  'place_fee_amount': instance.placeFeeAmount,
  'place_fee_mode': instance.placeFeeMode,
  'rules': instance.rules,
  'notice': instance.notice,
  'notice_preview': instance.noticePreview,
};
