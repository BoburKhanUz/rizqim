// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data62.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data62 _$Data62FromJson(Map<String, dynamic> json) => Data62(
  device: Device7.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch3.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data62ToJson(Data62 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
};
