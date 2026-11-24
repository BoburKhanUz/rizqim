// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data170.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data170 _$Data170FromJson(Map<String, dynamic> json) => Data170(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data170ToJson(Data170 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
