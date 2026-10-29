// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data119.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data119 _$Data119FromJson(Map<String, dynamic> json) => Data119(
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips.fromJson(e as Map<String, dynamic>))
      .toList(),
  truncated: json['truncated'] as bool,
);

Map<String, dynamic> _$Data119ToJson(Data119 instance) => <String, dynamic>{
  'tips': instance.tips,
  'truncated': instance.truncated,
};
