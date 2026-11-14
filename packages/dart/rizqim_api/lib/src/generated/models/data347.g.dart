// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data347.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data347 _$Data347FromJson(Map<String, dynamic> json) => Data347(
  serverTime: json['server_time'] as String,
  device: Device23.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data347ToJson(Data347 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
