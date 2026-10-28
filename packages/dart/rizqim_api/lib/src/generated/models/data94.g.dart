// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data94.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data94 _$Data94FromJson(Map<String, dynamic> json) => Data94(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  purpose: json['purpose'] as String,
  connection: json['connection'] as String,
  address: json['address'] as String?,
  port: (json['port'] as num?)?.toInt(),
  paperWidth: (json['paper_width'] as num).toInt(),
  stationId: (json['station_id'] as num?)?.toInt(),
  deviceId: json['device_id'] as String?,
  isActive: json['is_active'] as bool,
  encoding: json['encoding'] as String?,
  printAsImage: json['print_as_image'] as bool,
);

Map<String, dynamic> _$Data94ToJson(Data94 instance) => <String, dynamic>{
  'id': instance.id,
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
