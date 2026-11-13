// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data199.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data199 _$Data199FromJson(Map<String, dynamic> json) => Data199(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data199ToJson(Data199 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
