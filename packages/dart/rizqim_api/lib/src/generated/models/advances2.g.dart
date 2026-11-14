// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'advances2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Advances2 _$Advances2FromJson(Map<String, dynamic> json) => Advances2(
  total: json['total'] as String,
  applied: json['applied'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Advances2ToJson(Advances2 instance) => <String, dynamic>{
  'total': instance.total,
  'applied': instance.applied,
  'rows': instance.rows,
};
