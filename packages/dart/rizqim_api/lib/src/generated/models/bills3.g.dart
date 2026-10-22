// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bills3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bills3 _$Bills3FromJson(Map<String, dynamic> json) => Bills3(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items24.fromJson(e as Map<String, dynamic>))
      .toList(),
  guests: (json['guests'] as num?)?.toInt(),
);

Map<String, dynamic> _$Bills3ToJson(Bills3 instance) => <String, dynamic>{
  'items': instance.items,
  'guests': instance.guests,
};
