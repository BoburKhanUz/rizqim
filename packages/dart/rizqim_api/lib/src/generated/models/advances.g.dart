// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'advances.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Advances _$AdvancesFromJson(Map<String, dynamic> json) => Advances(
  total: json['total'] as String,
  applied: json['applied'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$AdvancesToJson(Advances instance) => <String, dynamic>{
  'total': instance.total,
  'applied': instance.applied,
  'rows': instance.rows,
};
