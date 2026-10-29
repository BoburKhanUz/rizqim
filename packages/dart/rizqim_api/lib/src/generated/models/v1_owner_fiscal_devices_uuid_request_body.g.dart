// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_fiscal_devices_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerFiscalDevicesUuidRequestBody
_$V1OwnerFiscalDevicesUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerFiscalDevicesUuidRequestBody(
      smartKassa: json['smart_kassa'] as bool?,
      fiscalKassaId: json['fiscal_kassa_id'] as String?,
    );

Map<String, dynamic> _$V1OwnerFiscalDevicesUuidRequestBodyToJson(
  V1OwnerFiscalDevicesUuidRequestBody instance,
) => <String, dynamic>{
  'smart_kassa': instance.smartKassa,
  'fiscal_kassa_id': instance.fiscalKassaId,
};
