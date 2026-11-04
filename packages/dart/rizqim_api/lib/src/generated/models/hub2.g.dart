// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hub2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hub2 _$Hub2FromJson(Map<String, dynamic> json) => Hub2(
  device: json['device'] as String,
  name: json['name'] as String?,
  chosen: json['chosen'] as bool,
);

Map<String, dynamic> _$Hub2ToJson(Hub2 instance) => <String, dynamic>{
  'device': instance.device,
  'name': instance.name,
  'chosen': instance.chosen,
};
