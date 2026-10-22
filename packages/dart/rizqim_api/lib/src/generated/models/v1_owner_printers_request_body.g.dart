// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_printers_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPrintersRequestBody _$V1OwnerPrintersRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPrintersRequestBody(
  name: json['name'] as String,
  connection: Connection.fromJson(json['connection'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
  purpose: json['purpose'] == null
      ? null
      : Purpose.fromJson(json['purpose'] as String),
  address: json['address'] as String?,
  port: (json['port'] as num?)?.toInt(),
  paperWidth: (json['paper_width'] as num?)?.toInt(),
  stationId: (json['station_id'] as num?)?.toInt(),
  deviceId: json['device_id'] as String?,
  isActive: json['is_active'] as bool?,
  encoding: json['encoding'] as String?,
  printAsImage: json['print_as_image'] as bool?,
);

Map<String, dynamic> _$V1OwnerPrintersRequestBodyToJson(
  V1OwnerPrintersRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
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
