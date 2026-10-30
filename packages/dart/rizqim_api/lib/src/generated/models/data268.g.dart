// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data268.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data268 _$Data268FromJson(Map<String, dynamic> json) => Data268(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] as String?,
);

Map<String, dynamic> _$Data268ToJson(Data268 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'service_state': instance.serviceState,
  'service_state_at': instance.serviceStateAt,
};
