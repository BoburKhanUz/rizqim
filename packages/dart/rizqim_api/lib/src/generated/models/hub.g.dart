// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hub.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hub _$HubFromJson(Map<String, dynamic> json) => Hub(
  device: json['device'] as String,
  name: json['name'] as String?,
  chosen: json['chosen'] as bool,
);

Map<String, dynamic> _$HubToJson(Hub instance) => <String, dynamic>{
  'device': instance.device,
  'name': instance.name,
  'chosen': instance.chosen,
};
