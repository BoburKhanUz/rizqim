// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_fee.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceFee _$ServiceFeeFromJson(Map<String, dynamic> json) => ServiceFee(
  serviceChargePercent: json['service_charge_percent'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  placeFeeMode: json['place_fee_mode'] as String,
);

Map<String, dynamic> _$ServiceFeeToJson(ServiceFee instance) =>
    <String, dynamic>{
      'service_charge_percent': instance.serviceChargePercent,
      'place_fee_amount': instance.placeFeeAmount,
      'place_fee_mode': instance.placeFeeMode,
    };
