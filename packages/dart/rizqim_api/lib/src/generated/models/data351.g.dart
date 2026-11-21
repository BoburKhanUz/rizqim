// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data351.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data351 _$Data351FromJson(Map<String, dynamic> json) => Data351(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data351ToJson(Data351 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
