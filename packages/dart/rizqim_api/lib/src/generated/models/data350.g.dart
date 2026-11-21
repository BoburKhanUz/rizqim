// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data350.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data350 _$Data350FromJson(Map<String, dynamic> json) => Data350(
  serverTime: json['server_time'] as String,
  device: Device24.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data350ToJson(Data350 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
