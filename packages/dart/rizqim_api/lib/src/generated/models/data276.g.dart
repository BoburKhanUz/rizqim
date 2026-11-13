// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data276.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data276 _$Data276FromJson(Map<String, dynamic> json) => Data276(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data276ToJson(Data276 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
