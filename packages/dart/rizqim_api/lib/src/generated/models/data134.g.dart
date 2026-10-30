// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data134.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data134 _$Data134FromJson(Map<String, dynamic> json) => Data134(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data134ToJson(Data134 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
