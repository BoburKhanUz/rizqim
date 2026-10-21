// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data180.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data180 _$Data180FromJson(Map<String, dynamic> json) => Data180(
  device: Device18.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store3.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch12.fromJson(json['branch'] as Map<String, dynamic>),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data180ToJson(Data180 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
  'branches': instance.branches,
};
