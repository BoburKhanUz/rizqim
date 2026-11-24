// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data258.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data258 _$Data258FromJson(Map<String, dynamic> json) => Data258(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data258ToJson(Data258 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
