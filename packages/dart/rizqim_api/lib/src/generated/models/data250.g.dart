// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data250.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data250 _$Data250FromJson(Map<String, dynamic> json) => Data250(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data250ToJson(Data250 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
