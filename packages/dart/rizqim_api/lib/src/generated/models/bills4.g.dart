// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bills4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bills4 _$Bills4FromJson(Map<String, dynamic> json) => Bills4(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items29.fromJson(e as Map<String, dynamic>))
      .toList(),
  guests: (json['guests'] as num?)?.toInt(),
);

Map<String, dynamic> _$Bills4ToJson(Bills4 instance) => <String, dynamic>{
  'items': instance.items,
  'guests': instance.guests,
};
