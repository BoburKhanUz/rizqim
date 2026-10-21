// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_service_fees_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerServiceFeesRequestBody _$V1OwnerServiceFeesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerServiceFeesRequestBody(
  serviceChargePercent: json['service_charge_percent'] as num,
  placeFeeAmount: json['place_fee_amount'] as num,
  placeFeeMode: PlaceFeeMode.fromJson(json['place_fee_mode'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
  serviceChargeChannels: (json['service_charge_channels'] as List<dynamic>?)
      ?.map((e) => ServiceChargeChannels.fromJson(e as String))
      .toList(),
  placeFeeChannels: (json['place_fee_channels'] as List<dynamic>?)
      ?.map((e) => PlaceFeeChannels.fromJson(e as String))
      .toList(),
  hallId: (json['hall_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerServiceFeesRequestBodyToJson(
  V1OwnerServiceFeesRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'service_charge_percent': instance.serviceChargePercent,
  'service_charge_channels': instance.serviceChargeChannels,
  'place_fee_amount': instance.placeFeeAmount,
  'place_fee_mode': instance.placeFeeMode,
  'place_fee_channels': instance.placeFeeChannels,
  'hall_id': instance.hallId,
};
