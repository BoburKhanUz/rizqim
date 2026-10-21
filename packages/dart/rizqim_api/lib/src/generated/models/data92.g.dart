// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data92.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data92 _$Data92FromJson(Map<String, dynamic> json) => Data92(
  device: Device10.fromJson(json['device'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch10.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data92ToJson(Data92 instance) => <String, dynamic>{
  'device': instance.device,
  'store': instance.store,
  'branch': instance.branch,
};
