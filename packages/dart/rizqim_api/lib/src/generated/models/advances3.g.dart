// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'advances3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Advances3 _$Advances3FromJson(Map<String, dynamic> json) => Advances3(
  total: json['total'] as String,
  applied: json['applied'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows13.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Advances3ToJson(Advances3 instance) => <String, dynamic>{
  'total': instance.total,
  'applied': instance.applied,
  'rows': instance.rows,
};
