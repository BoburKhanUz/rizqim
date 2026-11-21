// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings _$WarningsFromJson(Map<String, dynamic> json) => Warnings(
  reason: json['reason'] as String,
  device: json['device'] as String,
  firstSeq: (json['first_seq'] as num).toInt(),
  serverLastSeq: (json['server_last_seq'] as num).toInt(),
  message: json['message'] as String,
);

Map<String, dynamic> _$WarningsToJson(Warnings instance) => <String, dynamic>{
  'reason': instance.reason,
  'device': instance.device,
  'first_seq': instance.firstSeq,
  'server_last_seq': instance.serverLastSeq,
  'message': instance.message,
};
