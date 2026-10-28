// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data182.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data182 _$Data182FromJson(Map<String, dynamic> json) => Data182(
  serverTime: json['server_time'] as String,
  device: Device22.fromJson(json['device'] as Map<String, dynamic>),
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors2.fromJson(e as Map<String, dynamic>))
      .toList(),
  errorsCount: (json['errors_count'] as num).toInt(),
);

Map<String, dynamic> _$Data182ToJson(Data182 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'device': instance.device,
  'errors': instance.errors,
  'errors_count': instance.errorsCount,
};
