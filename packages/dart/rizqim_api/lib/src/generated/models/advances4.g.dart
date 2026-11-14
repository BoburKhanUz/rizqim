// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'advances4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Advances4 _$Advances4FromJson(Map<String, dynamic> json) => Advances4(
  total: json['total'] as String,
  applied: json['applied'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Advances4ToJson(Advances4 instance) => <String, dynamic>{
  'total': instance.total,
  'applied': instance.applied,
  'rows': instance.rows,
};
