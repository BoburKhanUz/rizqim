// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data347.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data347 _$Data347FromJson(Map<String, dynamic> json) => Data347(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data347ToJson(Data347 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
