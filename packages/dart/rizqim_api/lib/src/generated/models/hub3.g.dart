// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hub3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hub3 _$Hub3FromJson(Map<String, dynamic> json) => Hub3(
  device: json['device'] as String,
  name: json['name'] as String?,
  chosen: json['chosen'] as bool,
);

Map<String, dynamic> _$Hub3ToJson(Hub3 instance) => <String, dynamic>{
  'device': instance.device,
  'name': instance.name,
  'chosen': instance.chosen,
};
