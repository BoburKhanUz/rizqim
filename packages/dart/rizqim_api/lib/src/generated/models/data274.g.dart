// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data274.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data274 _$Data274FromJson(Map<String, dynamic> json) => Data274(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data274ToJson(Data274 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
