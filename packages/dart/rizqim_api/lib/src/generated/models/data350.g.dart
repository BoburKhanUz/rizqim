// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data350.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data350 _$Data350FromJson(Map<String, dynamic> json) => Data350(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data350ToJson(Data350 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
