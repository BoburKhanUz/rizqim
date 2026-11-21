// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data352.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data352 _$Data352FromJson(Map<String, dynamic> json) => Data352(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data352ToJson(Data352 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
