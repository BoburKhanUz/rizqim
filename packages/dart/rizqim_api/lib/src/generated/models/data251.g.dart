// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data251.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data251 _$Data251FromJson(Map<String, dynamic> json) => Data251(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data251ToJson(Data251 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
