// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data248.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data248 _$Data248FromJson(Map<String, dynamic> json) => Data248(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data248ToJson(Data248 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
