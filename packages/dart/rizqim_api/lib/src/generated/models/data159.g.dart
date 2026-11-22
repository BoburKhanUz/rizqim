// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data159.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data159 _$Data159FromJson(Map<String, dynamic> json) => Data159(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items9.fromJson(e as Map<String, dynamic>))
      .toList(),
  total: json['total'] as String,
);

Map<String, dynamic> _$Data159ToJson(Data159 instance) => <String, dynamic>{
  'items': instance.items,
  'total': instance.total,
};
