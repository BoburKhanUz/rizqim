// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data19 _$Data19FromJson(Map<String, dynamic> json) => Data19(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data19ToJson(Data19 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
