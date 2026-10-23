// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_printers_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPrintersIdRequestBody _$V1OwnerPrintersIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPrintersIdRequestBody(
  name: json['name'] as String?,
  purpose: json['purpose'] == null
      ? null
      : Purpose.fromJson(json['purpose'] as String),
  connection: json['connection'] == null
      ? null
      : Connection.fromJson(json['connection'] as String),
  address: json['address'] as String?,
  port: (json['port'] as num?)?.toInt(),
  paperWidth: (json['paper_width'] as num?)?.toInt(),
  stationId: (json['station_id'] as num?)?.toInt(),
  deviceId: json['device_id'] as String?,
  isActive: json['is_active'] as bool?,
  encoding: json['encoding'] as String?,
  printAsImage: json['print_as_image'] as bool?,
);

Map<String, dynamic> _$V1OwnerPrintersIdRequestBodyToJson(
  V1OwnerPrintersIdRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'purpose': instance.purpose,
  'connection': instance.connection,
  'address': instance.address,
  'port': instance.port,
  'paper_width': instance.paperWidth,
  'station_id': instance.stationId,
  'device_id': instance.deviceId,
  'is_active': instance.isActive,
  'encoding': instance.encoding,
  'print_as_image': instance.printAsImage,
};
