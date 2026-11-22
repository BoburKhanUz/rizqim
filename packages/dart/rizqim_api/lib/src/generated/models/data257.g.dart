// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data257.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data257 _$Data257FromJson(Map<String, dynamic> json) => Data257(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data257ToJson(Data257 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
