// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fees.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fees _$FeesFromJson(Map<String, dynamic> json) => Fees(
  hallId: (json['hall_id'] as num?)?.toInt(),
  channel: json['channel'] as String,
  serviceChargePercent: json['service_charge_percent'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
  rules: json['rules'] == null
      ? null
      : Rules4.fromJson(json['rules'] as Map<String, dynamic>),
  serviceChargeNotice: json['service_charge_notice'] as String?,
);

Map<String, dynamic> _$FeesToJson(Fees instance) => <String, dynamic>{
  'hall_id': instance.hallId,
  'channel': instance.channel,
  'service_charge_percent': instance.serviceChargePercent,
  'place_fee_amount': instance.placeFeeAmount,
  'place_fee_mode': instance.placeFeeMode,
  'rules': instance.rules,
  'service_charge_notice': instance.serviceChargeNotice,
};
