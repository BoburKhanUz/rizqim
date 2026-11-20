// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data165.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data165 _$Data165FromJson(Map<String, dynamic> json) => Data165(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data165ToJson(Data165 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
