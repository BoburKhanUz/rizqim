// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data254.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data254 _$Data254FromJson(Map<String, dynamic> json) => Data254(
  requestId: json['request_id'] as String,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
);

Map<String, dynamic> _$Data254ToJson(Data254 instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
};
