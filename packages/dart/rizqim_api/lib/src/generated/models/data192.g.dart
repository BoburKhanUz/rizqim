// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data192.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data192 _$Data192FromJson(Map<String, dynamic> json) => Data192(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data192ToJson(Data192 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
