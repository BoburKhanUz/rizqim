// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'mxik.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Mxik _$MxikFromJson(Map<String, dynamic> json) => Mxik(
  package: Package.fromJson(json['package'] as Map<String, dynamic>),
  hall: Hall.fromJson(json['hall'] as Map<String, dynamic>),
  extra: Extra.fromJson(json['extra'] as Map<String, dynamic>),
);

Map<String, dynamic> _$MxikToJson(Mxik instance) => <String, dynamic>{
  'package': instance.package,
  'hall': instance.hall,
  'extra': instance.extra,
};
