// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data138.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data138 _$Data138FromJson(Map<String, dynamic> json) => Data138(
  branchId: (json['branch_id'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  serviceChargePercent: json['service_charge_percent'] as String,
  serviceChargeChannels: (json['service_charge_channels'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
  placeFeeChannels: (json['place_fee_channels'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  serviceChargeRules: ServiceChargeRules2.fromJson(
    json['service_charge_rules'] as Map<String, dynamic>,
  ),
  serviceChargeNotice: json['service_charge_notice'] as String?,
);

Map<String, dynamic> _$Data138ToJson(Data138 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'service_charge_percent': instance.serviceChargePercent,
  'service_charge_channels': instance.serviceChargeChannels,
  'place_fee_amount': instance.placeFeeAmount,
  'place_fee_mode': instance.placeFeeMode,
  'place_fee_channels': instance.placeFeeChannels,
  'service_charge_rules': instance.serviceChargeRules,
  'service_charge_notice': instance.serviceChargeNotice,
};
