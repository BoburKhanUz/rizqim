// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data160.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data160 _$Data160FromJson(Map<String, dynamic> json) => Data160(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items9.fromJson(e as Map<String, dynamic>))
      .toList(),
  total: json['total'] as String,
);

Map<String, dynamic> _$Data160ToJson(Data160 instance) => <String, dynamic>{
  'items': instance.items,
  'total': instance.total,
};
