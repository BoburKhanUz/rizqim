// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data273.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data273 _$Data273FromJson(Map<String, dynamic> json) => Data273(
  serverTime: json['server_time'] as String,
  device: Device23.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data273ToJson(Data273 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
