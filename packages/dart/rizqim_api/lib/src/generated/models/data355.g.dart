// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data355.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data355 _$Data355FromJson(Map<String, dynamic> json) => Data355(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data355ToJson(Data355 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
