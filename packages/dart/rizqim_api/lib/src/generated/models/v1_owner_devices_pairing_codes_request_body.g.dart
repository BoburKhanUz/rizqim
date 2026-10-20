// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_devices_pairing_codes_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDevicesPairingCodesRequestBody
_$V1OwnerDevicesPairingCodesRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerDevicesPairingCodesRequestBody(
      type: Type4.fromJson(json['type'] as String),
      branchId: (json['branch_id'] as num?)?.toInt(),
      name: json['name'] as String?,
    );

Map<String, dynamic> _$V1OwnerDevicesPairingCodesRequestBodyToJson(
  V1OwnerDevicesPairingCodesRequestBody instance,
) => <String, dynamic>{
  'type': instance.type,
  'branch_id': instance.branchId,
  'name': instance.name,
};
