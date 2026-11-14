// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data346.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data346 _$Data346FromJson(Map<String, dynamic> json) => Data346(
  serverTime: json['server_time'] as String,
  device: Device23.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data346ToJson(Data346 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
