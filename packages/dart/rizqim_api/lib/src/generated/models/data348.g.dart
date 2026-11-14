// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data348.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data348 _$Data348FromJson(Map<String, dynamic> json) => Data348(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data348ToJson(Data348 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
