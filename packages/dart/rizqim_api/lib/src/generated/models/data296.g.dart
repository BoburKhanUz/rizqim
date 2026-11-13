// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data296.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data296 _$Data296FromJson(Map<String, dynamic> json) => Data296(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data296ToJson(Data296 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
