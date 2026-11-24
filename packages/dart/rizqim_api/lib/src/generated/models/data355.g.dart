// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data355.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data355 _$Data355FromJson(Map<String, dynamic> json) => Data355(
  serverTime: json['server_time'] as String,
  device: Device24.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data355ToJson(Data355 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
