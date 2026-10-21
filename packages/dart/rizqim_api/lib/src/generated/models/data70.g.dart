// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data70.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data70 _$Data70FromJson(Map<String, dynamic> json) => Data70(
  branchId: (json['branch_id'] as num?)?.toInt(),
  serviceChargePercent: json['service_charge_percent'] as String,
  serviceChargeChannels: (json['service_charge_channels'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
  placeFeeChannels: (json['place_fee_channels'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data70ToJson(Data70 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'service_charge_percent': instance.serviceChargePercent,
  'service_charge_channels': instance.serviceChargeChannels,
  'place_fee_amount': instance.placeFeeAmount,
  'place_fee_mode': instance.placeFeeMode,
  'place_fee_channels': instance.placeFeeChannels,
};
