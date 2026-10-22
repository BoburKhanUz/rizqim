// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_fee2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceFee2 _$ServiceFee2FromJson(Map<String, dynamic> json) => ServiceFee2(
  serviceChargePercent: json['service_charge_percent'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
);

Map<String, dynamic> _$ServiceFee2ToJson(ServiceFee2 instance) =>
    <String, dynamic>{
      'service_charge_percent': instance.serviceChargePercent,
      'place_fee_amount': instance.placeFeeAmount,
      'place_fee_mode': instance.placeFeeMode,
    };
