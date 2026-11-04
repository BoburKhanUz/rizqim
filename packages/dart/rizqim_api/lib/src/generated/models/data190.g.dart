// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data190.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data190 _$Data190FromJson(Map<String, dynamic> json) => Data190(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data190ToJson(Data190 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
